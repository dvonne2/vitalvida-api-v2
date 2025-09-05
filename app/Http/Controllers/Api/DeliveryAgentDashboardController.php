<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\VitalVidaDeliveryAgent;
use App\Models\VitalVidaProduct;
use App\Models\VitalVidaStockTransfer;

/**
 * WEEK 2 PERFORMANCE OPTIMIZATION: Dashboard Aggregator Controller
 * 
 * Reduces from 3+ API calls to 1 aggregated call
 * Implements idempotent caching for performance
 * Follows enterprise error taxonomy
 */
class DeliveryAgentDashboardController extends Controller
{
    /**
     * Get aggregated dashboard data for delivery agent
     * 
     * @param int $agentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboard($agentId)
    {
        try {
            // Validate agent access (row-level security)
            $user = Auth::user();
            if (!$this->canAccessAgent($user, $agentId)) {
                return $this->errorResponse('PERMISSION_DENIED', 'Access denied to this agent data', 403);
            }

            // Idempotent caching pattern
            $cacheKey = "da_dashboard_{$agentId}_" . now()->format('Y-m-d-H');
            
            $dashboardData = Cache::remember($cacheKey, 300, function() use ($agentId) {
                return $this->aggregateDashboardData($agentId);
            });

            return $this->successResponse($dashboardData, '1.0');

        } catch (\Exception $e) {
            \Log::error('Dashboard aggregation failed', [
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
                'correlation_id' => request()->header('X-Correlation-ID', uniqid())
            ]);

            return $this->errorResponse('SERVER_ERROR', 'Unable to load dashboard', 500);
        }
    }

    /**
     * Aggregate all dashboard data in single operation
     */
    private function aggregateDashboardData($agentId)
    {
        $agent = VitalVidaDeliveryAgent::findOrFail($agentId);

        return [
            'profile' => $this->getAgentProfile($agent),
            'orders' => $this->getTodaysOrders($agent),
            'inventory' => $this->getInventoryStatus($agent),
            'performance' => $this->getPerformanceMetrics($agent)
        ];
    }

    /**
     * Get agent profile data
     */
    private function getAgentProfile($agent)
    {
        return [
            'version' => '1.0',
            'id' => $agent->id,
            'name' => $agent->name,
            'zone' => $agent->zone ?? 'Unassigned',
            'performance' => [
                'deliveries_today' => $agent->deliveries_today ?? 0,
                'success_rate' => $agent->success_rate ?? 0,
                'rating' => $agent->rating ?? 0
            ],
            'inventory' => [
                'total_items' => $agent->products()->count(),
                'low_stock_alerts' => $agent->products()->where('stock_level', '<', 10)->count()
            ],
            'orders' => [] // Will be populated by getTodaysOrders
        ];
    }

    /**
     * Get today's orders for agent
     */
    private function getTodaysOrders($agent)
    {
        return $agent->orders()
            ->whereDate('created_at', today())
            ->with('items')
            ->get()
            ->map(function($order) {
                return [
                    'id' => $order->id,
                    'customer_name' => $order->customer_name,
                    'delivery_address' => $order->delivery_address,
                    'status' => $order->status,
                    'items' => $order->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'product_name' => $item->product_name,
                            'quantity' => $item->quantity,
                            'price' => $item->price
                        ];
                    }),
                    'created_at' => $order->created_at->toISOString(),
                    'delivery_time' => $order->delivery_time?->toISOString()
                ];
            });
    }

    /**
     * Get inventory status
     */
    private function getInventoryStatus($agent)
    {
        return $agent->products()
            ->select('id', 'name', 'stock_level', 'min_stock_level', 'price')
            ->get()
            ->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'stock_level' => $product->stock_level,
                    'is_low_stock' => $product->stock_level < $product->min_stock_level,
                    'price' => $product->price
                ];
            });
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics($agent)
    {
        return [
            'daily_metrics' => [
                'deliveries_completed' => $agent->deliveries_today ?? 0,
                'revenue_generated' => $agent->revenue_today ?? 0,
                'customer_rating' => $agent->rating ?? 0
            ],
            'weekly_trends' => [
                'delivery_trend' => $this->getWeeklyDeliveryTrend($agent),
                'performance_trend' => $this->getWeeklyPerformanceTrend($agent)
            ],
            'alerts' => $this->getAgentAlerts($agent)
        ];
    }

    /**
     * Get weekly delivery trend
     */
    private function getWeeklyDeliveryTrend($agent)
    {
        // Simplified - in production, query actual delivery data
        return [
            ['day' => 'Mon', 'deliveries' => 12],
            ['day' => 'Tue', 'deliveries' => 15],
            ['day' => 'Wed', 'deliveries' => 18],
            ['day' => 'Thu', 'deliveries' => 14],
            ['day' => 'Fri', 'deliveries' => 20],
            ['day' => 'Sat', 'deliveries' => 16],
            ['day' => 'Sun', 'deliveries' => 10]
        ];
    }

    /**
     * Get weekly performance trend
     */
    private function getWeeklyPerformanceTrend($agent)
    {
        return [
            'success_rate_trend' => 95.2,
            'customer_satisfaction' => 4.7,
            'on_time_delivery' => 92.1
        ];
    }

    /**
     * Get agent-specific alerts
     */
    private function getAgentAlerts($agent)
    {
        $alerts = [];

        // Low stock alerts
        $lowStockCount = $agent->products()->where('stock_level', '<', 10)->count();
        if ($lowStockCount > 0) {
            $alerts[] = [
                'type' => 'inventory',
                'severity' => 'medium',
                'message' => "{$lowStockCount} items running low on stock",
                'action_required' => true
            ];
        }

        // Performance alerts
        if (($agent->success_rate ?? 100) < 90) {
            $alerts[] = [
                'type' => 'performance',
                'severity' => 'high',
                'message' => 'Success rate below target (90%)',
                'action_required' => true
            ];
        }

        return $alerts;
    }

    /**
     * Check if user can access agent data (row-level security)
     */
    private function canAccessAgent($user, $agentId)
    {
        // Agents can only access their own data
        if ($user->delivery_agent_id && $user->delivery_agent_id != $agentId) {
            return false;
        }

        // Managers and admins can access all agents
        if (in_array($user->role, ['manager', 'admin', 'superadmin'])) {
            return true;
        }

        return true; // Default allow for now
    }

    /**
     * Standardized success response with versioning
     */
    private function successResponse($data, $version = '1.0')
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'version' => $version,
            'timestamp' => now()->toISOString(),
            'correlation_id' => request()->header('X-Correlation-ID', uniqid())
        ]);
    }

    /**
     * Standardized error response with taxonomy
     */
    private function errorResponse($code, $message, $httpStatus = 400)
    {
        $retryable = in_array($code, ['SERVER_ERROR', 'RATE_LIMITED']);
        
        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'retryable' => $retryable,
            'correlation_id' => request()->header('X-Correlation-ID', uniqid()),
            'timestamp' => now()->toISOString()
        ], $httpStatus);
    }
}
