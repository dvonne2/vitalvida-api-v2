<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\TransferOrder;
use App\Models\StockAdjustment;
use App\Models\PerformanceMetric;
use App\Models\DeliveryAgent;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard overview.
     */
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $dateRange = $request->get('date_range', '30'); // days

        $overview = [
            'total_sales' => $this->getTotalSales($dateRange, $user),
            'total_revenue' => $this->getTotalRevenue($dateRange, $user),
            'total_orders' => $this->getTotalOrders($dateRange, $user),
            'total_items' => $this->getTotalItems($user),
            'low_stock_items' => $this->getLowStockItems($user),
            'pending_orders' => $this->getPendingOrders($user),
            'recent_activity' => $this->getRecentActivity($user),
            'performance_metrics' => $this->getPerformanceMetrics($user, $dateRange)
        ];

        return ApiResponse::success($overview, 'Dashboard overview retrieved successfully');
    }

    /**
     * Get dashboard metrics.
     */
    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();
        $dateRange = $request->get('date_range', '30');

        $metrics = [
            'sales_metrics' => $this->getSalesMetrics($dateRange, $user),
            'inventory_metrics' => $this->getInventoryMetrics($user),
            'performance_metrics' => $this->getDetailedPerformanceMetrics($user, $dateRange),
            'financial_metrics' => $this->getFinancialMetrics($dateRange, $user)
        ];

        return ApiResponse::success($metrics, 'Dashboard metrics retrieved successfully');
    }

    /**
     * Get dashboard charts data.
     */
    public function charts(Request $request): JsonResponse
    {
        $user = $request->user();
        $dateRange = $request->get('date_range', '30');

        $charts = [
            'sales_trend' => $this->getSalesTrend($dateRange, $user),
            'revenue_trend' => $this->getRevenueTrend($dateRange, $user),
            'top_selling_items' => $this->getTopSellingItems($dateRange, $user),
            'category_performance' => $this->getCategoryPerformance($dateRange, $user),
            'stock_levels' => $this->getStockLevelsChart($user),
            'performance_trend' => $this->getPerformanceTrend($dateRange, $user)
        ];

        return ApiResponse::success($charts, 'Dashboard charts data retrieved successfully');
    }

    /**
     * Get dashboard notifications.
     */
    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->get('limit', 10);

        $notifications = [
            'low_stock_alerts' => $this->getLowStockAlerts($user),
            'pending_approvals' => $this->getPendingApprovals($user),
            'recent_activities' => $this->getRecentActivities($user, $limit),
            'system_alerts' => $this->getSystemAlerts($user)
        ];

        return ApiResponse::success($notifications, 'Dashboard notifications retrieved successfully');
    }

    // Helper methods for overview
    private function getTotalSales(int $days, $user): int
    {
        $query = Sale::where('created_at', '>=', now()->subDays($days));
        
        if ($user->delivery_agent_id) {
            $query->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        return $query->count();
    }

    private function getTotalRevenue(int $days, $user): float
    {
        $query = Sale::where('created_at', '>=', now()->subDays($days));
        
        if ($user->delivery_agent_id) {
            $query->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        return $query->sum('total');
    }

    private function getTotalOrders(int $days, $user): int
    {
        $query = PurchaseOrder::where('created_at', '>=', now()->subDays($days));
        
        if ($user->delivery_agent_id) {
            $query->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        return $query->count();
    }

    private function getTotalItems($user): int
    {
        $query = Item::where('is_active', true);
        
        if ($user->delivery_agent_id) {
            $query->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        return $query->count();
    }

    private function getLowStockItems($user): int
    {
        $query = Item::where('stock_quantity', '<=', DB::raw('reorder_level'))
                    ->where('is_active', true);
        
        if ($user->delivery_agent_id) {
            $query->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        return $query->count();
    }

    private function getPendingOrders($user): int
    {
        $query = PurchaseOrder::where('status', 'pending');
        
        if ($user->delivery_agent_id) {
            $query->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        return $query->count();
    }

    private function getRecentActivity($user): array
    {
        $activities = [];
        
        // Recent sales
        $recentSales = Sale::with(['customer', 'deliveryAgent'])
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        foreach ($recentSales as $sale) {
            $activities[] = [
                'type' => 'sale',
                'title' => 'New sale: ' . $sale->sale_number,
                'description' => '₦' . number_format($sale->total, 2) . ' - ' . $sale->customer->name,
                'timestamp' => $sale->created_at,
                'data' => $sale
            ];
        }
        
        // Recent stock adjustments
        $recentAdjustments = StockAdjustment::with(['item', 'deliveryAgent'])
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        foreach ($recentAdjustments as $adjustment) {
            $activities[] = [
                'type' => 'stock_adjustment',
                'title' => 'Stock adjustment: ' . $adjustment->reference_number,
                'description' => $adjustment->item->name . ' - ' . $adjustment->quantity,
                'timestamp' => $adjustment->created_at,
                'data' => $adjustment
            ];
        }
        
        // Sort by timestamp
        usort($activities, function($a, $b) {
            return $b['timestamp']->compare($a['timestamp']);
        });
        
        return array_slice($activities, 0, 10);
    }

    private function getPerformanceMetrics($user, int $days): array
    {
        if (!$user->delivery_agent_id) {
            return [];
        }
        
        $metrics = PerformanceMetric::where('delivery_agent_id', $user->delivery_agent_id)
            ->where('date', '>=', now()->subDays($days))
            ->selectRaw('
                AVG(delivery_rate) as avg_delivery_rate,
                AVG(otp_success_rate) as avg_otp_success_rate,
                AVG(stock_accuracy) as avg_stock_accuracy,
                SUM(sales_amount) as total_sales_amount,
                SUM(orders_completed) as total_orders_completed
            ')
            ->first();
        
        return [
            'delivery_rate' => round($metrics->avg_delivery_rate ?? 0, 2),
            'otp_success_rate' => round($metrics->avg_otp_success_rate ?? 0, 2),
            'stock_accuracy' => round($metrics->avg_stock_accuracy ?? 0, 2),
            'sales_amount' => $metrics->total_sales_amount ?? 0,
            'orders_completed' => $metrics->total_orders_completed ?? 0
        ];
    }

    // Helper methods for metrics
    private function getSalesMetrics(int $days, $user): array
    {
        $sales = Sale::where('created_at', '>=', now()->subDays($days));
        
        if ($user->delivery_agent_id) {
            $sales->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        $totalSales = $sales->count();
        $totalRevenue = $sales->sum('total');
        $avgOrderValue = $totalSales > 0 ? $totalRevenue / $totalSales : 0;
        
        return [
            'total_sales' => $totalSales,
            'total_revenue' => $totalRevenue,
            'average_order_value' => round($avgOrderValue, 2),
            'verified_sales' => $sales->where('otp_verified', true)->count(),
            'pending_sales' => $sales->where('otp_verified', false)->count()
        ];
    }

    private function getInventoryMetrics($user): array
    {
        $items = Item::where('is_active', true);
        
        if ($user->delivery_agent_id) {
            $items->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        $totalItems = $items->count();
        $lowStockItems = $items->where('stock_quantity', '<=', DB::raw('reorder_level'))->count();
        $outOfStockItems = $items->where('stock_quantity', 0)->count();
        $totalValue = $items->sum(DB::raw('stock_quantity * unit_price'));
        
        return [
            'total_items' => $totalItems,
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
            'total_inventory_value' => $totalValue,
            'stock_health_percentage' => $totalItems > 0 ? round((($totalItems - $lowStockItems) / $totalItems) * 100, 2) : 0
        ];
    }

    private function getDetailedPerformanceMetrics($user, int $days): array
    {
        if (!$user->delivery_agent_id) {
            return [];
        }
        
        $metrics = PerformanceMetric::where('delivery_agent_id', $user->delivery_agent_id)
            ->where('date', '>=', now()->subDays($days))
            ->get();
        
        return [
            'delivery_rate' => $metrics->avg('delivery_rate'),
            'otp_success_rate' => $metrics->avg('otp_success_rate'),
            'stock_accuracy' => $metrics->avg('stock_accuracy'),
            'customer_satisfaction' => $metrics->avg('customer_satisfaction'),
            'total_orders_completed' => $metrics->sum('orders_completed'),
            'total_sales_amount' => $metrics->sum('sales_amount'),
            'average_delivery_time' => $metrics->avg('delivery_time_avg'),
            'returns_count' => $metrics->sum('returns_count'),
            'complaints_count' => $metrics->sum('complaints_count')
        ];
    }

    private function getFinancialMetrics(int $days, $user): array
    {
        $sales = Sale::where('created_at', '>=', now()->subDays($days));
        
        if ($user->delivery_agent_id) {
            $sales->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        $totalRevenue = $sales->sum('total');
        $totalTax = $sales->sum('tax_amount');
        $totalDiscount = $sales->sum('discount_amount');
        $netRevenue = $totalRevenue - $totalTax + $totalDiscount;
        
        return [
            'total_revenue' => $totalRevenue,
            'total_tax' => $totalTax,
            'total_discounts' => $totalDiscount,
            'net_revenue' => $netRevenue,
            'average_daily_revenue' => round($netRevenue / $days, 2)
        ];
    }

    // Helper methods for charts
    private function getSalesTrend(int $days, $user): array
    {
        $sales = Sale::where('created_at', '>=', now()->subDays($days));
        
        if ($user->delivery_agent_id) {
            $sales->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        return $sales->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count
                ];
            })
            ->toArray();
    }

    private function getRevenueTrend(int $days, $user): array
    {
        $sales = Sale::where('created_at', '>=', now()->subDays($days));
        
        if ($user->delivery_agent_id) {
            $sales->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        return $sales->selectRaw('DATE(created_at) as date, SUM(total) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'revenue' => $item->revenue
                ];
            })
            ->toArray();
    }

    private function getTopSellingItems(int $days, $user): array
    {
        $sales = Sale::where('created_at', '>=', now()->subDays($days));
        
        if ($user->delivery_agent_id) {
            $sales->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        return $sales->with(['items.item'])
            ->get()
            ->flatMap(function ($sale) {
                return $sale->items;
            })
            ->groupBy('item_id')
            ->map(function ($items) {
                $item = $items->first()->item;
                return [
                    'item_name' => $item->name,
                    'total_quantity' => $items->sum('quantity'),
                    'total_revenue' => $items->sum('total')
                ];
            })
            ->sortByDesc('total_revenue')
            ->take(10)
            ->values()
            ->toArray();
    }

    private function getCategoryPerformance(int $days, $user): array
    {
        $sales = Sale::where('created_at', '>=', now()->subDays($days));
        
        if ($user->delivery_agent_id) {
            $sales->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        return $sales->with(['items.item.category'])
            ->get()
            ->flatMap(function ($sale) {
                return $sale->items;
            })
            ->groupBy('item.category.name')
            ->map(function ($items, $category) {
                return [
                    'category' => $category,
                    'total_quantity' => $items->sum('quantity'),
                    'total_revenue' => $items->sum('total')
                ];
            })
            ->sortByDesc('total_revenue')
            ->values()
            ->toArray();
    }

    private function getStockLevelsChart($user): array
    {
        $items = Item::where('is_active', true);
        
        if ($user->delivery_agent_id) {
            $items->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        return $items->select('name', 'stock_quantity', 'reorder_level')
            ->orderBy('stock_quantity', 'asc')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'item_name' => $item->name,
                    'stock_quantity' => $item->stock_quantity,
                    'reorder_level' => $item->reorder_level,
                    'status' => $item->stock_quantity <= $item->reorder_level ? 'low' : 'normal'
                ];
            })
            ->toArray();
    }

    private function getPerformanceTrend(int $days, $user): array
    {
        if (!$user->delivery_agent_id) {
            return [];
        }
        
        return PerformanceMetric::where('delivery_agent_id', $user->delivery_agent_id)
            ->where('date', '>=', now()->subDays($days))
            ->select('date', 'delivery_rate', 'otp_success_rate', 'stock_accuracy')
            ->orderBy('date')
            ->get()
            ->map(function ($metric) {
                return [
                    'date' => $metric->date,
                    'delivery_rate' => $metric->delivery_rate,
                    'otp_success_rate' => $metric->otp_success_rate,
                    'stock_accuracy' => $metric->stock_accuracy
                ];
            })
            ->toArray();
    }

    // Helper methods for notifications
    private function getLowStockAlerts($user): array
    {
        $items = Item::where('stock_quantity', '<=', DB::raw('reorder_level'))
                    ->where('is_active', true);
        
        if ($user->delivery_agent_id) {
            $items->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        return $items->select('name', 'stock_quantity', 'reorder_level')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'type' => 'low_stock',
                    'title' => 'Low stock alert',
                    'message' => "{$item->name} is running low on stock ({$item->stock_quantity} remaining)",
                    'severity' => $item->stock_quantity == 0 ? 'critical' : 'warning'
                ];
            })
            ->toArray();
    }

    private function getPendingApprovals($user): array
    {
        $approvals = [];
        
        // Pending stock adjustments
        $pendingAdjustments = StockAdjustment::where('status', 'pending');
        if ($user->delivery_agent_id) {
            $pendingAdjustments->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        foreach ($pendingAdjustments->limit(5)->get() as $adjustment) {
            $approvals[] = [
                'type' => 'stock_adjustment',
                'title' => 'Pending stock adjustment',
                'message' => "Stock adjustment {$adjustment->reference_number} requires approval",
                'id' => $adjustment->id
            ];
        }
        
        // Pending inventory counts
        $pendingCounts = InventoryCount::where('status', 'completed');
        if ($user->delivery_agent_id) {
            $pendingCounts->where('delivery_agent_id', $user->delivery_agent_id);
        }
        
        foreach ($pendingCounts->limit(5)->get() as $count) {
            $approvals[] = [
                'type' => 'inventory_count',
                'title' => 'Pending inventory count approval',
                'message' => "Inventory count {$count->count_number} requires approval",
                'id' => $count->id
            ];
        }
        
        return $approvals;
    }

    private function getRecentActivities($user, int $limit): array
    {
        $activities = [];
        
        // Recent sales
        $recentSales = Sale::with(['customer'])
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
        
        foreach ($recentSales as $sale) {
            $activities[] = [
                'type' => 'sale',
                'title' => 'New sale completed',
                'message' => "Sale {$sale->sale_number} for ₦" . number_format($sale->total, 2),
                'timestamp' => $sale->created_at,
                'status' => $sale->otp_verified ? 'verified' : 'pending'
            ];
        }
        
        return $activities;
    }

    private function getSystemAlerts($user): array
    {
        $alerts = [];
        
        // Check for system maintenance
        if (now()->hour >= 2 && now()->hour <= 4) {
            $alerts[] = [
                'type' => 'system',
                'title' => 'System Maintenance',
                'message' => 'Scheduled maintenance window is active',
                'severity' => 'info'
            ];
        }
        
        // Check for high error rates
        $recentErrors = DB::table('logs')
            ->where('level', 'error')
            ->where('created_at', '>=', now()->subHours(1))
            ->count();
        
        if ($recentErrors > 10) {
            $alerts[] = [
                'type' => 'system',
                'title' => 'High Error Rate',
                'message' => 'System experiencing high error rates',
                'severity' => 'warning'
            ];
        }
        
        return $alerts;
    }
}
