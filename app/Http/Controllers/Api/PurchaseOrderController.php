<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\DeliveryAgent;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderController extends Controller
{
    /**
     * Display a listing of purchase orders.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with(['supplier', 'deliveryAgent', 'items.item']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
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

        $purchaseOrders = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $purchaseOrders,
            'filters' => [
                'statuses' => ['pending', 'approved', 'delivered', 'cancelled'],
                'suppliers' => Supplier::active()->get(['id', 'name']),
                'delivery_agents' => DeliveryAgent::active()->get(['id', 'name'])
            ]
        ]);
    }

    /**
     * Store a newly created purchase order.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'delivery_agent_id' => 'nullable|exists:delivery_agents,id',
            'date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:date',
            'payment_terms' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.description' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $request->supplier_id,
                'delivery_agent_id' => $request->delivery_agent_id,
                'date' => $request->date,
                'expected_date' => $request->expected_date,
                'payment_terms' => $request->payment_terms,
                'shipping_address' => $request->shipping_address,
                'billing_address' => $request->billing_address,
                'notes' => $request->notes,
                'status' => 'pending'
            ]);

            // Create purchase order items
            foreach ($request->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'description' => $item['description'] ?? null
                ]);
            }

            // Update total amount
            $purchaseOrder->update(['total_amount' => $purchaseOrder->calculateTotalAmount()]);

            DB::commit();

            Log::info("Purchase order created: {$purchaseOrder->order_number}");

            return response()->json([
                'success' => true,
                'message' => 'Purchase order created successfully',
                'data' => $purchaseOrder->load(['supplier', 'deliveryAgent', 'items.item'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Failed to create purchase order: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified purchase order.
     */
    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load(['supplier', 'deliveryAgent', 'items.item', 'approvedBy', 'cancelledBy']);

        return response()->json([
            'success' => true,
            'data' => $purchaseOrder
        ]);
    }

    /**
     * Update the specified purchase order.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update purchase order that is not pending'
            ], 400);
        }

        $request->validate([
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'delivery_agent_id' => 'nullable|exists:delivery_agents,id',
            'date' => 'sometimes|date',
            'expected_date' => 'nullable|date|after_or_equal:date',
            'payment_terms' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        $purchaseOrder->update($request->only([
            'supplier_id', 'delivery_agent_id', 'date', 'expected_date',
            'payment_terms', 'shipping_address', 'billing_address', 'notes'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Purchase order updated successfully',
            'data' => $purchaseOrder->load(['supplier', 'deliveryAgent', 'items.item'])
        ]);
    }

    /**
     * Remove the specified purchase order.
     */
    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete purchase order that is not pending'
            ], 400);
        }

        $purchaseOrder->delete();

        return response()->json([
            'success' => true,
            'message' => 'Purchase order deleted successfully'
        ]);
    }

    /**
     * Mark purchase order as received.
     */
    public function receive(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order must be approved before receiving'
            ], 400);
        }

        $request->validate([
            'received_items' => 'required|array',
            'received_items.*.item_id' => 'required|exists:purchase_order_items,id',
            'received_items.*.quantity' => 'required|integer|min:1'
        ]);

        DB::beginTransaction();

        try {
            foreach ($request->received_items as $receivedItem) {
                $purchaseOrderItem = $purchaseOrder->items()->findOrFail($receivedItem['item_id']);
                
                if ($purchaseOrderItem->received_quantity + $receivedItem['quantity'] > $purchaseOrderItem->quantity) {
                    throw new \Exception("Cannot receive more than ordered quantity for item {$purchaseOrderItem->item->name}");
                }

                $purchaseOrderItem->update([
                    'received_quantity' => $purchaseOrderItem->received_quantity + $receivedItem['quantity']
                ]);

                // Update inventory
                $item = Item::find($purchaseOrderItem->item_id);
                if ($item) {
                    $item->purchase($receivedItem['quantity'], $purchaseOrderItem->unit_cost);
                }
            }

            // Check if all items are received
            if ($purchaseOrder->isFullyReceived()) {
                $purchaseOrder->update(['status' => 'delivered', 'delivery_date' => now()]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Items received successfully',
                'data' => $purchaseOrder->load(['supplier', 'deliveryAgent', 'items.item'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Failed to receive items: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to receive items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve purchase order.
     */
    public function approve(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (!$purchaseOrder->canApprove()) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order cannot be approved'
            ], 400);
        }

        $purchaseOrder->approve(auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Purchase order approved successfully',
            'data' => $purchaseOrder->load(['supplier', 'deliveryAgent', 'items.item'])
        ]);
    }

    /**
     * Cancel purchase order.
     */
    public function cancel(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (!$purchaseOrder->canCancel()) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order cannot be cancelled'
            ], 400);
        }

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $purchaseOrder->cancel(auth()->id(), $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Purchase order cancelled successfully',
            'data' => $purchaseOrder->load(['supplier', 'deliveryAgent', 'items.item'])
        ]);
    }

    /**
     * Get purchase order statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_orders' => PurchaseOrder::count(),
            'pending_orders' => PurchaseOrder::pending()->count(),
            'approved_orders' => PurchaseOrder::approved()->count(),
            'delivered_orders' => PurchaseOrder::delivered()->count(),
            'cancelled_orders' => PurchaseOrder::cancelled()->count(),
            'total_value' => PurchaseOrder::sum('total_amount'),
            'average_order_value' => PurchaseOrder::avg('total_amount'),
            'recent_orders' => PurchaseOrder::with(['supplier', 'deliveryAgent'])
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