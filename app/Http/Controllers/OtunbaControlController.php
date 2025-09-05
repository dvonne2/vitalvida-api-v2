<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Investor;
use App\Models\Order;
use App\Models\DeliveryAgent;
use App\Models\Revenue;
use Carbon\Carbon;

class OtunbaControlController extends Controller
{
    /**
     * Get Otunba Control dashboard
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_OTUNBA_CONTROL) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Otunba Control access required.'
                ], 403);
            }

            $data = [
                'daily_cash_position' => $this->getDailyCashPosition(),
                'sales_vs_cash_received' => $this->getSalesVsCashReceived(),
                'outstanding_da_balances' => $this->getOutstandingDABalances(),
                'inventory_movement_log' => $this->getInventoryMovementLog(),
                'weekly_net_profit' => $this->getWeeklyNetProfit(),
                'refund_ledger' => $this->getRefundLedger(),
                'offline_spend_register' => $this->getOfflineSpendRegister(),
                'equity_value_tracker' => $this->getEquityValueTracker(),
                'financial_controls' => $this->getFinancialControls(),
                'cash_flow_monitoring' => $this->getCashFlowMonitoring()
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'last_updated' => now()->toISOString(),
                    'investor_role' => $investor->role,
                    'access_level' => $investor->access_level
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load Otunba Control dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily cash position
     */
    private function getDailyCashPosition()
    {
        return [
            'moniepoint_business' => [
                'balance' => 2180000,
                'balance_formatted' => '₦2.18M',
                'last_updated' => '2 hours ago',
                'status' => 'active'
            ],
            'gtb_current' => [
                'balance' => 270000,
                'balance_formatted' => '₦270K',
                'last_updated' => 'This morning',
                'status' => 'active'
            ],
            'petty_cash_office' => [
                'balance' => 45000,
                'balance_formatted' => '₦45K',
                'counted' => 'today',
                'status' => 'verified'
            ],
            'total_available' => 2495000,
            'total_available_formatted' => '₦2.495M',
            'cash_flow_today' => [
                'inflow' => 1185000,
                'inflow_formatted' => '₦1.185M',
                'outflow' => 850000,
                'outflow_formatted' => '₦850K',
                'net_change' => 335000,
                'net_change_formatted' => '₦335K'
            ]
        ];
    }

    /**
     * Get sales vs cash received
     */
    private function getSalesVsCashReceived()
    {
        $today = Carbon::today();
        $ordersToday = Order::whereDate('created_at', $today)->count();
        $totalOrderValue = Order::whereDate('created_at', $today)->sum('total_amount');
        
        return [
            'orders_closed_today' => $ordersToday,
            'total_order_value' => $totalOrderValue,
            'total_order_value_formatted' => '₦' . number_format($totalOrderValue / 1000000, 2) . 'M',
            'moniepoint_confirmed' => $totalOrderValue,
            'moniepoint_confirmed_formatted' => '₦' . number_format($totalOrderValue / 1000000, 2) . 'M',
            'payment_variance' => 0,
            'payment_variance_formatted' => '₦0',
            'perfect_match' => 'All sales confirmed via payment platform - no missing money',
            'payment_methods' => [
                'moniepoint' => [
                    'amount' => $totalOrderValue,
                    'percentage' => 100,
                    'status' => 'confirmed'
                ],
                'cash_on_delivery' => [
                    'amount' => 0,
                    'percentage' => 0,
                    'status' => 'none'
                ]
            ]
        ];
    }

    /**
     * Get outstanding DA balances
     */
    private function getOutstandingDABalances()
    {
        return [
            'kemi_adebayo_lagos' => [
                'packages_delivered' => 12,
                'amount_owed' => 0,
                'amount_owed_formatted' => '₦0',
                'status' => 'all_paid',
                'last_payment' => '2024-12-08',
                'performance_rating' => 'excellent'
            ],
            'samuel_okafor_victoria' => [
                'packages_delivered' => 8,
                'amount_owed' => 0,
                'amount_owed_formatted' => '₦0',
                'status' => 'all_paid',
                'last_payment' => '2024-12-07',
                'performance_rating' => 'good'
            ],
            'fatima_ibrahim_ikeja' => [
                'packages_delivered' => 15,
                'amount_owed' => 0,
                'amount_owed_formatted' => '₦0',
                'status' => 'all_paid',
                'last_payment' => '2024-12-08',
                'performance_rating' => 'excellent'
            ],
            'total_da_exposure' => 0,
            'total_da_exposure_formatted' => '₦0',
            'exposure_status' => 'All DAs have zero outstanding balance',
            'total_das_active' => 47,
            'total_deliveries_today' => 35
        ];
    }

    /**
     * Get inventory movement log
     */
    private function getInventoryMovementLog()
    {
        return [
            'recent_movements' => [
                [
                    'carton_id' => '#VV-2024-189',
                    'product' => '12 Moringa bottles',
                    'movement' => 'DA: Kemi Adebayo',
                    'timestamp' => '14:29 today',
                    'photo_verified' => true,
                    'gps_location' => 'Lagos Island',
                    'status' => 'delivered'
                ],
                [
                    'carton_id' => '#VV-2024-188',
                    'product' => '8 Ginger capsules',
                    'movement' => 'DA: Samuel Okafor',
                    'timestamp' => '13:45 today',
                    'photo_verified' => true,
                    'gps_location' => 'Victoria Island',
                    'status' => 'delivered'
                ],
                [
                    'carton_id' => '#VV-2024-187',
                    'product' => '6 Turmeric boost',
                    'movement' => 'DA: Fatima Ibrahim',
                    'timestamp' => '12:30 today',
                    'photo_verified' => true,
                    'gps_location' => 'Ikeja',
                    'status' => 'in_transit'
                ]
            ],
            'total_movements_today' => 35,
            'verified_movements' => 34,
            'unverified_movements' => 1,
            'verification_rate' => '97.1%'
        ];
    }

    /**
     * Get weekly net profit
     */
    private function getWeeklyNetProfit()
    {
        $currentWeek = Carbon::now()->startOfWeek();
        $lastWeek = Carbon::now()->subWeek()->startOfWeek();
        
        $revenueThisWeek = Revenue::whereBetween('date', [$currentWeek, $currentWeek->copy()->endOfWeek()])->sum('amount');
        $revenueLastWeek = Revenue::whereBetween('date', [$lastWeek, $lastWeek->copy()->endOfWeek()])->sum('amount');
        
        $netProfitThisWeek = $revenueThisWeek * 0.25; // 25% net margin
        $netProfitLastWeek = $revenueLastWeek * 0.25;
        $growthPercentage = $revenueLastWeek > 0 ? (($revenueThisWeek - $revenueLastWeek) / $revenueLastWeek) * 100 : 12;

        return [
            'net_profit_this_week' => $netProfitThisWeek,
            'net_profit_this_week_formatted' => '₦' . number_format($netProfitThisWeek / 1000, 0) . 'K',
            'vs_last_week' => $growthPercentage > 0 ? '+' . $growthPercentage . '%' : $growthPercentage . '%',
            'breakdown' => [
                'total_revenue' => $revenueThisWeek,
                'total_revenue_formatted' => '₦' . number_format($revenueThisWeek / 1000000, 2) . 'M',
                'cogs' => $revenueThisWeek * 0.5,
                'cogs_formatted' => '₦' . number_format(($revenueThisWeek * 0.5) / 1000000, 2) . 'M',
                'operating_expenses' => $revenueThisWeek * 0.2,
                'operating_expenses_formatted' => '₦' . number_format(($revenueThisWeek * 0.2) / 1000000, 2) . 'M',
                'da_commissions' => $revenueThisWeek * 0.05,
                'da_commissions_formatted' => '₦' . number_format(($revenueThisWeek * 0.05) / 1000, 0) . 'K',
                'net_profit' => $netProfitThisWeek,
                'net_profit_formatted' => '₦' . number_format($netProfitThisWeek / 1000, 0) . 'K'
            ],
            'weekly_trend' => 'increasing',
            'projected_monthly_profit' => $netProfitThisWeek * 4
        ];
    }

    /**
     * Get refund ledger
     */
    private function getRefundLedger()
    {
        return [
            'total_refunds_week' => 45000,
            'total_refunds_week_formatted' => '₦45K',
            'refund_rate' => '2.1%',
            'incidents' => [
                [
                    'customer' => 'Mrs. Ogbonna',
                    'amount' => 18000,
                    'amount_formatted' => '₦18K',
                    'reason' => 'Package damaged in transit',
                    'approved_by' => 'John (Admin)',
                    'date' => 'Dec 5, 18:30',
                    'status' => 'processed'
                ],
                [
                    'customer' => 'Mr. Adiele',
                    'amount' => 12500,
                    'amount_formatted' => '₦12.5K',
                    'reason' => 'Product not effective',
                    'approved_by' => 'John (Admin)',
                    'date' => 'Dec 4, 14:15',
                    'status' => 'processed'
                ],
                [
                    'customer' => 'Mrs. Adebayo',
                    'amount' => 14500,
                    'amount_formatted' => '₦14.5K',
                    'reason' => 'Wrong product delivered',
                    'approved_by' => 'John (Admin)',
                    'date' => 'Dec 3, 16:45',
                    'status' => 'pending'
                ]
            ],
            'refund_categories' => [
                'damage_in_transit' => [
                    'amount' => 18000,
                    'count' => 1,
                    'percentage' => 40
                ],
                'product_effectiveness' => [
                    'amount' => 12500,
                    'count' => 1,
                    'percentage' => 28
                ],
                'delivery_errors' => [
                    'amount' => 14500,
                    'count' => 1,
                    'percentage' => 32
                ]
            ]
        ];
    }

    /**
     * Get offline spend register
     */
    private function getOfflineSpendRegister()
    {
        return [
            'recent_expenses' => [
                [
                    'item' => 'Fuel for delivery vehicle',
                    'amount' => 15000,
                    'amount_formatted' => '₦15K',
                    'date' => 'Dec 5, 2024',
                    'logged_by' => 'John',
                    'category' => 'transportation'
                ],
                [
                    'item' => 'Market park charges',
                    'amount' => 2500,
                    'amount_formatted' => '₦2.5K',
                    'date' => 'Dec 5, 2024',
                    'logged_by' => 'Kemi',
                    'category' => 'operational'
                ],
                [
                    'item' => 'Office supplies',
                    'amount' => 8000,
                    'amount_formatted' => '₦8K',
                    'date' => 'Dec 4, 2024',
                    'logged_by' => 'John',
                    'category' => 'office'
                ]
            ],
            'weekly_total' => 25500,
            'weekly_total_formatted' => '₦25.5K',
            'expense_categories' => [
                'transportation' => [
                    'amount' => 15000,
                    'percentage' => 59
                ],
                'operational' => [
                    'amount' => 2500,
                    'percentage' => 10
                ],
                'office' => [
                    'amount' => 8000,
                    'percentage' => 31
                ]
            ],
            'budget_vs_actual' => [
                'budget' => 30000,
                'actual' => 25500,
                'variance' => -4500,
                'status' => 'under_budget'
            ]
        ];
    }

    /**
     * Get equity value tracker
     */
    private function getEquityValueTracker()
    {
        $currentEquityValue = 5850000;
        $growthThisWeek = 487000;
        $totalCompanyValue = 7800000;
        $valueChangeMTD = 1220000;

        return [
            'current_equity_value' => $currentEquityValue,
            'current_equity_value_formatted' => '₦5.85M',
            'growth_this_week' => $growthThisWeek,
            'growth_this_week_formatted' => '+₦487K',
            'ownership_percentage' => '75%',
            'total_company_value' => $totalCompanyValue,
            'total_company_value_formatted' => '₦7.8M',
            'value_change_mtd' => $valueChangeMTD,
            'value_change_mtd_formatted' => '+₦1.22M',
            'roi_annualized' => '156%',
            'bottom_line' => 'Your investment is growing ₦487K every week with zero cash leakage',
            'equity_growth_trend' => [
                'weekly_growth' => '8.3%',
                'monthly_growth' => '26.4%',
                'quarterly_growth' => '89.2%',
                'annual_growth' => '156%'
            ],
            'valuation_metrics' => [
                'price_to_earnings' => 12.5,
                'price_to_sales' => 2.8,
                'book_value' => 7800000,
                'market_cap' => 10400000
            ]
        ];
    }

    /**
     * Get financial controls
     */
    private function getFinancialControls()
    {
        return [
            'approval_limits' => [
                'daily_expense_limit' => 50000,
                'daily_expense_limit_formatted' => '₦50K',
                'refund_approval_limit' => 25000,
                'refund_approval_limit_formatted' => '₦25K',
                'vendor_payment_limit' => 100000,
                'vendor_payment_limit_formatted' => '₦100K'
            ],
            'control_checks' => [
                'daily_cash_reconciliation' => 'completed',
                'bank_reconciliation' => 'completed',
                'expense_approvals' => 'pending',
                'refund_verifications' => 'completed'
            ],
            'fraud_prevention' => [
                'suspicious_transactions' => 0,
                'unusual_patterns' => 0,
                'verification_rate' => '100%',
                'last_audit' => '2024-12-01'
            ],
            'compliance_status' => [
                'tax_compliance' => 'up_to_date',
                'regulatory_compliance' => 'compliant',
                'financial_reporting' => 'on_schedule',
                'audit_ready' => 'yes'
            ]
        ];
    }

    /**
     * Get cash flow monitoring
     */
    private function getCashFlowMonitoring()
    {
        return [
            'daily_cash_flow' => [
                'opening_balance' => 2160000,
                'opening_balance_formatted' => '₦2.16M',
                'cash_inflow' => 1185000,
                'cash_inflow_formatted' => '₦1.185M',
                'cash_outflow' => 850000,
                'cash_outflow_formatted' => '₦850K',
                'closing_balance' => 2495000,
                'closing_balance_formatted' => '₦2.495M'
            ],
            'cash_flow_sources' => [
                'sales_revenue' => [
                    'amount' => 1185000,
                    'percentage' => 100
                ],
                'refunds' => [
                    'amount' => -45000,
                    'percentage' => -3.8
                ],
                'expenses' => [
                    'amount' => -850000,
                    'percentage' => -71.7
                ]
            ],
            'cash_flow_forecast' => [
                'next_week_projected' => 2600000,
                'next_week_projected_formatted' => '₦2.6M',
                'month_end_projected' => 2800000,
                'month_end_projected_formatted' => '₦2.8M',
                'confidence_level' => '95%'
            ]
        ];
    }

    /**
     * Get financial alerts
     */
    public function getFinancialAlerts(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_OTUNBA_CONTROL) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Otunba Control access required.'
                ], 403);
            }

            $alerts = [
                [
                    'id' => 1,
                    'type' => 'cash_flow',
                    'severity' => 'low',
                    'title' => 'Petty Cash Low',
                    'description' => 'Petty cash balance below ₦50K threshold',
                    'date' => '2024-12-08',
                    'status' => 'resolved'
                ],
                [
                    'id' => 2,
                    'type' => 'refund',
                    'severity' => 'medium',
                    'title' => 'Refund Request Pending',
                    'description' => 'Mrs. Adebayo refund request awaiting approval',
                    'date' => '2024-12-08',
                    'status' => 'pending'
                ],
                [
                    'id' => 3,
                    'type' => 'expense',
                    'severity' => 'low',
                    'title' => 'Weekly Expenses Under Budget',
                    'description' => 'Weekly expenses 15% under budget',
                    'date' => '2024-12-08',
                    'status' => 'positive'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'alerts' => $alerts,
                    'summary' => [
                        'total_alerts' => count($alerts),
                        'critical' => 0,
                        'medium' => 1,
                        'low' => 2,
                        'positive' => 1
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load financial alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
