<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\AgentPerformanceMetric;
use App\Models\PerformanceMetric;
use App\Models\RegionalPerformance;
use App\Models\Order;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DAPerformanceController extends Controller
{
    /**
     * Get overall DA performance metrics
     */
    public function getOverallMetrics(Request $request)
    {
        try {
            $period = $request->get('period', 'month'); // week, month, quarter, year
            $startDate = $this->getStartDate($period);
            
            $metrics = [
                'total_agents' => DeliveryAgent::count(),
                'active_agents' => DeliveryAgent::where('status', 'active')->count(),
                'total_deliveries' => Delivery::where('created_at', '>=', $startDate)->count(),
                'successful_deliveries' => Delivery::where('status', 'completed')
                    ->where('created_at', '>=', $startDate)
                    ->count(),
                'total_revenue' => Order::where('status', 'completed')
                    ->where('created_at', '>=', $startDate)
                    ->sum('total_amount'),
                'average_delivery_time' => Delivery::where('status', 'completed')
                    ->where('created_at', '>=', $startDate)
                    ->avg(DB::raw('(julianday(updated_at) - julianday(created_at)) * 24 * 60')),
                'customer_satisfaction' => Delivery::where('created_at', '>=', $startDate)
                    ->avg('rating'),
                'on_time_delivery_rate' => $this->calculateOnTimeDeliveryRate($startDate),
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics,
                'period' => $period,
                'start_date' => $startDate->toDateString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch overall metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get DA rankings by various criteria
     */
    public function getRankings(Request $request)
    {
        try {
            $criteria = $request->get('criteria', 'deliveries'); // deliveries, revenue, rating, efficiency
            $limit = $request->get('limit', 10);
            $period = $request->get('period', 'month');
            $startDate = $this->getStartDate($period);

            $rankings = DeliveryAgent::select([
                'delivery_agents.*',
                DB::raw('COUNT(deliveries.id) as total_deliveries'),
                DB::raw('COUNT(CASE WHEN deliveries.status = "completed" THEN 1 END) as successful_deliveries'),
                DB::raw('SUM(orders.total_amount) as total_revenue'),
                DB::raw('AVG(deliveries.rating) as average_rating'),
                DB::raw('AVG((julianday(deliveries.updated_at) - julianday(deliveries.created_at)) * 24 * 60) as avg_delivery_time')
            ])
            ->leftJoin('deliveries', 'delivery_agents.id', '=', 'deliveries.delivery_agent_id')
            ->leftJoin('orders', 'deliveries.order_id', '=', 'orders.id')
            ->where('deliveries.created_at', '>=', $startDate)
            ->groupBy('delivery_agents.id')
            ->orderBy($this->getOrderByColumn($criteria), 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($agent, $index) {
                $agent->rank = $index + 1;
                $agent->success_rate = $agent->total_deliveries > 0 
                    ? round(($agent->successful_deliveries / $agent->total_deliveries) * 100, 2)
                    : 0;
                return $agent;
            });

            return response()->json([
                'success' => true,
                'data' => $rankings,
                'criteria' => $criteria,
                'period' => $period
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rankings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get individual DA performance details
     */
    public function getAgentPerformance(Request $request, $agentId)
    {
        try {
            $agent = DeliveryAgent::findOrFail($agentId);
            $period = $request->get('period', 'month');
            $startDate = $this->getStartDate($period);

            $performance = [
                'agent' => $agent,
                'metrics' => [
                    'total_deliveries' => Delivery::where('delivery_agent_id', $agentId)
                        ->where('created_at', '>=', $startDate)
                        ->count(),
                    'successful_deliveries' => Delivery::where('delivery_agent_id', $agentId)
                        ->where('status', 'completed')
                        ->where('created_at', '>=', $startDate)
                        ->count(),
                    'total_revenue' => Order::whereHas('delivery', function($query) use ($agentId) {
                        $query->where('delivery_agent_id', $agentId);
                    })->where('status', 'completed')
                    ->where('created_at', '>=', $startDate)
                    ->sum('total_amount'),
                    'average_rating' => Delivery::where('delivery_agent_id', $agentId)
                        ->where('created_at', '>=', $startDate)
                        ->avg('rating'),
                    'average_delivery_time' => Delivery::where('delivery_agent_id', $agentId)
                        ->where('status', 'completed')
                        ->where('created_at', '>=', $startDate)
                        ->avg(DB::raw('(julianday(updated_at) - julianday(created_at)) * 24 * 60')),
                    'on_time_deliveries' => Delivery::where('delivery_agent_id', $agentId)
                        ->where('created_at', '>=', $startDate)
                        ->where('delivered_at', '<=', DB::raw('expected_delivery_at'))
                        ->count(),
                ],
                'recent_deliveries' => Delivery::where('delivery_agent_id', $agentId)
                    ->with(['order', 'customer'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get(),
                'performance_trend' => $this->getPerformanceTrend($agentId, $period),
                'rankings' => $this->getAgentRankings($agentId, $criteria = 'deliveries')
            ];

            return response()->json([
                'success' => true,
                'data' => $performance
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch agent performance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance comparison between agents
     */
    public function getPerformanceComparison(Request $request)
    {
        try {
            $agentIds = $request->get('agent_ids', []);
            $criteria = $request->get('criteria', 'deliveries');
            $period = $request->get('period', 'month');
            $startDate = $this->getStartDate($period);

            if (empty($agentIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agent IDs are required'
                ], 400);
            }

            $comparison = DeliveryAgent::whereIn('id', $agentIds)
                ->with(['deliveries' => function($query) use ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }, 'deliveries.order'])
                ->get()
                ->map(function ($agent) use ($startDate, $criteria) {
                    $metrics = $this->calculateAgentMetrics($agent, $startDate);
                    return [
                        'agent' => $agent,
                        'metrics' => $metrics,
                        'rank' => $this->getAgentRank($agent->id, $criteria)
                    ];
                })
                ->sortBy('rank');

            return response()->json([
                'success' => true,
                'data' => $comparison,
                'criteria' => $criteria,
                'period' => $period
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch performance comparison',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get regional performance analysis
     */
    public function getRegionalPerformance(Request $request)
    {
        try {
            $period = $request->get('period', 'month');
            $startDate = $this->getStartDate($period);

            $regionalPerformance = DeliveryAgent::select([
                'delivery_agents.state',
                DB::raw('COUNT(DISTINCT delivery_agents.id) as total_agents'),
                DB::raw('COUNT(deliveries.id) as total_deliveries'),
                DB::raw('COUNT(CASE WHEN deliveries.status = "completed" THEN 1 END) as successful_deliveries'),
                DB::raw('SUM(orders.total_amount) as total_revenue'),
                DB::raw('AVG(deliveries.rating) as average_rating'),
                DB::raw('AVG((julianday(deliveries.updated_at) - julianday(deliveries.created_at)) * 24 * 60) as avg_delivery_time')
            ])
            ->leftJoin('deliveries', 'delivery_agents.id', '=', 'deliveries.delivery_agent_id')
            ->leftJoin('orders', 'deliveries.order_id', '=', 'orders.id')
            ->where('deliveries.created_at', '>=', $startDate)
            ->groupBy('delivery_agents.state')
            ->orderBy('total_revenue', 'desc')
            ->get()
            ->map(function ($region) {
                $region->success_rate = $region->total_deliveries > 0 
                    ? round(($region->successful_deliveries / $region->total_deliveries) * 100, 2)
                    : 0;
                $region->revenue_per_agent = $region->total_agents > 0 
                    ? round($region->total_revenue / $region->total_agents, 2)
                    : 0;
                return $region;
            });

            return response()->json([
                'success' => true,
                'data' => $regionalPerformance,
                'period' => $period
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch regional performance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance trends over time
     */
    public function getPerformanceTrends(Request $request)
    {
        try {
            $period = $request->get('period', 'month');
            $agentId = $request->get('agent_id');
            $metric = $request->get('metric', 'deliveries'); // deliveries, revenue, rating

            $startDate = $this->getStartDate($period);
            $endDate = Carbon::now();

            $trends = [];
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                $periodStart = $currentDate->copy();
                $periodEnd = $this->getPeriodEnd($currentDate, $period);

                $value = $this->getMetricValue($agentId, $metric, $periodStart, $periodEnd);

                $trends[] = [
                    'period' => $periodStart->format('Y-m-d'),
                    'value' => $value
                ];

                $currentDate = $this->getNextPeriod($currentDate, $period);
            }

            return response()->json([
                'success' => true,
                'data' => $trends,
                'metric' => $metric,
                'period' => $period
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch performance trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top performers by category
     */
    public function getTopPerformers(Request $request)
    {
        try {
            $category = $request->get('category', 'deliveries'); // deliveries, revenue, rating, efficiency
            $limit = $request->get('limit', 5);
            $period = $request->get('period', 'month');
            $startDate = $this->getStartDate($period);

            $topPerformers = DeliveryAgent::select([
                'delivery_agents.*',
                DB::raw('COUNT(deliveries.id) as total_deliveries'),
                DB::raw('COUNT(CASE WHEN deliveries.status = "completed" THEN 1 END) as successful_deliveries'),
                DB::raw('SUM(orders.total_amount) as total_revenue'),
                DB::raw('AVG(deliveries.rating) as average_rating'),
                DB::raw('AVG((julianday(deliveries.updated_at) - julianday(deliveries.created_at)) * 24 * 60) as avg_delivery_time')
            ])
            ->leftJoin('deliveries', 'delivery_agents.id', '=', 'deliveries.delivery_agent_id')
            ->leftJoin('orders', 'deliveries.order_id', '=', 'orders.id')
            ->where('deliveries.created_at', '>=', $startDate)
            ->groupBy('delivery_agents.id')
            ->orderBy($this->getOrderByColumn($category), 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($agent, $index) {
                $agent->rank = $index + 1;
                $agent->success_rate = $agent->total_deliveries > 0 
                    ? round(($agent->successful_deliveries / $agent->total_deliveries) * 100, 2)
                    : 0;
                return $agent;
            });

            return response()->json([
                'success' => true,
                'data' => $topPerformers,
                'category' => $category,
                'period' => $period
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch top performers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance insights and recommendations
     */
    public function getPerformanceInsights(Request $request)
    {
        try {
            $period = $request->get('period', 'month');
            $startDate = $this->getStartDate($period);

            $insights = [
                'best_performing_agent' => $this->getBestPerformingAgent($startDate),
                'most_improved_agent' => $this->getMostImprovedAgent($startDate),
                'areas_for_improvement' => $this->getAreasForImprovement($startDate),
                'performance_alerts' => $this->getPerformanceAlerts($startDate),
                'recommendations' => $this->getPerformanceRecommendations($startDate)
            ];

            return response()->json([
                'success' => true,
                'data' => $insights,
                'period' => $period
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch performance insights',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper methods

    private function getStartDate($period)
    {
        return match($period) {
            'week' => Carbon::now()->subWeek(),
            'month' => Carbon::now()->subMonth(),
            'quarter' => Carbon::now()->subQuarter(),
            'year' => Carbon::now()->subYear(),
            default => Carbon::now()->subMonth()
        };
    }

    private function getOrderByColumn($criteria)
    {
        return match($criteria) {
            'deliveries' => 'total_deliveries',
            'revenue' => 'total_revenue',
            'rating' => 'average_rating',
            'efficiency' => 'avg_delivery_time',
            default => 'total_deliveries'
        };
    }

    private function calculateOnTimeDeliveryRate($startDate)
    {
        $totalDeliveries = Delivery::where('created_at', '>=', $startDate)->count();
        $onTimeDeliveries = Delivery::where('created_at', '>=', $startDate)
            ->where('delivered_at', '<=', DB::raw('expected_delivery_at'))
            ->count();

        return $totalDeliveries > 0 ? round(($onTimeDeliveries / $totalDeliveries) * 100, 2) : 0;
    }

    private function getPerformanceTrend($agentId, $period)
    {
        // Implementation for performance trend calculation
        return [];
    }

    private function getAgentRankings($agentId, $criteria)
    {
        // Implementation for agent rankings
        return [];
    }

    private function calculateAgentMetrics($agent, $startDate)
    {
        // Implementation for calculating agent metrics
        return [];
    }

    private function getAgentRank($agentId, $criteria)
    {
        // Implementation for getting agent rank
        return 0;
    }

    private function getPeriodEnd($date, $period)
    {
        return match($period) {
            'week' => $date->copy()->addWeek(),
            'month' => $date->copy()->addMonth(),
            'quarter' => $date->copy()->addQuarter(),
            'year' => $date->copy()->addYear(),
            default => $date->copy()->addMonth()
        };
    }

    private function getMetricValue($agentId, $metric, $startDate, $endDate)
    {
        $query = Delivery::whereBetween('created_at', [$startDate, $endDate]);
        
        if ($agentId) {
            $query->where('delivery_agent_id', $agentId);
        }

        return match($metric) {
            'deliveries' => $query->count(),
            'revenue' => Order::whereHas('delivery', function($q) use ($query) {
                $q->whereIn('id', $query->pluck('id'));
            })->sum('total_amount'),
            'rating' => $query->avg('rating'),
            default => $query->count()
        };
    }

    private function getNextPeriod($date, $period)
    {
        return match($period) {
            'week' => $date->addWeek(),
            'month' => $date->addMonth(),
            'quarter' => $date->addQuarter(),
            'year' => $date->addYear(),
            default => $date->addMonth()
        };
    }

    private function getBestPerformingAgent($startDate)
    {
        // Implementation for best performing agent
        return null;
    }

    private function getMostImprovedAgent($startDate)
    {
        // Implementation for most improved agent
        return null;
    }

    private function getAreasForImprovement($startDate)
    {
        // Implementation for areas for improvement
        return [];
    }

    private function getPerformanceAlerts($startDate)
    {
        // Implementation for performance alerts
        return [];
    }

    private function getPerformanceRecommendations($startDate)
    {
        // Implementation for performance recommendations
        return [];
    }
} 