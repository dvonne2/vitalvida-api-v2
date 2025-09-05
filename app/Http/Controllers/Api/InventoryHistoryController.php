<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryHistory;
use App\Models\Item;
use App\Models\DeliveryAgent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventoryHistoryController extends Controller
{
    /**
     * Display a listing of inventory history.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryHistory::with(['item', 'user', 'deliveryAgent']);

        // Apply filters
        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->has('reason')) {
            $query->where('reason', $request->reason);
        }

        if ($request->has('delivery_agent_id')) {
            $query->where('delivery_agent_id', $request->delivery_agent_id);
        }

        if ($request->has('location')) {
            $query->where('location', $request->location);
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $history = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $history,
            'filters' => [
                'reasons' => InventoryHistory::distinct()->pluck('reason'),
                'locations' => InventoryHistory::distinct()->pluck('location')->filter(),
                'items' => Item::active()->get(['id', 'name', 'sku']),
                'delivery_agents' => DeliveryAgent::active()->get(['id', 'name'])
            ]
        ]);
    }

    /**
     * Get current stock levels.
     */
    public function stockLevels(Request $request): JsonResponse
    {
        $query = Item::with(['category', 'supplier']);

        // Apply filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('location')) {
            $query->where('location', $request->location);
        }

        if ($request->has('low_stock')) {
            $query->lowStock();
        }

        if ($request->has('out_of_stock')) {
            $query->outOfStock();
        }

        $items = $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'category' => $item->category->name ?? 'Uncategorized',
                'supplier' => $item->supplier->name ?? 'No Supplier',
                'location' => $item->location,
                'stock_quantity' => $item->stock_quantity,
                'reorder_level' => $item->reorder_level,
                'unit_price' => $item->unit_price,
                'total_value' => $item->getStockValue(),
                'is_low_stock' => $item->isLowStock(),
                'is_out_of_stock' => $item->isOutOfStock(),
                'turnover_rate' => $item->getTurnoverRate(),
                'days_of_inventory' => $item->getDaysOfInventory(),
                'expiry_status' => $item->getExpiryStatus()
            ];
        });

        $summary = [
            'total_items' => $items->count(),
            'total_value' => $items->sum('total_value'),
            'low_stock_items' => $items->where('is_low_stock', true)->count(),
            'out_of_stock_items' => $items->where('is_out_of_stock', true)->count(),
            'expiring_soon_items' => $items->where('expiry_status', 'expiring_soon')->count(),
            'expired_items' => $items->where('expiry_status', 'expired')->count()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'summary' => $summary
            ]
        ]);
    }

    /**
     * Get stock levels by delivery agent.
     */
    public function stockLevelsByAgent(int $agentId): JsonResponse
    {
        $agent = DeliveryAgent::findOrFail($agentId);
        
        $items = Item::where('location', $agent->location)
            ->with(['category', 'supplier'])
            ->get()
            ->map(function ($item) use ($agent) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'category' => $item->category->name ?? 'Uncategorized',
                    'supplier' => $item->supplier->name ?? 'No Supplier',
                    'stock_quantity' => $item->stock_quantity,
                    'reorder_level' => $item->reorder_level,
                    'unit_price' => $item->unit_price,
                    'total_value' => $item->getStockValue(),
                    'is_low_stock' => $item->isLowStock(),
                    'is_out_of_stock' => $item->isOutOfStock(),
                    'last_movement' => $item->inventoryHistory()
                        ->latest()
                        ->first()
                        ?->created_at
                ];
            });

        $summary = [
            'agent_name' => $agent->name,
            'location' => $agent->location,
            'total_items' => $items->count(),
            'total_value' => $items->sum('total_value'),
            'low_stock_items' => $items->where('is_low_stock', true)->count(),
            'out_of_stock_items' => $items->where('is_out_of_stock', true)->count()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'agent' => $summary,
                'items' => $items
            ]
        ]);
    }

    /**
     * Get stock movements summary.
     */
    public function stockMovements(Request $request): JsonResponse
    {
        $query = InventoryHistory::with(['item', 'user', 'deliveryAgent']);

        // Apply date filter
        $days = $request->get('days', 30);
        $query->where('created_at', '>=', now()->subDays($days));

        $movements = $query->get()->groupBy('reason')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_quantity' => $group->sum('change_quantity'),
                'total_value' => $group->sum(function ($item) {
                    return $item->change_quantity * ($item->item->unit_price ?? 0);
                }),
                'recent_movements' => $group->take(5)->values()
            ];
        });

        $summary = [
            'total_movements' => $query->count(),
            'total_increases' => $query->where('change_quantity', '>', 0)->sum('change_quantity'),
            'total_decreases' => abs($query->where('change_quantity', '<', 0)->sum('change_quantity')),
            'total_value_change' => $query->get()->sum(function ($item) {
                return $item->change_quantity * ($item->item->unit_price ?? 0);
            })
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'movements_by_reason' => $movements
            ]
        ]);
    }

    /**
     * Get item-specific movements.
     */
    public function itemMovements(int $itemId, Request $request): JsonResponse
    {
        $item = Item::findOrFail($itemId);
        
        $query = $item->inventoryHistory()
            ->with(['user', 'deliveryAgent']);

        // Apply date filter
        $days = $request->get('days', 30);
        $query->where('created_at', '>=', now()->subDays($days));

        $movements = $query->orderBy('created_at', 'desc')->paginate(20);

        $summary = [
            'item_name' => $item->name,
            'sku' => $item->sku,
            'current_stock' => $item->stock_quantity,
            'total_movements' => $query->count(),
            'total_increases' => $query->where('change_quantity', '>', 0)->sum('change_quantity'),
            'total_decreases' => abs($query->where('change_quantity', '<', 0)->sum('change_quantity')),
            'turnover_rate' => $item->getTurnoverRate($days),
            'days_of_inventory' => $item->getDaysOfInventory()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'movements' => $movements
            ]
        ]);
    }
} 