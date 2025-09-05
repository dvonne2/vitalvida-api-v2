<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\DeliveryAgent;
use App\Models\Product;
use App\Models\Bin;
use App\Models\InventoryMovement;
use App\Models\AgentActivityLog;
use App\Models\ImDailyLog;
use App\Models\SystemRecommendation;
use App\Models\StockPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Main dashboard metrics
     * GET /api/dashboard/overview
     */
    public function getOverview(): JsonResponse
    {
        $today = now()->toDateString();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        // Order metrics
        $orderMetrics = [
            'total_orders' => Order::count(),
            'orders_today' => Order::whereDate('created_at', $today)->count(),
            'orders_this_week' => Order::whereBetween('created_at', [$thisWeek, now()])->count(),
            'orders_this_month' => Order::whereBetween('created_at', [$thisMonth, now()])->count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'processing_orders' => Order::whereIn('status', ['confirmed', 'processing'])->count(),
            'delivered_orders' => Order::where('status', 'delivered')->count(),
            'cancelled_orders' => Order::where('status', 'cancelled')->count()
        ];

        // Revenue metrics
        $revenueMetrics = [
            'total_revenue' => Order::where('payment_status', 'paid')->sum('total_amount'),
            'revenue_today' => Order::where('payment_status', 'paid')
                ->whereDate('created_at', $today)
                ->sum('total_amount'),
            'revenue_this_week' => Order::where('payment_status', 'paid')
                ->whereBetween('created_at', [$thisWeek, now()])
                ->sum('total_amount'),
            'revenue_this_month' => Order::where('payment_status', 'paid')
                ->whereBetween('created_at', [$thisMonth, now()])
                ->sum('total_amount'),
            'pending_payments' => Order::where('payment_status', 'pending')->sum('total_amount')
        ];

        // Inventory metrics
        $inventoryMetrics = [
            'total_products' => Product::count(),
            'active_products' => Product::where('status', 'active')->count(),
            'low_stock_products' => Product::where('available_quantity', '<=', DB::raw('minimum_stock_level'))->count(),
            'out_of_stock_products' => Product::where('available_quantity', 0)->count(),
            'total_bins' => Bin::count(),
            'active_bins' => Bin::where('is_active', true)->count(),
            'critical_stock_bins' => Bin::where('current_stock_count', '<=', 3)->count()
        ];

        // DA metrics
        $daMetrics = [
            'total_das' => DeliveryAgent::count(),
            'active_das' => DeliveryAgent::where('status', 'active')->count(),
            'das_online' => DeliveryAgent::where('last_active_at', '>=', now()->subMinutes(30))->count(),
            'das_with_low_stock' => DeliveryAgent::whereHas('zobin', function($query) {
                $query->whereRaw('MIN(shampoo_count, MIN(pomade_count, conditioner_count)) < 3');
            })->count()
        ];

        // Recent activity
        $recentActivity = [
            'recent_orders' => Order::with('customer')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'order_number', 'customer_name', 'total_amount', 'status', 'created_at']),
            'recent_movements' => InventoryMovement::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'movement_type', 'quantity_changed', 'item_name', 'created_at']),
            'recent_da_activities' => AgentActivityLog::with('deliveryAgent')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'activity_type', 'description', 'created_at'])
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'order_metrics' => $orderMetrics,
                'revenue_metrics' => $revenueMetrics,
                'inventory_metrics' => $inventoryMetrics,
                'da_metrics' => $daMetrics,
                'recent_activity' => $recentActivity,
                'last_updated' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Daily login tracking (8:14 AM status)
     * GET /api/dashboard/login-status
     */
    public function getLoginStatus(): JsonResponse
    {
        $today = now()->toDateString();
        $targetTime = Carbon::parse($today . ' 08:14:00');
        $currentTime = now();

        // Get today's login log
        $dailyLog = ImDailyLog::where('log_date', $today)->first();

        $loginStatus = [
            'date' => $today,
            'target_login_time' => '08:14 AM',
            'current_time' => $currentTime->format('H:i:s'),
            'is_late' => $currentTime->gt($targetTime),
            'minutes_late' => $currentTime->gt($targetTime) ? $currentTime->diffInMinutes($targetTime) : 0,
            'login_penalty' => $this->calculateLoginPenalty($dailyLog),
            'status' => $dailyLog ? 'logged_in' : 'not_logged_in'
        ];

        if ($dailyLog) {
            $loginStatus['actual_login_time'] = $dailyLog->login_time;
            $loginStatus['is_on_time'] = Carbon::parse($dailyLog->login_time)->lte($targetTime);
        }

        return response()->json([
            'success' => true,
            'data' => $loginStatus
        ]);
    }

    /**
     * DA Reviews (23/70 completed)
     * GET /api/dashboard/reviews
     */
    public function getReviews(): JsonResponse
    {
        $today = now()->toDateString();
        $dailyLog = ImDailyLog::where('log_date', $today)->first();

        $totalDAs = DeliveryAgent::where('status', 'active')->count();
        $reviewedDAs = $dailyLog ? $dailyLog->das_reviewed_count : 0;
        $pendingReviews = $totalDAs - $reviewedDAs;

        $reviewProgress = [
            'date' => $today,
            'total_das' => $totalDAs,
            'reviewed_count' => $reviewedDAs,
            'pending_count' => $pendingReviews,
            'completion_percentage' => $totalDAs > 0 ? round(($reviewedDAs / $totalDAs) * 100, 1) : 0,
            'review_penalty' => $this->calculateReviewPenalty($dailyLog, $totalDAs),
            'status' => $reviewedDAs >= $totalDAs ? 'completed' : 'in_progress'
        ];

        // Get recent reviews
        $recentReviews = DeliveryAgent::where('status', 'active')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get(['id', 'da_code', 'user_id', 'status', 'updated_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'review_progress' => $reviewProgress,
                'recent_reviews' => $recentReviews
            ]
        ]);
    }

    /**
     * Pending system actions (5 pending)
     * GET /api/dashboard/system-actions
     */
    public function getSystemActions(): JsonResponse
    {
        $pendingActions = SystemRecommendation::where('status', 'pending')
            ->with('deliveryAgent')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        $actionCategories = [
            'stock_restock' => $pendingActions->where('type', 'stock_restock')->count(),
            'da_performance' => $pendingActions->where('type', 'da_performance')->count(),
            'inventory_adjustment' => $pendingActions->where('type', 'inventory_adjustment')->count(),
            'system_maintenance' => $pendingActions->where('type', 'system_maintenance')->count(),
            'urgent_alerts' => $pendingActions->where('priority', 'high')->count()
        ];

        $systemActions = [
            'total_pending' => $pendingActions->count(),
            'categories' => $actionCategories,
            'urgent_count' => $pendingActions->where('priority', 'high')->count(),
            'actions' => $pendingActions->take(10)
        ];

        return response()->json([
            'success' => true,
            'data' => $systemActions
        ]);
    }

    /**
     * This week revenue (+₦7,500)
     * GET /api/dashboard/weekly-metrics
     */
    public function getWeeklyMetrics(): JsonResponse
    {
        $thisWeek = now()->startOfWeek();
        $lastWeek = now()->subWeek()->startOfWeek();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // Revenue metrics
        $thisWeekRevenue = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$thisWeek, now()])
            ->sum('total_amount');

        $lastWeekRevenue = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastWeek, $thisWeek])
            ->sum('total_amount');

        $revenueChange = $lastWeekRevenue > 0 ? (($thisWeekRevenue - $lastWeekRevenue) / $lastWeekRevenue) * 100 : 0;

        // Order metrics
        $thisWeekOrders = Order::whereBetween('created_at', [$thisWeek, now()])->count();
        $lastWeekOrders = Order::whereBetween('created_at', [$lastWeek, $thisWeek])->count();
        $orderChange = $lastWeekOrders > 0 ? (($thisWeekOrders - $lastWeekOrders) / $lastWeekOrders) * 100 : 0;

        // DA performance metrics
        $daPerformance = DeliveryAgent::where('status', 'active')
            ->withCount(['deliveries' => function($query) use ($thisWeek) {
                $query->whereBetween('created_at', [$thisWeek, now()]);
            }])
            ->withAvg(['deliveries' => function($query) use ($thisWeek) {
                $query->whereBetween('created_at', [$thisWeek, now()]);
            }], 'customer_rating')
            ->orderBy('deliveries_count', 'desc')
            ->limit(5)
            ->get();

        // Daily breakdown
        $dailyBreakdown = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $thisWeek->copy()->addDays($i);
            $dailyBreakdown[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('D'),
                'revenue' => Order::where('payment_status', 'paid')
                    ->whereDate('created_at', $date)
                    ->sum('total_amount'),
                'orders' => Order::whereDate('created_at', $date)->count(),
                'deliveries' => DeliveryAgent::whereHas('deliveries', function($query) use ($date) {
                    $query->whereDate('created_at', $date);
                })->count()
            ];
        }

        $weeklyMetrics = [
            'revenue' => [
                'this_week' => $thisWeekRevenue,
                'last_week' => $lastWeekRevenue,
                'change_percentage' => round($revenueChange, 2),
                'change_amount' => $thisWeekRevenue - $lastWeekRevenue,
                'trend' => $revenueChange > 0 ? 'up' : ($revenueChange < 0 ? 'down' : 'stable')
            ],
            'orders' => [
                'this_week' => $thisWeekOrders,
                'last_week' => $lastWeekOrders,
                'change_percentage' => round($orderChange, 2),
                'trend' => $orderChange > 0 ? 'up' : ($orderChange < 0 ? 'down' : 'stable')
            ],
            'top_performers' => $daPerformance,
            'daily_breakdown' => $dailyBreakdown
        ];

        return response()->json([
            'success' => true,
            'data' => $weeklyMetrics
        ]);
    }

    /**
     * Get critical discrepancies
     * GET /api/alerts/critical
     */
    public function getCriticalAlerts(): JsonResponse
    {
        $criticalAlerts = [];

        // Low stock alerts
        $lowStockProducts = Product::where('available_quantity', '<=', DB::raw('minimum_stock_level'))
            ->where('available_quantity', '>', 0)
            ->get();

        foreach ($lowStockProducts as $product) {
            $criticalAlerts[] = [
                'id' => 'LOW_STOCK_' . $product->id,
                'type' => 'low_stock',
                'severity' => 'medium',
                'title' => 'Low Stock Alert',
                'message' => "Product {$product->name} is running low (Available: {$product->available_quantity})",
                'product_id' => $product->id,
                'product_name' => $product->name,
                'available_quantity' => $product->available_quantity,
                'minimum_level' => $product->minimum_stock_level,
                'created_at' => now()->toISOString()
            ];
        }

        // Out of stock alerts
        $outOfStockProducts = Product::where('available_quantity', 0)
            ->where('status', 'active')
            ->get();

        foreach ($outOfStockProducts as $product) {
            $criticalAlerts[] = [
                'id' => 'OUT_OF_STOCK_' . $product->id,
                'type' => 'out_of_stock',
                'severity' => 'high',
                'title' => 'Out of Stock Alert',
                'message' => "Product {$product->name} is completely out of stock",
                'product_id' => $product->id,
                'product_name' => $product->name,
                'created_at' => now()->toISOString()
            ];
        }

        // DA critical stock alerts
        $criticalStockDAs = DeliveryAgent::whereHas('zobin', function($query) {
            $query->whereRaw('MIN(shampoo_count, MIN(pomade_count, conditioner_count)) < 3');
        })->with('zobin')->get();

        foreach ($criticalStockDAs as $da) {
            $criticalAlerts[] = [
                'id' => 'DA_CRITICAL_' . $da->id,
                'type' => 'da_critical_stock',
                'severity' => 'high',
                'title' => 'DA Critical Stock Alert',
                'message' => "DA {$da->da_code} has critically low stock",
                'da_id' => $da->id,
                'da_code' => $da->da_code,
                'available_sets' => $da->zobin->available_sets ?? 0,
                'created_at' => now()->toISOString()
            ];
        }

        // Pending high-priority system actions
        $highPriorityActions = SystemRecommendation::where('status', 'pending')
            ->where('priority', 'high')
            ->get();

        foreach ($highPriorityActions as $action) {
            $criticalAlerts[] = [
                'id' => 'SYSTEM_ACTION_' . $action->id,
                'type' => 'system_action',
                'severity' => 'high',
                'title' => 'High Priority System Action',
                'message' => $action->description,
                'action_id' => $action->id,
                'action_type' => $action->type,
                'created_at' => $action->created_at->toISOString()
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_alerts' => count($criticalAlerts),
                'high_severity' => count(array_filter($criticalAlerts, fn($alert) => $alert['severity'] === 'high')),
                'medium_severity' => count(array_filter($criticalAlerts, fn($alert) => $alert['severity'] === 'medium')),
                'alerts' => $criticalAlerts
            ]
        ]);
    }

    /**
     * Get daily enforcement tasks
     * GET /api/alerts/enforcement-tasks
     */
    public function getEnforcementTasks(): JsonResponse
    {
        $today = now()->toDateString();
        $dailyLog = ImDailyLog::where('log_date', $today)->first();

        $enforcementTasks = [
            'login_compliance' => [
                'task' => 'Daily Login by 8:14 AM',
                'status' => $dailyLog ? 'completed' : 'pending',
                'due_time' => '08:14 AM',
                'penalty' => $this->calculateLoginPenalty($dailyLog),
                'completed_at' => $dailyLog ? $dailyLog->login_time : null
            ],
            'da_reviews' => [
                'task' => 'Review All Active DAs',
                'status' => $this->getDAReviewStatus($dailyLog),
                'progress' => $dailyLog ? "{$dailyLog->das_reviewed_count}/" . DeliveryAgent::where('status', 'active')->count() : '0/0',
                'penalty' => $this->calculateReviewPenalty($dailyLog, DeliveryAgent::where('status', 'active')->count())
            ],
            'system_actions' => [
                'task' => 'Process System Recommendations',
                'status' => SystemRecommendation::where('status', 'pending')->count() > 0 ? 'pending' : 'completed',
                'pending_count' => SystemRecommendation::where('status', 'pending')->count(),
                'urgent_count' => SystemRecommendation::where('status', 'pending')->where('priority', 'high')->count()
            ],
            'inventory_checks' => [
                'task' => 'Daily Inventory Verification',
                'status' => $this->getInventoryCheckStatus(),
                'low_stock_items' => Product::where('available_quantity', '<=', DB::raw('minimum_stock_level'))->count(),
                'out_of_stock_items' => Product::where('available_quantity', 0)->count()
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $today,
                'tasks' => $enforcementTasks,
                'overall_completion' => $this->calculateOverallCompletion($enforcementTasks)
            ]
        ]);
    }

    /**
     * Mark alert as resolved
     * PUT /api/alerts/{alertId}/resolve
     */
    public function resolveAlert(Request $request, $alertId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'resolution_notes' => 'nullable|string',
            'resolved_by' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Parse alert ID to determine type and handle accordingly
        if (str_starts_with($alertId, 'LOW_STOCK_')) {
            $productId = str_replace('LOW_STOCK_', '', $alertId);
            $product = Product::find($productId);
            
            if ($product) {
                // Log the resolution
                \Log::info("Low stock alert resolved for product {$product->name}", [
                    'product_id' => $productId,
                    'resolved_by' => $request->resolved_by,
                    'notes' => $request->resolution_notes
                ]);
            }
        } elseif (str_starts_with($alertId, 'DA_CRITICAL_')) {
            $daId = str_replace('DA_CRITICAL_', '', $alertId);
            $da = DeliveryAgent::find($daId);
            
            if ($da) {
                // Log the resolution
                \Log::info("DA critical stock alert resolved for {$da->da_code}", [
                    'da_id' => $daId,
                    'resolved_by' => $request->resolved_by,
                    'notes' => $request->resolution_notes
                ]);
            }
        } elseif (str_starts_with($alertId, 'SYSTEM_ACTION_')) {
            $actionId = str_replace('SYSTEM_ACTION_', '', $alertId);
            $action = SystemRecommendation::find($actionId);
            
            if ($action) {
                $action->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'resolved_by' => $request->resolved_by,
                    'resolution_notes' => $request->resolution_notes
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Alert resolved successfully',
            'data' => [
                'alert_id' => $alertId,
                'resolved_at' => now()->toISOString(),
                'resolved_by' => $request->resolved_by
            ]
        ]);
    }

    /**
     * Calculate login penalty
     */
    private function calculateLoginPenalty($dailyLog): float
    {
        if (!$dailyLog || !$dailyLog->login_time) {
            return 0;
        }

        $targetTime = Carbon::parse($dailyLog->log_date . ' 08:14:00');
        $loginTime = Carbon::parse($dailyLog->login_time);

        if ($loginTime->lte($targetTime)) {
            return 0;
        }

        $minutesLate = $loginTime->diffInMinutes($targetTime);
        return $minutesLate * 100; // ₦100 per minute late
    }

    /**
     * Calculate review penalty
     */
    private function calculateReviewPenalty($dailyLog, $totalDAs): float
    {
        if (!$dailyLog) {
            return $totalDAs * 500; // ₦500 per unreviewed DA
        }

        $unreviewed = $totalDAs - $dailyLog->das_reviewed_count;
        return $unreviewed * 500;
    }

    /**
     * Get DA review status
     */
    private function getDAReviewStatus($dailyLog): string
    {
        if (!$dailyLog) {
            return 'pending';
        }

        $totalDAs = DeliveryAgent::where('status', 'active')->count();
        return $dailyLog->das_reviewed_count >= $totalDAs ? 'completed' : 'in_progress';
    }

    /**
     * Get inventory check status
     */
    private function getInventoryCheckStatus(): string
    {
        $lowStockCount = Product::where('available_quantity', '<=', DB::raw('minimum_stock_level'))->count();
        $outOfStockCount = Product::where('available_quantity', 0)->count();
        
        if ($outOfStockCount > 0) {
            return 'critical';
        } elseif ($lowStockCount > 0) {
            return 'warning';
        } else {
            return 'completed';
        }
    }

    /**
     * Calculate overall completion percentage
     */
    private function calculateOverallCompletion($tasks): float
    {
        $completed = 0;
        $total = count($tasks);

        foreach ($tasks as $task) {
            if ($task['status'] === 'completed') {
                $completed++;
            }
        }

        return $total > 0 ? round(($completed / $total) * 100, 1) : 0;
    }

    /**
     * Enhanced dashboard overview with time and status
     * GET /api/inventory-portal/dashboard/enhanced-overview
     */
    public function getEnhancedOverview(): JsonResponse
    {
        $now = now();
        $photoDeadline = $now->copy()->endOfWeek()->subDays(1)->setTime(12, 0);
        $timeRemaining = $now->diff($photoDeadline);
        
        // Calculate penalty risk
        $totalDAs = DeliveryAgent::where('status', 'active')->count();
        $nonCompliantDAs = $totalDAs - StockPhoto::where('uploaded_at', '>=', $now->startOfWeek())->count();
        $penaltyRisk = $nonCompliantDAs * 2500; // ₦2500 per non-compliant DA

        // Critical actions count
        $criticalActions = 0;
        $criticalActions += Product::where('available_quantity', 0)->count(); // Out of stock
        $criticalActions += DeliveryAgent::where('status', 'active')
            ->whereHas('zobin', function($query) {
                $query->whereRaw('MIN(shampoo_count, MIN(pomade_count, conditioner_count)) = 0');
            })->count(); // DAs with zero stock
        $criticalActions += Bin::where('current_stock_count', '<=', 3)->count(); // Critical bins

        // DA reviews
        $totalDAsForReview = $totalDAs;
        $reviewedDAs = ImDailyLog::where('log_date', $now->toDateString())->value('das_reviewed_count') ?? 0;
        $reviewPercentage = $totalDAsForReview > 0 ? round(($reviewedDAs / $totalDAsForReview) * 100, 1) : 0;

        // System actions
        $pendingActions = 5; // Placeholder
        $systemPenaltyRisk = 400000; // Placeholder

        // Weekly earnings
        $weeklyEarnings = Order::whereBetween('created_at', [$now->startOfWeek(), $now])
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        // Critical discrepancies
        $criticalDiscrepancies = 3; // Placeholder
        $revenueAtRisk = 497500; // Placeholder
        $protectedToday = 889200; // Placeholder

        return response()->json([
            'success' => true,
            'data' => [
                'time' => $now->format('g:i A'),
                'status' => 'On time',
                'photo_deadline' => $timeRemaining->format('%hh %im remaining'),
                'penalty_risk' => '₦' . number_format($penaltyRisk),
                'critical_actions' => $criticalActions,
                'metrics' => [
                    'daily_login' => '8:14 AM',
                    'da_reviews' => [
                        'completed' => $reviewedDAs,
                        'total' => $totalDAsForReview,
                        'percentage' => $reviewPercentage
                    ],
                    'system_actions' => [
                        'pending' => $pendingActions,
                        'penalty_risk' => $systemPenaltyRisk
                    ],
                    'weekly_earnings' => $weeklyEarnings
                ],
                'critical_discrepancies' => $criticalDiscrepancies,
                'revenue_at_risk' => $revenueAtRisk,
                'protected_today' => $protectedToday,
                'avg_resolution' => '12 min',
                'last_updated' => $now->toISOString()
            ]
        ]);
    }

    /**
     * Regional overview dashboard
     * GET /api/inventory-portal/dashboard/regional-overview
     */
    public function getRegionalOverview(): JsonResponse
    {
        $states = collect(['Lagos', 'Abuja', 'Kano', 'Port Harcourt']);
        
        $regionalData = $states->map(function($state) {
            $totalDAs = DeliveryAgent::where('state', $state)->where('status', 'active')->count();
            $activeDAs = DeliveryAgent::where('state', $state)
                ->where('status', 'active')
                ->where('last_active_at', '>=', now()->subHours(24))
                ->count();

            $totalOrders = Order::whereHas('deliveryAgent', function($query) use ($state) {
                $query->where('state', $state);
            })->count();

            $deliveredOrders = Order::whereHas('deliveryAgent', function($query) use ($state) {
                $query->where('state', $state);
            })->where('status', 'delivered')->count();

            $successRate = $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 1) : 0;

            $totalRevenue = Order::whereHas('deliveryAgent', function($query) use ($state) {
                $query->where('state', $state);
            })->where('payment_status', 'paid')->sum('total_amount');

            return [
                'state' => $state,
                'zones' => $this->getZoneCount($state),
                'stock' => $this->getStateStock($state),
                'agents' => $totalDAs,
                'utilization' => $this->getStateUtilization($state),
                'status' => $this->getStateStatus($successRate),
                'performance' => [
                    'total_orders' => $totalOrders,
                    'delivered_orders' => $deliveredOrders,
                    'success_rate' => $successRate,
                    'total_revenue' => $totalRevenue
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
     * Get zone count for a state
     */
    private function getZoneCount($state): int
    {
        $zoneMap = [
            'Lagos' => 12,
            'Abuja' => 8,
            'Kano' => 6,
            'Port Harcourt' => 5
        ];
        
        return $zoneMap[$state] ?? 1;
    }

    /**
     * Get state stock level
     */
    private function getStateStock($state): int
    {
        return DeliveryAgent::where('state', $state)
            ->where('status', 'active')
            ->whereHas('zobin')
            ->with('zobin')
            ->get()
            ->sum(function($da) {
                return $da->zobin->shampoo_count + $da->zobin->pomade_count + $da->zobin->conditioner_count;
            });
    }

    /**
     * Get state utilization rate
     */
    private function getStateUtilization($state): float
    {
        $totalBins = Bin::where('state', $state)->count();
        $activeBins = Bin::where('state', $state)->where('is_active', true)->count();
        
        return $totalBins > 0 ? round(($activeBins / $totalBins) * 100, 1) : 0;
    }

    /**
     * Get state status
     */
    private function getStateStatus($successRate): string
    {
        if ($successRate >= 90) return 'excellent';
        if ($successRate >= 75) return 'optimal';
        if ($successRate >= 60) return 'warning';
        return 'critical';
    }
} 