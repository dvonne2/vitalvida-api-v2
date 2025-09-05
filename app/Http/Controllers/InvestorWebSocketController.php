<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Investor;
use App\Models\Order;
use App\Models\Revenue;
use App\Models\FinancialStatement;
use App\Models\InvestorDocument;
use Carbon\Carbon;

class InvestorWebSocketController extends Controller
{
    /**
     * Connect to WebSocket for specific investor role
     */
    public function connect(Request $request, string $investor_role): JsonResponse
    {
        try {
            $investor = $request->user();

            if (!$investor instanceof Investor || $investor->role !== $investor_role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Role mismatch.'
                ], 403);
            }

            // Generate WebSocket connection token
            $connectionToken = uniqid('ws_', true);
            $channelName = "investor-dashboard-{$investor_role}";

            return response()->json([
                'success' => true,
                'data' => [
                    'connection_token' => $connectionToken,
                    'channel_name' => $channelName,
                    'websocket_url' => config('app.websocket_url', 'ws://localhost:6001'),
                    'connection_expires' => now()->addHours(24)->toISOString(),
                    'supported_events' => [
                        'document_status_changed',
                        'cash_position_updated',
                        'order_volume_changed',
                        'compliance_score_updated',
                        'financial_metrics_updated',
                        'operational_alerts'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to establish WebSocket connection',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Subscribe to specific WebSocket channel
     */
    public function subscribe(Request $request, string $channel): JsonResponse
    {
        try {
            $investor = $request->user();

            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            $allowedChannels = [
                "investor-dashboard-{$investor->role}",
                'document-updates',
                'financial-metrics',
                'operational-alerts'
            ];

            if (!in_array($channel, $allowedChannels)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this channel.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'channel' => $channel,
                    'subscription_id' => uniqid('sub_'),
                    'status' => 'subscribed',
                    'last_message_id' => null,
                    'subscription_expires' => now()->addHours(24)->toISOString()
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
     * Financial metrics real-time stream
     */
    public function financialStream(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();

            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            // Get real-time financial metrics
            $currentRevenue = Revenue::whereDate('date', today())->sum('amount');
            $monthlyRevenue = Revenue::whereMonth('created_at', now()->month)->sum('amount');
            $lastMonthRevenue = Revenue::whereMonth('created_at', now()->subMonth()->month)->sum('amount');
            $revenueGrowth = $lastMonthRevenue > 0 ? (($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;

            $data = [
                'timestamp' => now()->toISOString(),
                'metrics' => [
                    'current_revenue' => $currentRevenue,
                    'monthly_revenue' => $monthlyRevenue,
                    'revenue_growth_percentage' => number_format($revenueGrowth, 2),
                    'cash_position' => 2495000, // From OtunbaControlController
                    'burn_rate' => 500000,
                    'runway_days' => round((2495000 / 500000) * 30)
                ],
                'alerts' => $this->getFinancialAlerts($investor)
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get financial stream',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Operational metrics real-time stream
     */
    public function operationalStream(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();

            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            // Get real-time operational metrics
            $todayOrders = Order::whereDate('created_at', today())->count();
            $activeOrders = Order::whereIn('status', ['pending', 'processing'])->count();
            $completedOrders = Order::where('status', 'completed')->whereDate('created_at', today())->count();

            $data = [
                'timestamp' => now()->toISOString(),
                'metrics' => [
                    'today_orders' => $todayOrders,
                    'active_orders' => $activeOrders,
                    'completed_orders' => $completedOrders,
                    'order_completion_rate' => $todayOrders > 0 ? ($completedOrders / $todayOrders) * 100 : 0,
                    'average_order_value' => $todayOrders > 0 ? Revenue::whereDate('date', today())->sum('amount') / $todayOrders : 0,
                    'system_uptime' => 99.8,
                    'delivery_success_rate' => 99.5
                ],
                'alerts' => $this->getOperationalAlerts($investor)
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get operational stream',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Document updates real-time stream
     */
    public function documentUpdates(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();

            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            // Get recent document updates
            $recentDocuments = InvestorDocument::where('updated_at', '>=', now()->subHours(24))
                ->with('category')
                ->orderBy('updated_at', 'desc')
                ->limit(10)
                ->get();

            $documentUpdates = $recentDocuments->map(function ($document) {
                return [
                    'document_id' => $document->id,
                    'title' => $document->title,
                    'category' => $document->category->name,
                    'status' => $document->status,
                    'updated_at' => $document->updated_at->toISOString(),
                    'updated_by' => $document->uploaded_by
                ];
            });

            $data = [
                'timestamp' => now()->toISOString(),
                'recent_updates' => $documentUpdates,
                'total_documents' => InvestorDocument::count(),
                'completed_documents' => InvestorDocument::where('status', 'complete')->count(),
                'pending_documents' => InvestorDocument::where('status', 'pending')->count(),
                'completion_percentage' => InvestorDocument::count() > 0 ? 
                    (InvestorDocument::where('status', 'complete')->count() / InvestorDocument::count()) * 100 : 0
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get document updates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Growth metrics real-time stream
     */
    public function growthMetrics(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();

            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            // Get growth metrics
            $thisMonthOrders = Order::whereMonth('created_at', now()->month)->count();
            $lastMonthOrders = Order::whereMonth('created_at', now()->subMonth()->month)->count();
            $orderGrowth = $lastMonthOrders > 0 ? (($thisMonthOrders - $lastMonthOrders) / $lastMonthOrders) * 100 : 0;

            $thisMonthRevenue = Revenue::whereMonth('created_at', now()->month)->sum('amount');
            $lastMonthRevenue = Revenue::whereMonth('created_at', now()->subMonth()->month)->sum('amount');
            $revenueGrowth = $lastMonthRevenue > 0 ? (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;

            $data = [
                'timestamp' => now()->toISOString(),
                'metrics' => [
                    'order_growth_percentage' => number_format($orderGrowth, 2),
                    'revenue_growth_percentage' => number_format($revenueGrowth, 2),
                    'customer_acquisition_cost' => 1500,
                    'lifetime_value' => 5250,
                    'ltv_cac_ratio' => 3.5,
                    'retention_rate' => 85.5,
                    'churn_rate' => 14.5,
                    'neil_score' => 8.7
                ],
                'alerts' => $this->getGrowthAlerts($investor)
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get growth metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get financial alerts for investor
     */
    private function getFinancialAlerts(Investor $investor): array
    {
        $alerts = [];

        // Check cash position
        $cashPosition = 2495000;
        if ($cashPosition < 1000000) {
            $alerts[] = [
                'type' => 'cash_position_low',
                'severity' => 'high',
                'message' => 'Cash position below ₦1M threshold',
                'current_value' => $cashPosition,
                'threshold' => 1000000
            ];
        }

        // Check burn rate
        $burnRate = 500000;
        $runwayDays = ($cashPosition / $burnRate) * 30;
        if ($runwayDays < 90) {
            $alerts[] = [
                'type' => 'runway_short',
                'severity' => 'medium',
                'message' => 'Runway below 90 days',
                'current_days' => round($runwayDays),
                'threshold' => 90
            ];
        }

        return $alerts;
    }

    /**
     * Get operational alerts for investor
     */
    private function getOperationalAlerts(Investor $investor): array
    {
        $alerts = [];

        // Check order completion rate
        $todayOrders = Order::whereDate('created_at', today())->count();
        $completedOrders = Order::where('status', 'completed')->whereDate('created_at', today())->count();
        $completionRate = $todayOrders > 0 ? ($completedOrders / $todayOrders) * 100 : 0;

        if ($completionRate < 95) {
            $alerts[] = [
                'type' => 'low_completion_rate',
                'severity' => 'medium',
                'message' => 'Order completion rate below 95%',
                'current_rate' => number_format($completionRate, 1),
                'threshold' => 95
            ];
        }

        return $alerts;
    }

    /**
     * Get growth alerts for investor
     */
    private function getGrowthAlerts(Investor $investor): array
    {
        $alerts = [];

        // Check growth rate
        $thisMonthOrders = Order::whereMonth('created_at', now()->month)->count();
        $lastMonthOrders = Order::whereMonth('created_at', now()->subMonth()->month)->count();
        $orderGrowth = $lastMonthOrders > 0 ? (($thisMonthOrders - $lastMonthOrders) / $lastMonthOrders) * 100 : 0;

        if ($orderGrowth < 10) {
            $alerts[] = [
                'type' => 'low_growth_rate',
                'severity' => 'medium',
                'message' => 'Order growth rate below 10%',
                'current_rate' => number_format($orderGrowth, 1),
                'threshold' => 10
            ];
        }

        return $alerts;
    }
}
