<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Get low stock alerts.
     */
    public function lowStockAlerts(Request $request): JsonResponse
    {
        $threshold = $request->get('threshold', 10);
        $location = $request->get('location');

        $query = Item::with(['category', 'supplier'])
            ->where('stock_quantity', '<=', $threshold)
            ->where('is_active', true);

        if ($location) {
            $query->where('location', $location);
        }

        $lowStockItems = $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'category' => $item->category->name ?? 'Uncategorized',
                'supplier' => $item->supplier->name ?? 'No Supplier',
                'current_stock' => $item->stock_quantity,
                'reorder_level' => $item->reorder_level,
                'reorder_quantity' => $item->reorder_quantity,
                'unit_price' => $item->unit_price,
                'urgency_level' => $this->getUrgencyLevel($item->stock_quantity, $item->reorder_level)
            ];
        });

        $summary = [
            'total_low_stock_items' => $lowStockItems->count(),
            'critical_items' => $lowStockItems->where('urgency_level', 'critical')->count(),
            'high_urgency_items' => $lowStockItems->where('urgency_level', 'high')->count(),
            'medium_urgency_items' => $lowStockItems->where('urgency_level', 'medium')->count()
        ];

        return ApiResponse::success([
            'summary' => $summary,
            'items' => $lowStockItems
        ], 'Low stock alerts retrieved successfully');
    }

    /**
     * Get inventory summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $query = Item::where('is_active', true);

        if ($request->has('delivery_agent_id')) {
            $query->where('delivery_agent_id', $request->delivery_agent_id);
        }

        $items = $query->get();

        $summary = [
            'total_items' => $items->count(),
            'total_stock_quantity' => $items->sum('stock_quantity'),
            'total_value' => $items->sum(function ($item) {
                return $item->stock_quantity * $item->unit_price;
            }),
            'low_stock_items' => $items->where('stock_quantity', '<=', DB::raw('reorder_level'))->count(),
            'out_of_stock_items' => $items->where('stock_quantity', 0)->count(),
            'by_category' => $items->groupBy('category.name')->map(function ($categoryItems) {
                return [
                    'count' => $categoryItems->count(),
                    'total_quantity' => $categoryItems->sum('stock_quantity'),
                    'total_value' => $categoryItems->sum(function ($item) {
                        return $item->stock_quantity * $item->unit_price;
                    })
                ];
            })
        ];

        return ApiResponse::success($summary, 'Inventory summary retrieved successfully');
    }

    /**
     * Get stock movement summary.
     */
    public function stockMovement(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $movements = DB::table('inventory_history')
            ->join('items', 'inventory_history.item_id', '=', 'items.id')
            ->whereBetween('inventory_history.created_at', [$startDate, $endDate])
            ->selectRaw('
                items.name as item_name,
                inventory_history.reason,
                SUM(inventory_history.change_quantity) as total_change,
                COUNT(*) as movement_count
            ')
            ->groupBy('items.name', 'inventory_history.reason')
            ->get();

        $summary = [
            'total_movements' => $movements->sum('movement_count'),
            'total_increases' => $movements->where('total_change', '>', 0)->sum('total_change'),
            'total_decreases' => abs($movements->where('total_change', '<', 0)->sum('total_change')),
            'by_reason' => $movements->groupBy('reason')->map(function ($reasonMovements) {
                return [
                    'count' => $reasonMovements->count(),
                    'total_change' => $reasonMovements->sum('total_change')
                ];
            })
        ];

        return ApiResponse::success([
            'summary' => $summary,
            'movements' => $movements
        ], 'Stock movement summary retrieved successfully');
    }

    /**
     * Get urgency level for low stock items.
     */
    private function getUrgencyLevel(int $currentStock, int $reorderLevel): string
    {
        if ($currentStock == 0) return 'critical';
        if ($currentStock <= $reorderLevel * 0.5) return 'critical';
        if ($currentStock <= $reorderLevel) return 'high';
        if ($currentStock <= $reorderLevel * 1.5) return 'medium';
        return 'low';
    }
} 