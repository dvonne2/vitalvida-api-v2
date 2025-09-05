<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransferOrder;
use App\Models\TransferOrderItem;
use App\Models\DeliveryAgent;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferOrderController extends Controller
{
    /**
     * Display a listing of transfer orders.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TransferOrder::with(['deliveryAgent', 'items.item']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_location')) {
            $query->where('from_location', $request->from_location);
        }

        if ($request->has('to_location')) {
            $query->where('to_location', $request->to_location);
        }

        if ($request->has('delivery_agent_id')) {
            $query->where('delivery_agent_id', $request->delivery_agent_id);
        }

        if ($request->has('date_from')) {
            $query->where('transfer_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('transfer_date', '<=', $request->date_to);
        }

        $transferOrders = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $transferOrders,
            'filters' => [
                'statuses' => ['pending', 'approved', 'completed', 'cancelled'],
                'locations' => DeliveryAgent::distinct()->pluck('location')->filter(),
                'delivery_agents' => DeliveryAgent::active()->get(['id', 'name'])
            ]
        ]);
    }

    /**
     * Store a newly created transfer order.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'from_location' => 'required|string',
            'to_location' => 'required|string|different:from_location',
            'delivery_agent_id' => 'nullable|exists:delivery_agents,id',
            'transfer_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:transfer_date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0'
        ]);

        DB::beginTransaction();

        try {
            // Validate stock availability
            foreach ($request->items as $item) {
                $inventoryItem = Item::where('id', $item['item_id'])
                    ->where('location', $request->from_location)
                    ->first();

                if (!$inventoryItem || $inventoryItem->stock_quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for item {$inventoryItem->name} at {$request->from_location}");
                }
            }

            $transferOrder = TransferOrder::create([
                'from_location' => $request->from_location,
                'to_location' => $request->to_location,
                'delivery_agent_id' => $request->delivery_agent_id,
                'transfer_date' => $request->transfer_date,
                'expected_date' => $request->expected_date,
                'notes' => $request->notes,
                'status' => 'pending'
            ]);

            // Create transfer order items
            foreach ($request->items as $item) {
                TransferOrderItem::create([
                    'transfer_order_id' => $transferOrder->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost']
                ]);
            }

            // Calculate totals
            $transferOrder->calculateTotals();

            DB::commit();

            Log::info("Transfer order created: {$transferOrder->transfer_number}");

            return response()->json([
                'success' => true,
                'message' => 'Transfer order created successfully',
                'data' => $transferOrder->load(['deliveryAgent', 'items.item'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Failed to create transfer order: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create transfer order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified transfer order.
     */
    public function show(TransferOrder $transferOrder): JsonResponse
    {
        $transferOrder->load(['deliveryAgent', 'items.item', 'approvedBy', 'cancelledBy']);

        return response()->json([
            'success' => true,
            'data' => $transferOrder
        ]);
    }

    /**
     * Update the specified transfer order.
     */
    public function update(Request $request, TransferOrder $transferOrder): JsonResponse
    {
        if ($transferOrder->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update transfer order that is not pending'
            ], 400);
        }

        $request->validate([
            'from_location' => 'sometimes|string',
            'to_location' => 'sometimes|string|different:from_location',
            'delivery_agent_id' => 'nullable|exists:delivery_agents,id',
            'transfer_date' => 'sometimes|date',
            'expected_date' => 'nullable|date|after_or_equal:transfer_date',
            'notes' => 'nullable|string'
        ]);

        $transferOrder->update($request->only([
            'from_location', 'to_location', 'delivery_agent_id', 
            'transfer_date', 'expected_date', 'notes'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Transfer order updated successfully',
            'data' => $transferOrder->load(['deliveryAgent', 'items.item'])
        ]);
    }

    /**
     * Remove the specified transfer order.
     */
    public function destroy(TransferOrder $transferOrder): JsonResponse
    {
        if ($transferOrder->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete transfer order that is not pending'
            ], 400);
        }

        $transferOrder->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transfer order deleted successfully'
        ]);
    }

    /**
     * Approve transfer order.
     */
    public function approve(TransferOrder $transferOrder): JsonResponse
    {
        if (!$transferOrder->canApprove()) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer order cannot be approved'
            ], 400);
        }

        $transferOrder->approve(auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Transfer order approved successfully',
            'data' => $transferOrder->load(['deliveryAgent', 'items.item'])
        ]);
    }

    /**
     * Complete transfer order.
     */
    public function complete(TransferOrder $transferOrder): JsonResponse
    {
        if (!$transferOrder->canComplete()) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer order cannot be completed'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $transferOrder->complete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfer order completed successfully',
                'data' => $transferOrder->load(['deliveryAgent', 'items.item'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Failed to complete transfer order: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete transfer order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel transfer order.
     */
    public function cancel(Request $request, TransferOrder $transferOrder): JsonResponse
    {
        if (!$transferOrder->canCancel()) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer order cannot be cancelled'
            ], 400);
        }

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $transferOrder->cancel(auth()->id(), $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Transfer order cancelled successfully',
            'data' => $transferOrder->load(['deliveryAgent', 'items.item'])
        ]);
    }

    /**
     * Get transfer order statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_transfers' => TransferOrder::count(),
            'pending_transfers' => TransferOrder::pending()->count(),
            'approved_transfers' => TransferOrder::approved()->count(),
            'completed_transfers' => TransferOrder::completed()->count(),
            'cancelled_transfers' => TransferOrder::cancelled()->count(),
            'total_value' => TransferOrder::sum('total_value'),
            'average_transfer_value' => TransferOrder::avg('total_value'),
            'recent_transfers' => TransferOrder::with(['deliveryAgent'])
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