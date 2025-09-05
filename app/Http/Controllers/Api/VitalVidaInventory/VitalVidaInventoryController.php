<?php

namespace App\Http\Controllers\Api\VitalVidaInventory;

use App\Http\Controllers\Controller;
use App\Models\VitalVidaInventory\Product;
use App\Models\VitalVidaInventory\DeliveryAgent;
use App\Models\VitalVidaInventory\StockTransfer;
use App\Models\VitalVidaInventory\AuditFlag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class VitalVidaInventoryController extends Controller
{
    /**
     * Get dashboard overview
     */
    public function dashboard(): JsonResponse
    {
        $totalAgents = DeliveryAgent::active()->count();
        $activeDeliveries = DeliveryAgent::where('status', 'On Delivery')->count();
        $completedToday = StockTransfer::whereDate('completed_at', today())->count();
        $averageRating = DeliveryAgent::active()->avg('rating') ?? 0;

        $recentActivities = $this->getRecentActivities();
        $performanceIndicators = $this->getPerformanceIndicators();

        return response()->json([
            'status' => 'success',
            'data' => [
                'metrics' => [
                    'total_agents' => $totalAgents,
                    'active_deliveries' => $activeDeliveries,
                    'completed_today' => $completedToday,
                    'average_rating' => round($averageRating, 1),
                    'success_rate' => 97.8
                ],
                'recent_activities' => $recentActivities,
                'performance_indicators' => $performanceIndicators
            ]
        ]);
    }

    /**
     * Get items summary
     */
    public function itemsSummary(): JsonResponse
    {
        $totalProducts = Product::active()->count();
        $inStock = Product::active()->where('stock_level', '>', 0)->count();
        $lowStock = Product::lowStock()->count();
        $outOfStock = Product::outOfStock()->count();
        $totalValue = Product::active()->sum('total_value');

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_products' => $totalProducts,
                'in_stock' => $inStock,
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
                'total_value' => $totalValue
            ]
        ]);
    }

    /**
     * Get all items
     */
    public function items(Request $request): JsonResponse
    {
        $query = Product::with('supplier')->active();

        // Apply filters
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('status')) {
            if ($request->status === 'low_stock') {
                $query->lowStock();
            } elseif ($request->status === 'out_of_stock') {
                $query->outOfStock();
            }
        }

        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            });
        }

        $items = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $items
        ]);
    }

    /**
     * Get delivery agents
     */
    public function deliveryAgents(Request $request): JsonResponse
    {
        $query = DeliveryAgent::with(['products', 'auditFlags']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        $agents = $query->paginate($request->get('per_page', 12));

        return response()->json([
            'status' => 'success',
            'data' => $agents
        ]);
    }

    /**
     * Get analytics overview
     */
    public function analyticsOverview(): JsonResponse
    {
        $totalRevenue = 2400000; // ₦2.4M
        $itemsDelivered = 3247;
        $avgDeliveryTime = 2.4; // hours
        $successRate = 97.8;

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_revenue' => $totalRevenue,
                'items_delivered' => $itemsDelivered,
                'avg_delivery_time' => $avgDeliveryTime,
                'success_rate' => $successRate,
                'charts' => $this->getAnalyticsCharts(),
                'geographic_distribution' => $this->getGeographicDistribution(),
                'agent_performance' => $this->getAgentPerformanceData()
            ]
        ]);
    }

    /**
     * Get inventory overview
     */
    public function inventoryOverview(): JsonResponse
    {
        $totalProducts = Product::active()->count();
        $totalStockValue = Product::active()->sum('total_value');
        $lowStockAlerts = Product::lowStock()->count();
        $expiringSoon = Product::expiringSoon()->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_products' => $totalProducts,
                'total_stock_value' => $totalStockValue,
                'low_stock_alerts' => $lowStockAlerts,
                'expiring_soon' => $expiringSoon,
                'categories' => $this->getProductCategories(),
                'alerts' => $this->getInventoryAlerts()
            ]
        ]);
    }

    /**
     * Get stock transfers
     */
    public function stockTransfers(Request $request): JsonResponse
    {
        $query = StockTransfer::with(['product', 'fromAgent', 'toAgent']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $transfers = $query->latest()->paginate($request->get('per_page', 15));

        $summary = [
            'total' => StockTransfer::count(),
            'completed' => StockTransfer::completed()->count(),
            'in_transit' => StockTransfer::inTransit()->count(),
            'pending' => StockTransfer::pending()->count(),
            'failed' => StockTransfer::failed()->count()
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'transfers' => $transfers,
                'summary' => $summary
            ]
        ]);
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities(): array
    {
        return [
            [
                'agent' => 'Adebayo Okonkwo',
                'action' => 'completed delivery to Mrs. Amina Hassan',
                'location' => 'Victoria Island, Lagos',
                'amount' => 8500,
                'timestamp' => '2 mins ago'
            ],
            [
                'agent' => 'Fatima Abdullahi',
                'action' => 'received stock transfer of 25 Hair Conditioner 500ml',
                'location' => 'Ikeja, Lagos',
                'amount' => 62500,
                'timestamp' => '8 mins ago'
            ],
            [
                'agent' => 'Chinedu Okoro',
                'action' => 'flagged discrepancy in Shampoo 250ml count',
                'location' => 'Surulere, Lagos',
                'amount' => 0,
                'timestamp' => '15 mins ago'
            ]
        ];
    }

    /**
     * Get performance indicators
     */
    private function getPerformanceIndicators(): array
    {
        return [
            'delivery_success_rate' => 97.8,
            'avg_delivery_time' => 2.4,
            'stock_accuracy' => 98.7,
            'agent_satisfaction' => 4.9
        ];
    }

    /**
     * Get analytics charts data
     */
    private function getAnalyticsCharts(): array
    {
        return [
            'delivery_trends' => [
                'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'data' => [45, 52, 48, 61, 55, 67, 43]
            ],
            'revenue_trends' => [
                'labels' => ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                'data' => [580000, 620000, 590000, 610000]
            ]
        ];
    }

    /**
     * Get geographic distribution
     */
    private function getGeographicDistribution(): array
    {
        return [
            ['location' => 'Lagos Island', 'deliveries' => 156, 'agents' => 8],
            ['location' => 'Victoria Island', 'deliveries' => 134, 'agents' => 6],
            ['location' => 'Ikeja', 'deliveries' => 98, 'agents' => 5],
            ['location' => 'Surulere', 'deliveries' => 87, 'agents' => 4],
            ['location' => 'Yaba', 'deliveries' => 76, 'agents' => 3]
        ];
    }

    /**
     * Get agent performance data
     */
    private function getAgentPerformanceData(): array
    {
        return [
            [
                'name' => 'Fatima Abdullahi',
                'deliveries' => 156,
                'success_rate' => 98.7,
                'rating' => 4.9,
                'revenue' => 390000
            ],
            [
                'name' => 'Adebayo Okonkwo',
                'deliveries' => 134,
                'success_rate' => 97.8,
                'rating' => 4.8,
                'revenue' => 335000
            ],
            [
                'name' => 'Chinedu Okoro',
                'deliveries' => 128,
                'success_rate' => 96.9,
                'rating' => 4.7,
                'revenue' => 320000
            ]
        ];
    }

    /**
     * Get product categories
     */
    private function getProductCategories(): array
    {
        return [
            ['name' => 'Hair Care', 'count' => 45, 'value' => 1250000],
            ['name' => 'Skin Care', 'count' => 38, 'value' => 980000],
            ['name' => 'Body Care', 'count' => 32, 'value' => 850000],
            ['name' => 'Accessories', 'count' => 28, 'value' => 420000]
        ];
    }

    /**
     * Get inventory alerts
     */
    private function getInventoryAlerts(): array
    {
        return [
            [
                'type' => 'low_stock',
                'product' => 'Hair Conditioner 500ml',
                'current_stock' => 8,
                'min_stock' => 15,
                'priority' => 'high'
            ],
            [
                'type' => 'expiring_soon',
                'product' => 'Shampoo 250ml',
                'expiry_date' => '2025-09-15',
                'days_remaining' => 31,
                'priority' => 'medium'
            ]
        ];
    }
}
