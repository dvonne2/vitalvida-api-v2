<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Category;
use App\Models\InventoryHistory;
use App\Models\StockAdjustment;
use App\Models\InventoryCount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventoryReportController extends Controller
{
    /**
     * Get inventory valuation.
     */
    public function inventoryValuation(Request $request): JsonResponse
    {
        $location = $request->get('location');
        $categoryId = $request->get('category_id');

        $query = Item::with(['category', 'supplier']);

        if ($location) {
            $query->where('location', $location);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $items = $query->get();

        $valuation = [
            'total_items' => $items->count(),
            'total_stock_quantity' => $items->sum('stock_quantity'),
            'total_value' => $items->sum(function ($item) {
                return $item->stock_quantity * $item->unit_price;
            }),
            'total_cost' => $items->sum(function ($item) {
                return $item->stock_quantity * ($item->cost_price ?? 0);
            }),
            'potential_profit' => $items->sum(function ($item) {
                return $item->stock_quantity * ($item->unit_price - ($item->cost_price ?? 0));
            }),
            'by_category' => $items->groupBy('category.name')
                ->map(function ($categoryItems) {
                    return [
                        'total_items' => $categoryItems->count(),
                        'total_quantity' => $categoryItems->sum('stock_quantity'),
                        'total_value' => $categoryItems->sum(function ($item) {
                            return $item->stock_quantity * $item->unit_price;
                        }),
                        'potential_profit' => $categoryItems->sum(function ($item) {
                            return $item->stock_quantity * ($item->unit_price - ($item->cost_price ?? 0));
                        })
                    ];
                }),
            'by_location' => $items->groupBy('location')
                ->map(function ($locationItems) {
                    return [
                        'total_items' => $locationItems->count(),
                        'total_quantity' => $locationItems->sum('stock_quantity'),
                        'total_value' => $locationItems->sum(function ($item) {
                            return $item->stock_quantity * $item->unit_price;
                        })
                    ];
                })
        ];

        return response()->json([
            'success' => true,
            'data' => $valuation,
            'filters' => [
                'location' => $location,
                'category_id' => $categoryId
            ]
        ]);
    }

    /**
     * Get stock movement report.
     */
    public function stockMovement(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());
        $reason = $request->get('reason');

        $query = InventoryHistory::with(['item.category', 'user', 'deliveryAgent'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($reason) {
            $query->where('reason', $reason);
        }

        $movements = $query->get();

        $summary = [
            'total_movements' => $movements->count(),
            'total_increases' => $movements->where('change_quantity', '>', 0)->sum('change_quantity'),
            'total_decreases' => abs($movements->where('change_quantity', '<', 0)->sum('change_quantity')),
            'net_change' => $movements->sum('change_quantity'),
            'by_reason' => $movements->groupBy('reason')
                ->map(function ($reasonMovements) {
                    return [
                        'count' => $reasonMovements->count(),
                        'total_change' => $reasonMovements->sum('change_quantity'),
                        'increases' => $reasonMovements->where('change_quantity', '>', 0)->sum('change_quantity'),
                        'decreases' => abs($reasonMovements->where('change_quantity', '<', 0)->sum('change_quantity'))
                    ];
                }),
            'by_item' => $movements->groupBy('item.name')
                ->map(function ($itemMovements) {
                    return [
                        'item_name' => $itemMovements->first()->item->name,
                        'category' => $itemMovements->first()->item->category->name ?? 'Uncategorized',
                        'total_movements' => $itemMovements->count(),
                        'net_change' => $itemMovements->sum('change_quantity'),
                        'last_movement' => $itemMovements->max('created_at')
                    ];
                })
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => $reason
            ]
        ]);
    }

    /**
     * Get low stock alert.
     */
    public function lowStockAlert(Request $request): JsonResponse
    {
        $threshold = $request->get('threshold', 10);
        $location = $request->get('location');

        $query = Item::with(['category', 'supplier'])
            ->where('stock_quantity', '<=', $threshold)
            ->where('is_active', true);

        if ($location) {
            $query->where('location', $location);
        }

        $lowStockItems = $query->get()
            ->map(function ($item) {
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
                    'total_value' => $item->stock_quantity * $item->unit_price,
                    'days_since_last_purchase' => $item->last_purchase_date ? 
                        now()->diffInDays($item->last_purchase_date) : null,
                    'urgency_level' => $this->getUrgencyLevel($item->stock_quantity, $item->reorder_level)
                ];
            })
            ->sortBy('urgency_level');

        $summary = [
            'total_low_stock_items' => $lowStockItems->count(),
            'critical_items' => $lowStockItems->where('urgency_level', 'critical')->count(),
            'high_urgency_items' => $lowStockItems->where('urgency_level', 'high')->count(),
            'medium_urgency_items' => $lowStockItems->where('urgency_level', 'medium')->count(),
            'total_value_at_risk' => $lowStockItems->sum('total_value'),
            'by_category' => $lowStockItems->groupBy('category')
                ->map(function ($items) {
                    return [
                        'count' => $items->count(),
                        'total_value' => $items->sum('total_value')
                    ];
                })
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'items' => $lowStockItems
            ],
            'filters' => [
                'threshold' => $threshold,
                'location' => $location
            ]
        ]);
    }

    /**
     * Get dead stock report.
     */
    public function deadStockReport(Request $request): JsonResponse
    {
        $daysThreshold = $request->get('days_threshold', 90);
        $location = $request->get('location');

        $query = Item::with(['category', 'supplier'])
            ->where('is_active', true)
            ->where(function ($q) use ($daysThreshold) {
                $q->whereNull('last_sale_date')
                  ->orWhere('last_sale_date', '<=', now()->subDays($daysThreshold));
            });

        if ($location) {
            $query->where('location', $location);
        }

        $deadStockItems = $query->get()
            ->map(function ($item) use ($daysThreshold) {
                $daysSinceLastSale = $item->last_sale_date ? 
                    now()->diffInDays($item->last_sale_date) : $daysThreshold;

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'category' => $item->category->name ?? 'Uncategorized',
                    'supplier' => $item->supplier->name ?? 'No Supplier',
                    'current_stock' => $item->stock_quantity,
                    'unit_price' => $item->unit_price,
                    'total_value' => $item->stock_quantity * $item->unit_price,
                    'last_sale_date' => $item->last_sale_date,
                    'days_since_last_sale' => $daysSinceLastSale,
                    'turnover_rate' => $item->getTurnoverRate(),
                    'recommendation' => $this->getDeadStockRecommendation($item, $daysSinceLastSale)
                ];
            })
            ->sortByDesc('total_value');

        $summary = [
            'total_dead_stock_items' => $deadStockItems->count(),
            'total_value_tied_up' => $deadStockItems->sum('total_value'),
            'by_category' => $deadStockItems->groupBy('category')
                ->map(function ($items) {
                    return [
                        'count' => $items->count(),
                        'total_value' => $items->sum('total_value')
                    ];
                }),
            'recommendations' => [
                'discount' => $deadStockItems->where('recommendation', 'discount')->count(),
                'discontinue' => $deadStockItems->where('recommendation', 'discontinue')->count(),
                'transfer' => $deadStockItems->where('recommendation', 'transfer')->count(),
                'promote' => $deadStockItems->where('recommendation', 'promote')->count()
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'items' => $deadStockItems
            ],
            'filters' => [
                'days_threshold' => $daysThreshold,
                'location' => $location
            ]
        ]);
    }

    /**
     * Get inventory history for specific item.
     */
    public function inventoryHistory(int $itemId, Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $item = Item::with(['category', 'supplier'])->findOrFail($itemId);

        $history = InventoryHistory::where('item_id', $itemId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['user', 'deliveryAgent'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'date' => $entry->created_at,
                    'reason' => $entry->reason,
                    'quantity_before' => $entry->quantity_before,
                    'quantity_after' => $entry->quantity_after,
                    'change_quantity' => $entry->change_quantity,
                    'user' => $entry->user->name ?? 'System',
                    'delivery_agent' => $entry->deliveryAgent->name ?? null,
                    'location' => $entry->location
                ];
            });

        $summary = [
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'category' => $item->category->name ?? 'Uncategorized',
                'current_stock' => $item->stock_quantity,
                'unit_price' => $item->unit_price,
                'total_value' => $item->stock_quantity * $item->unit_price
            ],
            'movements' => [
                'total_movements' => $history->count(),
                'total_increases' => $history->where('change_quantity', '>', 0)->sum('change_quantity'),
                'total_decreases' => abs($history->where('change_quantity', '<', 0)->sum('change_quantity')),
                'net_change' => $history->sum('change_quantity')
            ],
            'by_reason' => $history->groupBy('reason')
                ->map(function ($reasonHistory) {
                    return [
                        'count' => $reasonHistory->count(),
                        'total_change' => $reasonHistory->sum('change_quantity')
                    ];
                })
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'history' => $history
            ],
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
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

    /**
     * Get recommendation for dead stock items.
     */
    private function getDeadStockRecommendation(Item $item, int $daysSinceLastSale): string
    {
        if ($daysSinceLastSale > 180) return 'discontinue';
        if ($daysSinceLastSale > 120) return 'discount';
        if ($daysSinceLastSale > 90) return 'promote';
        return 'transfer';
    }
} 