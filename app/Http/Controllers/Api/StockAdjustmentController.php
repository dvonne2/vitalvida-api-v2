<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\Item;
use App\Models\DeliveryAgent;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAdjustmentController extends Controller
{
    /**
     * Display a listing of stock adjustments.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockAdjustment::with(['item', 'deliveryAgent', 'employee', 'approvedBy']);

        // Apply filters
        if ($request->has('adjustment_type')) {
            $query->where('adjustment_type', $request->adjustment_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
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

        $adjustments = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $adjustments,
            'filters' => [
                'adjustment_types' => [
                    'damage', 'loss', 'found', 'theft', 'expiry', 
                    'quality_control', 'inventory_count', 'system_adjustment'
                ],
                'statuses' => ['pending', 'approved', 'rejected'],
                'items' => Item::active()->get(['id', 'name', 'sku']),
                'delivery_agents' => DeliveryAgent::active()->get(['id', 'name'])
            ]
        ]);
    }

    /**
     * Store a newly created stock adjustment.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'delivery_agent_id' => 'nullable|exists:delivery_agents,id',
            'employee_id' => 'nullable|exists:employees,id',
            'adjustment_type' => 'required|in:damage,loss,found,theft,expiry,quality_control,inventory_count,system_adjustment',
            'quantity' => 'required|integer',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'date' => 'required|date'
        ]);

        DB::beginTransaction();

        try {
            // Validate stock availability for decreases
            if ($request->quantity < 0) {
                $item = Item::find($request->item_id);
                if ($item->stock_quantity < abs($request->quantity)) {
                    throw new \Exception("Insufficient stock for adjustment. Available: {$item->stock_quantity}, Required: " . abs($request->quantity));
                }
            }

            $adjustment = StockAdjustment::create([
                'item_id' => $request->item_id,
                'delivery_agent_id' => $request->delivery_agent_id,
                'employee_id' => $request->employee_id,
                'adjustment_type' => $request->adjustment_type,
                'quantity' => $request->quantity,
                'reason' => $request->reason,
                'notes' => $request->notes,
                'date' => $request->date,
                'status' => 'pending'
            ]);

            DB::commit();

            Log::info("Stock adjustment created: {$adjustment->reference_number}");

            return response()->json([
                'success' => true,
                'message' => 'Stock adjustment created successfully',
                'data' => $adjustment->load(['item', 'deliveryAgent', 'employee'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Failed to create stock adjustment: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create stock adjustment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified stock adjustment.
     */
    public function show(StockAdjustment $stockAdjustment): JsonResponse
    {
        $stockAdjustment->load(['item', 'deliveryAgent', 'employee', 'approvedBy']);

        return response()->json([
            'success' => true,
            'data' => $stockAdjustment
        ]);
    }

    /**
     * Update the specified stock adjustment.
     */
    public function update(Request $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        if ($stockAdjustment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update approved stock adjustment'
            ], 400);
        }

        $request->validate([
            'quantity' => 'sometimes|integer',
            'reason' => 'sometimes|string|max:255',
            'notes' => 'nullable|string',
            'date' => 'sometimes|date'
        ]);

        $stockAdjustment->update($request->only(['quantity', 'reason', 'notes', 'date']));

        return response()->json([
            'success' => true,
            'message' => 'Stock adjustment updated successfully',
            'data' => $stockAdjustment->load(['item', 'deliveryAgent', 'employee'])
        ]);
    }

    /**
     * Remove the specified stock adjustment.
     */
    public function destroy(StockAdjustment $stockAdjustment): JsonResponse
    {
        if ($stockAdjustment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete approved stock adjustment'
            ], 400);
        }

        $stockAdjustment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Stock adjustment deleted successfully'
        ]);
    }

    /**
     * Approve stock adjustment.
     */
    public function approve(StockAdjustment $stockAdjustment): JsonResponse
    {
        if (!$stockAdjustment->canApprove()) {
            return response()->json([
                'success' => false,
                'message' => 'Stock adjustment cannot be approved'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $stockAdjustment->approve(auth()->id());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjustment approved successfully',
                'data' => $stockAdjustment->load(['item', 'deliveryAgent', 'employee'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Failed to approve stock adjustment: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve stock adjustment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk approve stock adjustments.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $request->validate([
            'adjustment_ids' => 'required|array',
            'adjustment_ids.*' => 'exists:stock_adjustments,id'
        ]);

        DB::beginTransaction();

        try {
            $adjustments = StockAdjustment::whereIn('id', $request->adjustment_ids)
                ->where('status', 'pending')
                ->get();

            foreach ($adjustments as $adjustment) {
                $adjustment->approve(auth()->id());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully approved {$adjustments->count()} adjustments"
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Failed to bulk approve adjustments: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve adjustments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock adjustment statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_adjustments' => StockAdjustment::count(),
            'pending_adjustments' => StockAdjustment::pending()->count(),
            'approved_adjustments' => StockAdjustment::approved()->count(),
            'total_increases' => StockAdjustment::where('quantity', '>', 0)->sum('quantity'),
            'total_decreases' => abs(StockAdjustment::where('quantity', '<', 0)->sum('quantity')),
            'adjustments_by_type' => StockAdjustment::selectRaw('adjustment_type, COUNT(*) as count')
                ->groupBy('adjustment_type')
                ->get(),
            'recent_adjustments' => StockAdjustment::with(['item', 'deliveryAgent'])
                ->latest()
                ->limit(10)
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get low stock alerts.
     */
    public function lowStockAlerts(): JsonResponse
    {
        $lowStockItems = Item::lowStock()
            ->with(['category', 'supplier'])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'current_stock' => $item->stock_quantity,
                    'reorder_level' => $item->reorder_level,
                    'reorder_quantity' => $item->reorder_quantity,
                    'category' => $item->category->name ?? 'Uncategorized',
                    'supplier' => $item->supplier->name ?? 'No Supplier'
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $lowStockItems
        ]);
    }
} 