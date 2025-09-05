<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\Item;
use App\Models\DeliveryAgent;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryCountController extends Controller
{
    /**
     * Display a listing of inventory counts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryCount::with(['deliveryAgent', 'employee', 'approvedBy']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('delivery_agent_id')) {
            $query->where('delivery_agent_id', $request->delivery_agent_id);
        }

        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        $counts = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $counts,
            'filters' => [
                'statuses' => ['pending', 'in_progress', 'completed', 'approved'],
                'types' => ['full', 'partial'],
                'delivery_agents' => DeliveryAgent::active()->get(['id', 'name'])
            ]
        ]);
    }

    /**
     * Store a newly created inventory count.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'delivery_agent_id' => 'nullable|exists:delivery_agents,id',
            'employee_id' => 'nullable|exists:employees,id',
            'date' => 'required|date',
            'type' => 'required|in:full,partial',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.expected_quantity' => 'required|integer|min:0'
        ]);

        DB::beginTransaction();

        try {
            $count = InventoryCount::create([
                'delivery_agent_id' => $request->delivery_agent_id,
                'employee_id' => $request->employee_id,
                'date' => $request->date,
                'type' => $request->type,
                'notes' => $request->notes,
                'status' => 'pending'
            ]);

            // Create count items
            foreach ($request->items as $item) {
                InventoryCountItem::create([
                    'inventory_count_id' => $count->id,
                    'item_id' => $item['item_id'],
                    'expected_quantity' => $item['expected_quantity']
                ]);
            }

            DB::commit();

            Log::info("Inventory count created: {$count->count_number}");

            return response()->json([
                'success' => true,
                'message' => 'Inventory count created successfully',
                'data' => $count->load(['deliveryAgent', 'employee', 'items.item'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Failed to create inventory count: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create inventory count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified inventory count.
     */
    public function show(InventoryCount $inventoryCount): JsonResponse
    {
        $inventoryCount->load(['deliveryAgent', 'employee', 'approvedBy', 'items.item']);

        return response()->json([
            'success' => true,
            'data' => $inventoryCount
        ]);
    }

    /**
     * Update the specified inventory count.
     */
    public function update(Request $request, InventoryCount $inventoryCount): JsonResponse
    {
        if ($inventoryCount->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update inventory count that is not pending'
            ], 400);
        }

        $request->validate([
            'delivery_agent_id' => 'sometimes|exists:delivery_agents,id',
            'employee_id' => 'nullable|exists:employees,id',
            'date' => 'sometimes|date',
            'type' => 'sometimes|in:full,partial',
            'notes' => 'nullable|string'
        ]);

        $inventoryCount->update($request->only([
            'delivery_agent_id', 'employee_id', 'date', 'type', 'notes'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Inventory count updated successfully',
            'data' => $inventoryCount->load(['deliveryAgent', 'employee', 'items.item'])
        ]);
    }

    /**
     * Remove the specified inventory count.
     */
    public function destroy(InventoryCount $inventoryCount): JsonResponse
    {
        if ($inventoryCount->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete inventory count that is not pending'
            ], 400);
        }

        $inventoryCount->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inventory count deleted successfully'
        ]);
    }

    /**
     * Start inventory count.
     */
    public function start(InventoryCount $inventoryCount): JsonResponse
    {
        if (!$inventoryCount->canStart()) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory count cannot be started'
            ], 400);
        }

        $inventoryCount->start();

        return response()->json([
            'success' => true,
            'message' => 'Inventory count started successfully',
            'data' => $inventoryCount->load(['deliveryAgent', 'employee', 'items.item'])
        ]);
    }

    /**
     * Complete inventory count.
     */
    public function complete(InventoryCount $inventoryCount): JsonResponse
    {
        if (!$inventoryCount->canComplete()) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory count cannot be completed'
            ], 400);
        }

        $inventoryCount->complete();

        return response()->json([
            'success' => true,
            'message' => 'Inventory count completed successfully',
            'data' => $inventoryCount->load(['deliveryAgent', 'employee', 'items.item'])
        ]);
    }

    /**
     * Approve inventory count.
     */
    public function approve(InventoryCount $inventoryCount): JsonResponse
    {
        if (!$inventoryCount->canApprove()) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory count cannot be approved'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $inventoryCount->approve(auth()->id());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inventory count approved successfully',
                'data' => $inventoryCount->load(['deliveryAgent', 'employee', 'items.item'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Failed to approve inventory count: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve inventory count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update count item.
     */
    public function updateCountItem(Request $request, InventoryCount $inventoryCount, InventoryCountItem $item): JsonResponse
    {
        if ($inventoryCount->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Inventory count must be in progress to update items'
            ], 400);
        }

        $request->validate([
            'actual_quantity' => 'required|integer|min:0'
        ]);

        $item->update(['actual_quantity' => $request->actual_quantity]);

        return response()->json([
            'success' => true,
            'message' => 'Count item updated successfully',
            'data' => $item->load('item')
        ]);
    }

    /**
     * Generate discrepancy report.
     */
    public function generateDiscrepancyReport(InventoryCount $inventoryCount): JsonResponse
    {
        $discrepancies = $inventoryCount->items()
            ->where('variance', '!=', 0)
            ->with('item')
            ->get()
            ->map(function ($item) {
                return [
                    'item_name' => $item->item->name,
                    'sku' => $item->item->sku,
                    'expected_quantity' => $item->expected_quantity,
                    'actual_quantity' => $item->actual_quantity,
                    'variance' => $item->variance,
                    'variance_percentage' => $item->variance_percentage,
                    'discrepancy_type' => $item->discrepancy_type
                ];
            });

        $summary = [
            'total_items' => $inventoryCount->total_items,
            'counted_items' => $inventoryCount->counted_items,
            'discrepancy_count' => $inventoryCount->discrepancy_count,
            'total_variance' => $inventoryCount->total_variance,
            'progress_percentage' => $inventoryCount->progress_percentage
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'discrepancies' => $discrepancies
            ]
        ]);
    }

    /**
     * Get inventory count statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_counts' => InventoryCount::count(),
            'pending_counts' => InventoryCount::pending()->count(),
            'in_progress_counts' => InventoryCount::inProgress()->count(),
            'completed_counts' => InventoryCount::completed()->count(),
            'approved_counts' => InventoryCount::approved()->count(),
            'full_counts' => InventoryCount::where('type', 'full')->count(),
            'partial_counts' => InventoryCount::where('type', 'partial')->count(),
            'recent_counts' => InventoryCount::with(['deliveryAgent', 'employee'])
                ->latest()
                ->limit(5)
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
} 