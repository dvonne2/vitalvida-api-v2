<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Bin;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\DeliveryAgent;
use App\Models\Zobin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryFlowController extends Controller
{
    /**
     * Get goods IN/OUT/Returns summary
     * GET /api/inventory/flow/summary
     */
    public function getFlowSummary(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $summary = InventoryMovement::select('movement_type')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(quantity_changed) as total_quantity')
            ->selectRaw('SUM(total_cost) as total_value')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('movement_type')
            ->get();

        $dailyFlow = InventoryMovement::selectRaw('DATE(created_at) as date')
            ->selectRaw('movement_type')
            ->selectRaw('SUM(quantity_changed) as quantity')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date', 'movement_type')
            ->orderBy('date')
            ->get();

        $topProducts = InventoryMovement::select('item_name')
            ->selectRaw('SUM(ABS(quantity_changed)) as total_movement')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('item_name')
            ->orderBy('total_movement', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'summary' => $summary,
                'daily_flow' => $dailyFlow,
                'top_products' => $topProducts,
                'total_movements' => InventoryMovement::whereBetween('created_at', [$startDate, $endDate])->count()
            ]
        ]);
    }

    /**
     * Create goods received entry
     * POST /api/inventory/goods-in
     */
    public function createGoodsIn(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'source_type' => 'required|in:supplier,factory,return,transfer',
            'source_reference' => 'required|string',
            'destination_bin_id' => 'required|exists:bins,id',
            'received_by' => 'required|exists:users,id',
            'notes' => 'nullable|string',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $movements = [];
            $totalValue = 0;

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $itemValue = $item['quantity'] * $item['unit_cost'];
                $totalValue += $itemValue;

                // Create inventory movement
                $movement = InventoryMovement::create([
                    'movement_type' => 'inbound',
                    'item_id' => $product->id,
                    'item_name' => $product->name,
                    'item_sku' => $product->sku,
                    'quantity_before' => $product->available_quantity,
                    'quantity_changed' => $item['quantity'],
                    'quantity_after' => $product->available_quantity + $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $itemValue,
                    'source_type' => $request->source_type,
                    'source_reference' => $request->source_reference,
                    'destination_bin_id' => $request->destination_bin_id,
                    'performed_by' => $request->received_by,
                    'notes' => $request->notes,
                    'purchase_order_id' => $request->purchase_order_id,
                    'movement_at' => now()
                ]);

                // Update product quantity
                $product->increment('available_quantity', $item['quantity']);

                // Update bin stock if applicable
                if ($request->destination_bin_id) {
                    $this->updateBinStock($request->destination_bin_id, $product->id, $item['quantity'], 'add');
                }

                $movements[] = $movement;
            }

            // Update purchase order if provided
            if ($request->purchase_order_id) {
                $this->updatePurchaseOrderStatus($request->purchase_order_id, $request->items);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Goods received successfully',
                'data' => [
                    'movements' => $movements,
                    'total_value' => $totalValue,
                    'items_count' => count($request->items)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process goods in',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process goods outbound
     * POST /api/inventory/goods-out
     */
    public function processGoodsOut(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'source_bin_id' => 'required|exists:bins,id',
            'destination_type' => 'required|in:customer,da,transfer,damaged',
            'destination_reference' => 'required|string',
            'processed_by' => 'required|exists:users,id',
            'notes' => 'nullable|string',
            'order_id' => 'nullable|exists:orders,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $movements = [];
            $insufficientStock = [];

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                
                // Check if sufficient stock is available
                if ($product->available_quantity < $item['quantity']) {
                    $insufficientStock[] = [
                        'product' => $product->name,
                        'requested' => $item['quantity'],
                        'available' => $product->available_quantity
                    ];
                    continue;
                }

                // Check bin stock if applicable
                if ($request->source_bin_id) {
                    $binStock = $this->getBinStock($request->source_bin_id, $product->id);
                    if ($binStock < $item['quantity']) {
                        $insufficientStock[] = [
                            'product' => $product->name,
                            'requested' => $item['quantity'],
                            'available_in_bin' => $binStock
                        ];
                        continue;
                    }
                }

                // Create inventory movement
                $movement = InventoryMovement::create([
                    'movement_type' => 'outbound',
                    'item_id' => $product->id,
                    'item_name' => $product->name,
                    'item_sku' => $product->sku,
                    'quantity_before' => $product->available_quantity,
                    'quantity_changed' => -$item['quantity'],
                    'quantity_after' => $product->available_quantity - $item['quantity'],
                    'source_bin_id' => $request->source_bin_id,
                    'destination_type' => $request->destination_type,
                    'destination_reference' => $request->destination_reference,
                    'performed_by' => $request->processed_by,
                    'notes' => $request->notes,
                    'order_id' => $request->order_id,
                    'movement_at' => now()
                ]);

                // Update product quantity
                $product->decrement('available_quantity', $item['quantity']);

                // Update bin stock if applicable
                if ($request->source_bin_id) {
                    $this->updateBinStock($request->source_bin_id, $product->id, $item['quantity'], 'subtract');
                }

                $movements[] = $movement;
            }

            if (!empty($insufficientStock)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock for some items',
                    'insufficient_stock' => $insufficientStock
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Goods outbound processed successfully',
                'data' => [
                    'movements' => $movements,
                    'items_count' => count($request->items)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process goods out',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process return transactions
     * POST /api/inventory/returns
     */
    public function processReturns(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.return_reason' => 'required|in:damaged,expired,wrong_item,customer_return',
            'source_type' => 'required|in:customer,da,store',
            'source_reference' => 'required|string',
            'destination_bin_id' => 'required|exists:bins,id',
            'processed_by' => 'required|exists:users,id',
            'notes' => 'nullable|string',
            'order_id' => 'nullable|exists:orders,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $movements = [];

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                // Create return movement
                $movement = InventoryMovement::create([
                    'movement_type' => 'return',
                    'item_id' => $product->id,
                    'item_name' => $product->name,
                    'item_sku' => $product->sku,
                    'quantity_before' => $product->available_quantity,
                    'quantity_changed' => $item['quantity'],
                    'quantity_after' => $product->available_quantity + $item['quantity'],
                    'source_type' => $request->source_type,
                    'source_reference' => $request->source_reference,
                    'destination_bin_id' => $request->destination_bin_id,
                    'performed_by' => $request->processed_by,
                    'notes' => $request->notes . ' - Reason: ' . $item['return_reason'],
                    'order_id' => $request->order_id,
                    'return_reason' => $item['return_reason'],
                    'movement_at' => now()
                ]);

                // Update product quantity (only for non-damaged returns)
                if ($item['return_reason'] !== 'damaged') {
                    $product->increment('available_quantity', $item['quantity']);
                    
                    // Update bin stock if applicable
                    if ($request->destination_bin_id) {
                        $this->updateBinStock($request->destination_bin_id, $product->id, $item['quantity'], 'add');
                    }
                }

                $movements[] = $movement;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Returns processed successfully',
                'data' => [
                    'movements' => $movements,
                    'items_count' => count($request->items)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process returns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all purchase orders
     * GET /api/purchase-orders
     */
    public function getPurchaseOrders(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with(['items', 'supplier']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $purchaseOrders = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $purchaseOrders
        ]);
    }

    /**
     * Create new purchase order
     * POST /api/purchase-orders
     */
    public function createPurchaseOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:suppliers,id',
            'expected_delivery_date' => 'required|date|after:today',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'priority' => 'sometimes|in:low,medium,high,urgent'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['unit_cost'];
            }

            $purchaseOrder = PurchaseOrder::create([
                'po_number' => 'PO-' . strtoupper(uniqid()),
                'supplier_id' => $request->supplier_id,
                'total_amount' => $totalAmount,
                'expected_delivery_date' => $request->expected_delivery_date,
                'status' => 'pending',
                'priority' => $request->priority ?? 'medium',
                'notes' => $request->notes,
                'created_by' => auth()->id()
            ]);

            foreach ($request->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $item['quantity'] * $item['unit_cost']
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase order created successfully',
                'data' => $purchaseOrder->load('items')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update purchase order status
     * PUT /api/purchase-orders/{poId}
     */
    public function updatePurchaseOrder(Request $request, $poId): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::find($poId);

        if (!$purchaseOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,confirmed,shipped,received,cancelled',
            'expected_delivery_date' => 'sometimes|date',
            'notes' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $purchaseOrder->update($request->only(['status', 'expected_delivery_date', 'notes']));

        return response()->json([
            'success' => true,
            'message' => 'Purchase order updated successfully',
            'data' => $purchaseOrder->fresh()
        ]);
    }

    /**
     * Get purchase order line items
     * GET /api/purchase-orders/{poId}/items
     */
    public function getPurchaseOrderItems($poId): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::with('items.product')->find($poId);

        if (!$purchaseOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $purchaseOrder->items
        ]);
    }

    /**
     * Receive stock from supplier
     * POST /api/purchase-orders/{poId}/receive
     */
    public function receiveStock(Request $request, $poId): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::with('items')->find($poId);

        if (!$purchaseOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found'
            ], 404);
        }

        if ($purchaseOrder->status !== 'shipped') {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order is not in shipped status'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'received_items' => 'required|array',
            'received_items.*.item_id' => 'required|exists:purchase_order_items,id',
            'received_items.*.quantity_received' => 'required|integer|min:1',
            'destination_bin_id' => 'required|exists:bins,id',
            'received_by' => 'required|exists:users,id',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($request->received_items as $receivedItem) {
                $poItem = $purchaseOrder->items->find($receivedItem['item_id']);
                
                if (!$poItem) {
                    continue;
                }

                $product = Product::find($poItem->product_id);
                $itemValue = $receivedItem['quantity_received'] * $poItem->unit_cost;

                // Create inventory movement
                InventoryMovement::create([
                    'movement_type' => 'inbound',
                    'item_id' => $product->id,
                    'item_name' => $product->name,
                    'item_sku' => $product->sku,
                    'quantity_before' => $product->available_quantity,
                    'quantity_changed' => $receivedItem['quantity_received'],
                    'quantity_after' => $product->available_quantity + $receivedItem['quantity_received'],
                    'unit_cost' => $poItem->unit_cost,
                    'total_cost' => $itemValue,
                    'source_type' => 'supplier',
                    'source_reference' => $purchaseOrder->po_number,
                    'destination_bin_id' => $request->destination_bin_id,
                    'performed_by' => $request->received_by,
                    'notes' => $request->notes,
                    'purchase_order_id' => $purchaseOrder->id,
                    'movement_at' => now()
                ]);

                // Update product quantity
                $product->increment('available_quantity', $receivedItem['quantity_received']);

                // Update bin stock
                $this->updateBinStock($request->destination_bin_id, $product->id, $receivedItem['quantity_received'], 'add');

                // Update PO item received quantity
                $poItem->increment('quantity_received', $receivedItem['quantity_received']);
            }

            // Check if all items are received
            $allReceived = $purchaseOrder->items->every(function ($item) {
                return $item->quantity_received >= $item->quantity;
            });

            if ($allReceived) {
                $purchaseOrder->update(['status' => 'received']);
            } else {
                $purchaseOrder->update(['status' => 'partially_received']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock received successfully',
                'data' => [
                    'purchase_order' => $purchaseOrder->fresh(),
                    'received_items' => $request->received_items
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to receive stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock movement log
     * GET /api/inventory/movements
     */
    public function getMovements(Request $request): JsonResponse
    {
        $query = InventoryMovement::with(['user', 'product']);

        if ($request->has('movement_type')) {
            $query->where('movement_type', $request->movement_type);
        }

        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->has('bin_id')) {
            $query->where('destination_bin_id', $request->bin_id)
                  ->orWhere('source_bin_id', $request->bin_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('performed_by')) {
            $query->where('performed_by', $request->performed_by);
        }

        $movements = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $movements
        ]);
    }

    /**
     * Log stock movement
     * POST /api/inventory/movements
     */
    public function logMovement(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'movement_type' => 'required|in:inbound,outbound,transfer,adjustment,return',
            'item_id' => 'required|exists:products,id',
            'quantity_changed' => 'required|integer',
            'source_bin_id' => 'nullable|exists:bins,id',
            'destination_bin_id' => 'nullable|exists:bins,id',
            'performed_by' => 'required|exists:users,id',
            'notes' => 'nullable|string',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::find($request->item_id);
            $quantityBefore = $product->available_quantity;
            $quantityAfter = $quantityBefore + $request->quantity_changed;

            $movement = InventoryMovement::create([
                'movement_type' => $request->movement_type,
                'item_id' => $request->item_id,
                'item_name' => $product->name,
                'item_sku' => $product->sku,
                'quantity_before' => $quantityBefore,
                'quantity_changed' => $request->quantity_changed,
                'quantity_after' => $quantityAfter,
                'source_bin_id' => $request->source_bin_id,
                'destination_bin_id' => $request->destination_bin_id,
                'performed_by' => $request->performed_by,
                'notes' => $request->notes,
                'reference_type' => $request->reference_type,
                'reference_id' => $request->reference_id,
                'movement_at' => now()
            ]);

            // Update product quantity
            $product->update(['available_quantity' => $quantityAfter]);

            return response()->json([
                'success' => true,
                'message' => 'Movement logged successfully',
                'data' => $movement
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to log movement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search movements
     * GET /api/inventory/movements/search
     */
    public function searchMovements(Request $request): JsonResponse
    {
        $query = $request->get('q');
        
        if (!$query) {
            return response()->json([
                'success' => false,
                'message' => 'Search query is required'
            ], 422);
        }

        $movements = InventoryMovement::where('item_name', 'like', "%{$query}%")
            ->orWhere('item_sku', 'like', "%{$query}%")
            ->orWhere('source_reference', 'like', "%{$query}%")
            ->orWhere('destination_reference', 'like', "%{$query}%")
            ->orWhere('notes', 'like', "%{$query}%")
            ->with(['user', 'product'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $movements
        ]);
    }

    /**
     * Filter movements
     * GET /api/inventory/movements/filter
     */
    public function filterMovements(Request $request): JsonResponse
    {
        $query = InventoryMovement::with(['user', 'product']);

        // Apply filters
        if ($request->has('movement_types')) {
            $query->whereIn('movement_type', explode(',', $request->movement_types));
        }

        if ($request->has('date_range')) {
            $dates = explode(',', $request->date_range);
            if (count($dates) === 2) {
                $query->whereBetween('created_at', $dates);
            }
        }

        if ($request->has('min_quantity')) {
            $query->where('quantity_changed', '>=', $request->min_quantity);
        }

        if ($request->has('max_quantity')) {
            $query->where('quantity_changed', '<=', $request->max_quantity);
        }

        if ($request->has('performed_by')) {
            $query->where('performed_by', $request->performed_by);
        }

        $movements = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $movements
        ]);
    }

    /**
     * Get current inventory levels
     * GET /api/inventory/current
     */
    public function getCurrentInventory(): JsonResponse
    {
        $products = Product::select('id', 'name', 'sku', 'category', 'available_quantity', 'minimum_stock_level', 'maximum_stock_level')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $inventorySummary = [
            'total_products' => $products->count(),
            'in_stock' => $products->where('available_quantity', '>', 0)->count(),
            'low_stock' => $products->where('available_quantity', '<=', DB::raw('minimum_stock_level'))->where('available_quantity', '>', 0)->count(),
            'out_of_stock' => $products->where('available_quantity', 0)->count(),
            'overstocked' => $products->where('available_quantity', '>', DB::raw('maximum_stock_level'))->count(),
            'total_value' => $products->sum(function($product) {
                return $product->available_quantity * ($product->unit_price ?? 0);
            })
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $inventorySummary,
                'products' => $products
            ]
        ]);
    }

    /**
     * Get specific product inventory
     * GET /api/inventory/products/{sku}
     */
    public function getProductInventory($sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $recentMovements = InventoryMovement::where('item_id', $product->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $binStock = DB::table('bin_stocks')
            ->where('product_id', $product->id)
            ->join('bins', 'bin_stocks.bin_id', '=', 'bins.id')
            ->select('bins.name as bin_name', 'bin_stocks.quantity')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product,
                'recent_movements' => $recentMovements,
                'bin_stock' => $binStock
            ]
        ]);
    }

    /**
     * Manual inventory adjustment
     * PUT /api/inventory/adjust
     */
    public function adjustInventory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'adjustment_quantity' => 'required|integer',
            'adjustment_reason' => 'required|in:correction,damage,loss,found,audit',
            'bin_id' => 'nullable|exists:bins,id',
            'adjusted_by' => 'required|exists:users,id',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::find($request->product_id);
            $quantityBefore = $product->available_quantity;
            $quantityAfter = $quantityBefore + $request->adjustment_quantity;

            // Prevent negative inventory unless it's a loss/damage
            if ($quantityAfter < 0 && !in_array($request->adjustment_reason, ['loss', 'damage'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Adjustment would result in negative inventory'
                ], 422);
            }

            $movement = InventoryMovement::create([
                'movement_type' => 'adjustment',
                'item_id' => $product->id,
                'item_name' => $product->name,
                'item_sku' => $product->sku,
                'quantity_before' => $quantityBefore,
                'quantity_changed' => $request->adjustment_quantity,
                'quantity_after' => $quantityAfter,
                'destination_bin_id' => $request->bin_id,
                'performed_by' => $request->adjusted_by,
                'notes' => $request->notes . ' - Reason: ' . $request->adjustment_reason,
                'adjustment_reason' => $request->adjustment_reason,
                'movement_at' => now()
            ]);

            // Update product quantity
            $product->update(['available_quantity' => $quantityAfter]);

            // Update bin stock if applicable
            if ($request->bin_id && $request->adjustment_quantity > 0) {
                $this->updateBinStock($request->bin_id, $product->id, $request->adjustment_quantity, 'add');
            }

            return response()->json([
                'success' => true,
                'message' => 'Inventory adjusted successfully',
                'data' => [
                    'movement' => $movement,
                    'product' => $product->fresh()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust inventory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update bin stock
     */
    private function updateBinStock($binId, $productId, $quantity, $operation): void
    {
        $binStock = DB::table('bin_stocks')
            ->where('bin_id', $binId)
            ->where('product_id', $productId)
            ->first();

        if ($binStock) {
            $newQuantity = $operation === 'add' ? $binStock->quantity + $quantity : $binStock->quantity - $quantity;
            DB::table('bin_stocks')
                ->where('bin_id', $binId)
                ->where('product_id', $productId)
                ->update(['quantity' => $newQuantity]);
        } else {
            DB::table('bin_stocks')->insert([
                'bin_id' => $binId,
                'product_id' => $productId,
                'quantity' => $operation === 'add' ? $quantity : 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Get bin stock
     */
    private function getBinStock($binId, $productId): int
    {
        $binStock = DB::table('bin_stocks')
            ->where('bin_id', $binId)
            ->where('product_id', $productId)
            ->first();

        return $binStock ? $binStock->quantity : 0;
    }

    /**
     * Update purchase order status
     */
    private function updatePurchaseOrderStatus($poId, $receivedItems): void
    {
        $purchaseOrder = PurchaseOrder::with('items')->find($poId);
        
        if (!$purchaseOrder) {
            return;
        }

        foreach ($receivedItems as $receivedItem) {
            $poItem = $purchaseOrder->items->where('product_id', $receivedItem['product_id'])->first();
            if ($poItem) {
                $poItem->increment('quantity_received', $receivedItem['quantity']);
            }
        }

        // Check if all items are received
        $allReceived = $purchaseOrder->items->every(function ($item) {
            return $item->quantity_received >= $item->quantity;
        });

        if ($allReceived) {
            $purchaseOrder->update(['status' => 'received']);
        } else {
            $purchaseOrder->update(['status' => 'partially_received']);
        }
    }
} 