<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\Order;
use App\Models\Zobin;
use App\Models\Bin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RegionalController extends Controller
{
    /**
     * Regional performance overview
     * GET /api/inventory-portal/regional/overview
     */
    public function getRegionalOverview(): JsonResponse
    {
        $states = ['Lagos', 'Abuja', 'Kano', 'Port Harcourt', 'Kaduna', 'Enugu'];
        
        $regionalData = collect($states)->map(function($state) {
            // DA statistics
            $totalDAs = DeliveryAgent::where('state', $state)->where('status', 'active')->count();
            $activeDAs = DeliveryAgent::where('state', $state)
                ->where('status', 'active')
                ->where('last_active_at', '>=', now()->subHours(24))
                ->count();

            // Stock statistics
            $stateStock = DeliveryAgent::where('state', $state)
                ->where('status', 'active')
                ->whereHas('zobin')
                ->with('zobin')
                ->get()
                ->sum(function($da) {
                    return $da->zobin->shampoo_count + $da->zobin->pomade_count + $da->zobin->conditioner_count;
                });

            // Order statistics
            $totalOrders = Order::whereHas('deliveryAgent', function($query) use ($state) {
                $query->where('state', $state);
            })->count();

            $deliveredOrders = Order::whereHas('deliveryAgent', function($query) use ($state) {
                $query->where('state', $state);
            })->where('status', 'delivered')->count();

            $successRate = $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 1) : 0;

            // Revenue statistics
            $totalRevenue = Order::whereHas('deliveryAgent', function($query) use ($state) {
                $query->where('state', $state);
            })->where('payment_status', 'paid')->sum('total_amount');

            // Bin statistics
            $totalBins = Bin::where('state', $state)->count();
            $activeBins = Bin::where('state', $state)->where('is_active', true)->count();
            $criticalBins = Bin::where('state', $state)
                ->where('is_active', true)
                ->where('current_stock_count', '<=', 3)
                ->count();

            $utilizationRate = $totalBins > 0 ? round(($activeBins / $totalBins) * 100, 1) : 0;

            return [
                'state' => $state,
                'zones' => $this->getZoneCount($state),
                'stock' => $stateStock,
                'agents' => $totalDAs,
                'active_agents' => $activeDAs,
                'utilization' => $utilizationRate,
                'status' => $this->getRegionalStatus($successRate, $utilizationRate),
                'performance' => [
                    'total_orders' => $totalOrders,
                    'delivered_orders' => $deliveredOrders,
                    'success_rate' => $successRate,
                    'total_revenue' => $totalRevenue
                ],
                'bins' => [
                    'total' => $totalBins,
                    'active' => $activeBins,
                    'critical' => $criticalBins
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'regional_overview' => $regionalData,
                'summary' => [
                    'total_states' => $states->count(),
                    'total_agents' => $regionalData->sum('agents'),
                    'total_stock' => $regionalData->sum('stock'),
                    'total_revenue' => $regionalData->sum('performance.total_revenue'),
                    'avg_success_rate' => $regionalData->avg('performance.success_rate')
                ],
                'last_updated' => now()->toISOString()
            ]
        ]);
    }

    /**
     * State-wise stock levels
     * GET /api/inventory-portal/regional/stock-levels
     */
    public function getStateStockLevels(): JsonResponse
    {
        $stateStockLevels = DB::table('delivery_agents')
            ->join('zobins', 'delivery_agents.id', '=', 'zobins.delivery_agent_id')
            ->where('delivery_agents.status', 'active')
            ->selectRaw('
                delivery_agents.state,
                COUNT(*) as total_das,
                AVG(MIN(zobins.shampoo_count, MIN(zobins.pomade_count, zobins.conditioner_count))) as avg_available_sets,
                SUM(CASE WHEN MIN(zobins.shampoo_count, MIN(zobins.pomade_count, zobins.conditioner_count)) < 3 THEN 1 ELSE 0 END) as critical_stock_das,
                SUM(zobins.shampoo_count) as total_shampoo,
                SUM(zobins.pomade_count) as total_pomade,
                SUM(zobins.conditioner_count) as total_conditioner,
                SUM(zobins.shampoo_count + zobins.pomade_count + zobins.conditioner_count) as total_stock
            ')
            ->groupBy('delivery_agents.state')
            ->orderBy('total_stock', 'desc')
            ->get();

        $stateStockLevels->transform(function ($state) {
            $criticalPercentage = $state->total_das > 0 ? round(($state->critical_stock_das / $state->total_das) * 100, 1) : 0;
            
            $status = 'optimal';
            if ($criticalPercentage > 30) {
                $status = 'critical';
            } elseif ($criticalPercentage > 15) {
                $status = 'warning';
            }

            return [
                'state' => $state->state,
                'total_das' => $state->total_das,
                'avg_available_sets' => round($state->avg_available_sets, 1),
                'critical_stock_das' => $state->critical_stock_das,
                'critical_percentage' => $criticalPercentage,
                'total_stock' => $state->total_stock,
                'stock_breakdown' => [
                    'shampoo' => $state->total_shampoo,
                    'pomade' => $state->total_pomade,
                    'conditioner' => $state->total_conditioner
                ],
                'status' => $status
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'state_stock_levels' => $stateStockLevels,
                'summary' => [
                    'total_states' => $stateStockLevels->count(),
                    'total_das' => $stateStockLevels->sum('total_das'),
                    'total_stock' => $stateStockLevels->sum('total_stock'),
                    'critical_states' => $stateStockLevels->where('status', 'critical')->count(),
                    'warning_states' => $stateStockLevels->where('status', 'warning')->count()
                ],
                'last_updated' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Regional performance trends
     * GET /api/inventory-portal/regional/trends
     */
    public function getRegionalTrends(): JsonResponse
    {
        $weeks = collect();
        for ($i = 3; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();
            
            $weeklyData = DB::table('orders')
                ->join('delivery_agents', 'orders.delivery_agent_id', '=', 'delivery_agents.id')
                ->whereBetween('orders.created_at', [$weekStart, $weekEnd])
                ->selectRaw('
                    delivery_agents.state,
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN orders.status = "delivered" THEN 1 ELSE 0 END) as delivered_orders,
                    SUM(CASE WHEN orders.payment_status = "paid" THEN orders.total_amount ELSE 0 END) as revenue
                ')
                ->groupBy('delivery_agents.state')
                ->get();

            $weeks->push([
                'week' => $weekStart->format('M j'),
                'data' => $weeklyData->map(function($state) {
                    $successRate = $state->total_orders > 0 ? round(($state->delivered_orders / $state->total_orders) * 100, 1) : 0;
                    
                    return [
                        'state' => $state->state,
                        'total_orders' => $state->total_orders,
                        'delivered_orders' => $state->delivered_orders,
                        'success_rate' => $successRate,
                        'revenue' => $state->revenue
                    ];
                })
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'trends' => $weeks,
                'summary' => [
                    'total_weeks' => $weeks->count(),
                    'trending_states' => $this->getTrendingStates($weeks),
                    'improving_states' => $this->getImprovingStates($weeks),
                    'declining_states' => $this->getDecliningStates($weeks)
                ],
                'last_updated' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get zone count for a state
     */
    private function getZoneCount($state): int
    {
        // This would typically come from a zones table
        // For now, return a placeholder based on state
        $zoneMap = [
            'Lagos' => 12,
            'Abuja' => 8,
            'Kano' => 6,
            'Port Harcourt' => 5,
            'Kaduna' => 4,
            'Enugu' => 3
        ];
        
        return $zoneMap[$state] ?? 1;
    }

    /**
     * Get regional status based on performance metrics
     */
    private function getRegionalStatus($successRate, $utilizationRate): string
    {
        if ($successRate >= 90 && $utilizationRate >= 80) {
            return 'excellent';
        } elseif ($successRate >= 75 && $utilizationRate >= 60) {
            return 'optimal';
        } elseif ($successRate >= 60 && $utilizationRate >= 40) {
            return 'warning';
        } else {
            return 'critical';
        }
    }

    /**
     * Get trending states (improving performance)
     */
    private function getTrendingStates($weeks): array
    {
        // This would analyze trends across weeks
        // For now, return placeholder data
        return ['Lagos', 'Abuja'];
    }

    /**
     * Get improving states
     */
    private function getImprovingStates($weeks): array
    {
        // This would compare recent weeks to earlier weeks
        // For now, return placeholder data
        return ['Kano'];
    }

    /**
     * Get declining states
     */
    private function getDecliningStates($weeks): array
    {
        // This would identify states with declining performance
        // For now, return placeholder data
        return [];
    }
}
