<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\DeliveryAgent;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryReceiveController extends Controller
{
    /**
     * Get inventory movements
     */
    public function movements(): JsonResponse
    {
        try {
            $movements = InventoryMovement::with(['user'])
                ->orderBy('movement_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($movement) {
                    return [
                        'id' => $movement->id,
                        'item_id' => $movement->item_id,
                        'item_name' => $movement->item_name ?? 'Unknown Item',
                        'movement_type' => $movement->movement_type,
                        'quantity' => $movement->quantity_changed,
                        'unit_cost' => 0, // Will be calculated from item cost_price
                        'total_value' => 0, // Will be calculated
                        'reference' => $movement->source_reference ?? $movement->order_number,
                        'notes' => $movement->notes,
                        'created_at' => $movement->movement_at ? $movement->movement_at->toISOString() : $movement->created_at->toISOString(),
                        'updated_at' => $movement->updated_at->toISOString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $movements,
                'message' => 'Inventory movements retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve inventory movements: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Receive inventory
     */
    public function receive(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'item_id' => 'required|exists:items,id',
                'quantity' => 'required|numeric|min:1',
                'unit_cost' => 'required|numeric|min:0',
                'movement_type' => 'required|in:inbound,outbound,adjustment',
                'reference' => 'required|string|max:255',
                'notes' => 'nullable|string',
                'supplier_id' => 'nullable|exists:suppliers,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $totalValue = $data['quantity'] * $data['unit_cost'];

            // Get the item to get its current stock
            $item = Item::find($data['item_id']);
            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found'
                ], 404);
            }

            $quantityBefore = $item->stock_quantity;
            $quantityAfter = $data['movement_type'] === 'inbound' 
                ? $quantityBefore + $data['quantity']
                : $quantityBefore - $data['quantity'];

            $movement = InventoryMovement::create([
                'item_id' => $data['item_id'],
                'item_name' => $item->name,
                'item_sku' => $item->sku,
                'movement_type' => $data['movement_type'],
                'quantity_before' => $quantityBefore,
                'quantity_changed' => $data['quantity'],
                'quantity_after' => $quantityAfter,
                'source_type' => 'manual',
                'source_reference' => $data['reference'],
                'source_details' => [
                    'unit_cost' => $data['unit_cost'],
                    'total_value' => $totalValue,
                    'supplier_id' => $data['supplier_id'] ?? null,
                ],
                'user_id' => auth()->id(),
                'performed_by' => auth()->user()->name ?? 'System',
                'notes' => $data['notes'] ?? null,
                'movement_at' => now(),
            ]);

            // Update item stock
            $item->stock_quantity = $quantityAfter;
            $item->save();

            return response()->json([
                'success' => true,
                'data' => $movement,
                'message' => 'Inventory movement created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create inventory movement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload inventory file
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:csv,xlsx,xls|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('inventory_uploads', $filename);

            // TODO: Process the uploaded file
            // This would typically involve reading the file and creating inventory movements

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'filename' => $filename,
                    'processed' => false // Will be true after processing
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function dashboardStats(): JsonResponse
    {
        try {
            $stats = [
                'total_agents' => DB::table('users')->where('is_agent', true)->count(),
                'active_deliveries' => DB::table('deliveries')->where('status', 'in_progress')->count(),
                'completed_today' => DB::table('deliveries')
                    ->whereDate('updated_at', today())
                    ->where('status', 'completed')
                    ->count(),
                'average_rating' => DB::table('deliveries')->avg('rating') ?? 4.5,
                'total_items' => Item::count(),
                'low_stock_items' => Item::where('stock_quantity', '<=', DB::raw('reorder_level'))->count(),
                'total_value' => Item::sum(DB::raw('stock_quantity * cost_price')),
                'recent_movements' => InventoryMovement::count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Dashboard statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory analytics
     */
    public function analytics(): JsonResponse
    {
        try {
            $analytics = [
                'movements_by_type' => InventoryMovement::select('movement_type', DB::raw('count(*) as count'))
                    ->groupBy('movement_type')
                    ->get(),
                'top_items' => Item::orderBy('stock_quantity', 'desc')
                    ->limit(10)
                    ->get(['id', 'name', 'stock_quantity', 'cost_price']),
                'low_stock_alerts' => Item::where('stock_quantity', '<=', DB::raw('reorder_level'))
                    ->get(['id', 'name', 'stock_quantity', 'reorder_level']),
                'recent_activity' => InventoryMovement::orderBy('movement_at', 'desc')
                    ->limit(20)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics,
                'message' => 'Analytics data retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve analytics: ' . $e->getMessage()
            ], 500);
        }
    }
}
