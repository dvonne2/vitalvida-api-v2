<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsEngineService;
use App\Services\PaymentEngineService;
use App\Services\InventoryVerificationService;
use App\Services\ThresholdValidationService;
use App\Services\BonusCalculationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MobileDashboardController extends Controller
{
    private AnalyticsEngineService $analyticsService;
    private PaymentEngineService $paymentService;
    private InventoryVerificationService $inventoryService;
    private ThresholdValidationService $thresholdService;
    private BonusCalculationService $bonusService;

    public function __construct(
        AnalyticsEngineService $analyticsService,
        PaymentEngineService $paymentService,
        InventoryVerificationService $inventoryService,
        ThresholdValidationService $thresholdService,
        BonusCalculationService $bonusService
    ) {
        $this->analyticsService = $analyticsService;
        $this->paymentService = $paymentService;
        $this->inventoryService = $inventoryService;
        $this->thresholdService = $thresholdService;
        $this->bonusService = $bonusService;
    }

    /**
     * Get mobile dashboard overview
     */
    public function overview(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required'
                ], 401);
            }

            // Get role-specific dashboard data
            $dashboardData = match($user->role) {
                'ceo', 'gm' => $this->getExecutiveDashboard(),
                'fc' => $this->getFinanceDashboard(),
                'da' => $this->getDeliveryAgentDashboard(),
                'im' => $this->getInventoryManagerDashboard(),
                default => $this->getDefaultDashboard()
            };

            return response()->json([
                'success' => true,
                'data' => $dashboardData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Dashboard loading failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get executive dashboard data
     */
    private function getExecutiveDashboard(): array
    {
        $today = now()->format('Y-m-d');
        $thisMonth = now()->format('Y-m');

        return [
            'summary' => [
                'total_revenue' => $this->paymentService->getTotalRevenue($thisMonth),
                'total_payments' => $this->paymentService->getTotalPayments($thisMonth),
                'pending_payments' => $this->paymentService->getPendingPayments(),
                'inventory_value' => $this->inventoryService->getTotalInventoryValue(),
                'active_deliveries' => $this->inventoryService->getActiveDeliveries(),
                'threshold_violations' => $this->thresholdService->getViolationCount(),
                'bonus_payouts' => $this->bonusService->getTotalBonusPayouts($thisMonth)
            ],
            'quick_actions' => [
                'approve_payments' => $this->paymentService->getPendingApprovalCount(),
                'review_thresholds' => $this->thresholdService->getUrgentItemsCount(),
                'process_bonuses' => $this->bonusService->getPendingBonusCount(),
                'view_reports' => $this->analyticsService->getAvailableReportsCount()
            ],
            'alerts' => $this->getAlerts(),
            'recent_activity' => $this->getRecentActivity()
        ];
    }

    /**
     * Get finance dashboard data
     */
    private function getFinanceDashboard(): array
    {
        $thisMonth = now()->format('Y-m');

        return [
            'summary' => [
                'total_revenue' => $this->paymentService->getTotalRevenue($thisMonth),
                'pending_payments' => $this->paymentService->getPendingPayments(),
                'payment_approvals' => $this->paymentService->getPendingApprovalCount(),
                'bonus_payouts' => $this->bonusService->getTotalBonusPayouts($thisMonth),
                'salary_deductions' => $this->bonusService->getTotalSalaryDeductions($thisMonth)
            ],
            'quick_actions' => [
                'approve_payments' => $this->paymentService->getPendingApprovalCount(),
                'process_bonuses' => $this->bonusService->getPendingBonusCount(),
                'review_deductions' => $this->bonusService->getPendingDeductionCount(),
                'generate_reports' => $this->analyticsService->getAvailableReportsCount()
            ],
            'alerts' => $this->getFinanceAlerts(),
            'recent_activity' => $this->getFinanceActivity()
        ];
    }

    /**
     * Get delivery agent dashboard data
     */
    private function getDeliveryAgentDashboard(): array
    {
        $user = auth()->user();
        $today = now()->format('Y-m-d');

        return [
            'summary' => [
                'today_deliveries' => $this->inventoryService->getAgentDeliveries($user->id, $today),
                'pending_deliveries' => $this->inventoryService->getAgentPendingDeliveries($user->id),
                'completed_deliveries' => $this->inventoryService->getAgentCompletedDeliveries($user->id, $today),
                'total_earnings' => $this->bonusService->getAgentEarnings($user->id, $today),
                'bonus_earned' => $this->bonusService->getAgentBonus($user->id, $today)
            ],
            'quick_actions' => [
                'start_delivery' => $this->inventoryService->getAgentAvailableDeliveries($user->id),
                'complete_delivery' => $this->inventoryService->getAgentInProgressDeliveries($user->id),
                'report_issue' => 0,
                'view_earnings' => 1
            ],
            'alerts' => $this->getAgentAlerts($user->id),
            'recent_activity' => $this->getAgentActivity($user->id)
        ];
    }

    /**
     * Get inventory manager dashboard data
     */
    private function getInventoryManagerDashboard(): array
    {
        $today = now()->format('Y-m-d');

        return [
            'summary' => [
                'total_inventory' => $this->inventoryService->getTotalInventoryCount(),
                'low_stock_items' => $this->inventoryService->getLowStockItemsCount(),
                'pending_movements' => $this->inventoryService->getPendingMovementsCount(),
                'today_movements' => $this->inventoryService->getTodayMovementsCount(),
                'threshold_violations' => $this->thresholdService->getViolationCount()
            ],
            'quick_actions' => [
                'approve_movements' => $this->inventoryService->getPendingApprovalCount(),
                'check_stock' => $this->inventoryService->getLowStockItemsCount(),
                'process_returns' => $this->inventoryService->getPendingReturnsCount(),
                'update_inventory' => 1
            ],
            'alerts' => $this->getInventoryAlerts(),
            'recent_activity' => $this->getInventoryActivity()
        ];
    }

    /**
     * Get default dashboard data
     */
    private function getDefaultDashboard(): array
    {
        return [
            'summary' => [
                'welcome_message' => 'Welcome to Vitalvida Mobile',
                'last_login' => auth()->user()->last_login_at ?? 'First time login'
            ],
            'quick_actions' => [
                'view_profile' => 1,
                'change_password' => 1,
                'contact_support' => 1
            ],
            'alerts' => [],
            'recent_activity' => []
        ];
    }

    /**
     * Get alerts for dashboard
     */
    private function getAlerts(): array
    {
        $alerts = [];

        // Payment alerts
        $pendingPayments = $this->paymentService->getPendingPayments();
        if ($pendingPayments > 10) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'High Pending Payments',
                'message' => "{$pendingPayments} payments awaiting approval",
                'action' => 'review_payments'
            ];
        }

        // Threshold alerts
        $violations = $this->thresholdService->getViolationCount();
        if ($violations > 0) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'Threshold Violations',
                'message' => "{$violations} threshold violations detected",
                'action' => 'review_thresholds'
            ];
        }

        // Inventory alerts
        $lowStock = $this->inventoryService->getLowStockItemsCount();
        if ($lowStock > 5) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Low Stock Items',
                'message' => "{$lowStock} items are running low on stock",
                'action' => 'check_inventory'
            ];
        }

        return $alerts;
    }

    /**
     * Get finance-specific alerts
     */
    private function getFinanceAlerts(): array
    {
        $alerts = [];

        $pendingApprovals = $this->paymentService->getPendingApprovalCount();
        if ($pendingApprovals > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Payment Approvals',
                'message' => "{$pendingApprovals} payments need approval",
                'action' => 'approve_payments'
            ];
        }

        $pendingBonuses = $this->bonusService->getPendingBonusCount();
        if ($pendingBonuses > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Bonus Processing',
                'message' => "{$pendingBonuses} bonuses need processing",
                'action' => 'process_bonuses'
            ];
        }

        return $alerts;
    }

    /**
     * Get agent-specific alerts
     */
    private function getAgentAlerts(int $userId): array
    {
        $alerts = [];

        $pendingDeliveries = $this->inventoryService->getAgentPendingDeliveries($userId);
        if ($pendingDeliveries > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Pending Deliveries',
                'message' => "{$pendingDeliveries} deliveries assigned to you",
                'action' => 'view_deliveries'
            ];
        }

        return $alerts;
    }

    /**
     * Get inventory-specific alerts
     */
    private function getInventoryAlerts(): array
    {
        $alerts = [];

        $lowStock = $this->inventoryService->getLowStockItemsCount();
        if ($lowStock > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Low Stock Alert',
                'message' => "{$lowStock} items need restocking",
                'action' => 'check_stock'
            ];
        }

        $pendingMovements = $this->inventoryService->getPendingMovementsCount();
        if ($pendingMovements > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Pending Movements',
                'message' => "{$pendingMovements} inventory movements need approval",
                'action' => 'approve_movements'
            ];
        }

        return $alerts;
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(): array
    {
        // This would typically fetch from activity logs
        return [
            [
                'type' => 'payment',
                'message' => 'Payment approved for delivery #1234',
                'timestamp' => now()->subMinutes(5)->toISOString()
            ],
            [
                'type' => 'inventory',
                'message' => 'Stock movement processed',
                'timestamp' => now()->subMinutes(15)->toISOString()
            ],
            [
                'type' => 'bonus',
                'message' => 'Bonus calculated for agent John Doe',
                'timestamp' => now()->subMinutes(30)->toISOString()
            ]
        ];
    }

    /**
     * Get finance activity
     */
    private function getFinanceActivity(): array
    {
        return [
            [
                'type' => 'payment',
                'message' => 'Payment processed for $500',
                'timestamp' => now()->subMinutes(10)->toISOString()
            ],
            [
                'type' => 'bonus',
                'message' => 'Bonus payout completed',
                'timestamp' => now()->subMinutes(25)->toISOString()
            ]
        ];
    }

    /**
     * Get agent activity
     */
    private function getAgentActivity(int $userId): array
    {
        return [
            [
                'type' => 'delivery',
                'message' => 'Delivery completed for order #5678',
                'timestamp' => now()->subMinutes(20)->toISOString()
            ],
            [
                'type' => 'earnings',
                'message' => 'Earned $25 for delivery',
                'timestamp' => now()->subMinutes(45)->toISOString()
            ]
        ];
    }

    /**
     * Get inventory activity
     */
    private function getInventoryActivity(): array
    {
        return [
            [
                'type' => 'movement',
                'message' => 'Stock movement approved',
                'timestamp' => now()->subMinutes(8)->toISOString()
            ],
            [
                'type' => 'restock',
                'message' => 'Inventory restocked for 5 items',
                'timestamp' => now()->subMinutes(35)->toISOString()
            ]
        ];
    }
} 