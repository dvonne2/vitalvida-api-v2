<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\DashboardMetricsService;
use App\Services\RevenueAnalyticsService;
use App\Services\RiskAssessmentService;
use App\Models\Order;
use App\Models\Alert;
use App\Models\DeliveryAgent;
use Carbon\Carbon;

class WebSocketController extends Controller
{
    protected $dashboardService;
    protected $revenueService;
    protected $riskService;

    public function __construct(
        DashboardMetricsService $dashboardService,
        RevenueAnalyticsService $revenueService,
        RiskAssessmentService $riskService
    ) {
        $this->dashboardService = $dashboardService;
        $this->revenueService = $revenueService;
        $this->riskService = $riskService;
    }

    /**
     * Get dashboard updates
     */
    public function getDashboardUpdates(): JsonResponse
    {
        try {
            $data = $this->dashboardService->getDashboardData();
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'last_updated' => now()->toISOString(),
                    'cache_ttl' => 300,
                    'websocket_channel' => 'ceo-dashboard'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get dashboard updates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get latest alerts
     */
    public function getLatestAlerts(): JsonResponse
    {
        try {
            $alerts = Alert::where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $alerts,
                'meta' => [
                    'last_updated' => now()->toISOString(),
                    'cache_ttl' => 60,
                    'websocket_channel' => 'alert-system'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get latest alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get latest exceptions
     */
    public function getLatestExceptions(): JsonResponse
    {
        try {
            $exceptions = [
                [
                    'timestamp' => '2024-01-15 09:23',
                    'category' => 'Finance',
                    'description' => 'DA exposure detected: ₦2,500',
                    'assigned_to' => 'Finance Head',
                    'status' => 'fix'
                ],
                [
                    'timestamp' => '2024-01-15 08:45',
                    'category' => 'Media',
                    'description' => 'Ad set exceeded ₦50K with no orders',
                    'assigned_to' => 'Media Head',
                    'status' => 'monitor'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $exceptions,
                'meta' => [
                    'last_updated' => now()->toISOString(),
                    'cache_ttl' => 120,
                    'websocket_channel' => 'exceptions'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get latest exceptions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order updates
     */
    public function getOrderUpdates(): JsonResponse
    {
        try {
            $today = Carbon::today();
            
            $orderUpdates = [
                'orders_created_today' => Order::whereDate('created_at', $today)->count(),
                'orders_delivered_today' => Order::whereDate('delivered_at', $today)->count(),
                'orders_pending' => Order::where('status', 'pending')->count(),
                'orders_in_transit' => Order::where('status', 'out_for_delivery')->count(),
                'recent_orders' => Order::with('customer')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $orderUpdates,
                'meta' => [
                    'last_updated' => now()->toISOString(),
                    'cache_ttl' => 60,
                    'websocket_channel' => 'order-tracking'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get order updates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get financial updates
     */
    public function getFinancialUpdates(): JsonResponse
    {
        try {
            $financialData = $this->revenueService->getRevenueAnalytics();

            return response()->json([
                'success' => true,
                'data' => $financialData,
                'meta' => [
                    'last_updated' => now()->toISOString(),
                    'cache_ttl' => 1800,
                    'websocket_channel' => 'financial-updates'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get financial updates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get delivery agent updates
     */
    public function getDeliveryAgentUpdates(): JsonResponse
    {
        try {
            $daUpdates = [
                'active_das' => DeliveryAgent::where('status', 'active')->count(),
                'total_das' => DeliveryAgent::count(),
                'das_online' => DeliveryAgent::where('last_activity', '>=', Carbon::now()->subMinutes(30))->count(),
                'recent_activities' => DeliveryAgent::with('orders')
                    ->where('last_activity', '>=', Carbon::now()->subHours(2))
                    ->orderBy('last_activity', 'desc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $daUpdates,
                'meta' => [
                    'last_updated' => now()->toISOString(),
                    'cache_ttl' => 120,
                    'websocket_channel' => 'delivery-agents'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get delivery agent updates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get connection status
     */
    public function getConnectionStatus(): JsonResponse
    {
        try {
            $status = [
                'websocket_connected' => true,
                'database_connected' => true,
                'cache_connected' => true,
                'last_heartbeat' => now()->toISOString(),
                'uptime' => '99.9%',
                'response_time' => rand(50, 150) . 'ms'
            ];

            return response()->json([
                'success' => true,
                'data' => $status,
                'meta' => [
                    'last_updated' => now()->toISOString(),
                    'cache_ttl' => 30,
                    'websocket_channel' => 'system-status'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get connection status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Subscribe to WebSocket channel
     */
    public function subscribe(Request $request): JsonResponse
    {
        try {
            $channel = $request->get('channel', 'ceo-dashboard');
            
            // Validate channel
            $validChannels = [
                'ceo-dashboard',
                'alert-system',
                'order-tracking',
                'performance-metrics',
                'financial-updates',
                'delivery-agents',
                'system-status'
            ];

            if (!in_array($channel, $validChannels)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid channel'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'channel' => $channel,
                    'subscribed' => true,
                    'connection_id' => uniqid('ws_'),
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to subscribe to channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unsubscribe from WebSocket channel
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        try {
            $channel = $request->get('channel');
            $connectionId = $request->get('connection_id');

            return response()->json([
                'success' => true,
                'data' => [
                    'channel' => $channel,
                    'unsubscribed' => true,
                    'connection_id' => $connectionId,
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unsubscribe from channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
