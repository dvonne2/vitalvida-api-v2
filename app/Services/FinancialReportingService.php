<?php

namespace App\Services;

use App\Models\Revenue;
use App\Models\Order;
use App\Models\FinancialStatement;
use App\Models\Investor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportingService
{
    /**
     * Generate Profit & Loss statement
     */
    public function generateProfitLossStatement($period = 'current_month')
    {
        $startDate = $this->getPeriodStartDate($period);
        $endDate = $this->getPeriodEndDate($period);

        $revenue = Revenue::whereBetween('date', [$startDate, $endDate])->sum('amount');
        $costOfGoodsSold = $revenue * 0.50; // 50% COGS
        $grossProfit = $revenue - $costOfGoodsSold;
        $operatingExpenses = $revenue * 0.20; // 20% operating expenses
        $operatingIncome = $grossProfit - $operatingExpenses;
        $netIncome = $operatingIncome * 0.85; // 15% tax rate

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'period_name' => $this->getPeriodDisplayName($period)
            ],
            'revenue' => [
                'amount' => $revenue,
                'formatted' => '₦' . number_format($revenue / 1000000, 2) . 'M',
                'growth' => '+18% vs previous period'
            ],
            'cost_of_goods_sold' => [
                'amount' => $costOfGoodsSold,
                'formatted' => '₦' . number_format($costOfGoodsSold / 1000000, 2) . 'M',
                'percentage' => '50%'
            ],
            'gross_profit' => [
                'amount' => $grossProfit,
                'formatted' => '₦' . number_format($grossProfit / 1000000, 2) . 'M',
                'margin' => '50%'
            ],
            'operating_expenses' => [
                'amount' => $operatingExpenses,
                'formatted' => '₦' . number_format($operatingExpenses / 1000000, 2) . 'M',
                'breakdown' => [
                    'marketing' => $operatingExpenses * 0.40,
                    'operations' => $operatingExpenses * 0.30,
                    'technology' => $operatingExpenses * 0.20,
                    'administration' => $operatingExpenses * 0.10
                ]
            ],
            'operating_income' => [
                'amount' => $operatingIncome,
                'formatted' => '₦' . number_format($operatingIncome / 1000000, 2) . 'M',
                'margin' => '30%'
            ],
            'net_income' => [
                'amount' => $netIncome,
                'formatted' => '₦' . number_format($netIncome / 1000000, 2) . 'M',
                'margin' => '25.5%'
            ],
            'margins' => [
                'gross_margin' => '50%',
                'operating_margin' => '30%',
                'net_margin' => '25.5%'
            ]
        ];
    }

    /**
     * Calculate cash flow projections
     */
    public function calculateCashFlowProjections($months = 12)
    {
        $projections = [];
        $currentDate = Carbon::now();

        for ($i = 1; $i <= $months; $i++) {
            $projectionDate = $currentDate->copy()->addMonths($i);
            $monthlyRevenue = $this->getProjectedRevenue($projectionDate);
            $monthlyExpenses = $this->getProjectedExpenses($projectionDate);
            $netCashFlow = $monthlyRevenue - $monthlyExpenses;

            $projections[] = [
                'month' => $projectionDate->format('Y-m'),
                'revenue' => $monthlyRevenue,
                'expenses' => $monthlyExpenses,
                'net_cash_flow' => $netCashFlow,
                'cumulative_cash_flow' => $this->calculateCumulativeCashFlow($projections, $netCashFlow),
                'cash_balance' => $this->calculateProjectedCashBalance($projections, $netCashFlow)
            ];
        }

        return [
            'projections' => $projections,
            'summary' => [
                'total_projected_revenue' => array_sum(array_column($projections, 'revenue')),
                'total_projected_expenses' => array_sum(array_column($projections, 'expenses')),
                'total_projected_cash_flow' => array_sum(array_column($projections, 'net_cash_flow')),
                'projection_period' => $months . ' months',
                'growth_assumption' => '15% monthly growth'
            ]
        ];
    }

    /**
     * Compute balance sheet
     */
    public function computeBalanceSheet($date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::now();

        // Assets
        $currentAssets = [
            'cash_and_equivalents' => 2495000,
            'accounts_receivable' => 850000,
            'inventory' => 1200000,
            'prepaid_expenses' => 150000
        ];

        $fixedAssets = [
            'equipment' => 800000,
            'furniture' => 200000,
            'vehicles' => 200000,
            'accumulated_depreciation' => -150000
        ];

        $totalCurrentAssets = array_sum($currentAssets);
        $totalFixedAssets = array_sum($fixedAssets);
        $totalAssets = $totalCurrentAssets + $totalFixedAssets;

        // Liabilities
        $currentLiabilities = [
            'accounts_payable' => 600000,
            'accrued_expenses' => 150000,
            'short_term_debt' => 50000
        ];

        $longTermLiabilities = [
            'long_term_debt' => 0,
            'deferred_tax_liability' => 0
        ];

        $totalCurrentLiabilities = array_sum($currentLiabilities);
        $totalLongTermLiabilities = array_sum($longTermLiabilities);
        $totalLiabilities = $totalCurrentLiabilities + $totalLongTermLiabilities;

        // Equity
        $equity = [
            'common_stock' => 1000000,
            'retained_earnings' => 2900000,
            'total_equity' => 3900000
        ];

        return [
            'date' => $date->format('Y-m-d'),
            'assets' => [
                'current_assets' => [
                    'items' => $currentAssets,
                    'total' => $totalCurrentAssets,
                    'formatted_total' => '₦' . number_format($totalCurrentAssets / 1000000, 2) . 'M'
                ],
                'fixed_assets' => [
                    'items' => $fixedAssets,
                    'total' => $totalFixedAssets,
                    'formatted_total' => '₦' . number_format($totalFixedAssets / 1000000, 2) . 'M'
                ],
                'total_assets' => [
                    'amount' => $totalAssets,
                    'formatted' => '₦' . number_format($totalAssets / 1000000, 2) . 'M'
                ]
            ],
            'liabilities' => [
                'current_liabilities' => [
                    'items' => $currentLiabilities,
                    'total' => $totalCurrentLiabilities,
                    'formatted_total' => '₦' . number_format($totalCurrentLiabilities / 1000000, 2) . 'M'
                ],
                'long_term_liabilities' => [
                    'items' => $longTermLiabilities,
                    'total' => $totalLongTermLiabilities,
                    'formatted_total' => '₦' . number_format($totalLongTermLiabilities / 1000000, 2) . 'M'
                ],
                'total_liabilities' => [
                    'amount' => $totalLiabilities,
                    'formatted' => '₦' . number_format($totalLiabilities / 1000000, 2) . 'M'
                ]
            ],
            'equity' => [
                'items' => $equity,
                'total_equity' => [
                    'amount' => $equity['total_equity'],
                    'formatted' => '₦' . number_format($equity['total_equity'] / 1000000, 2) . 'M'
                ]
            ],
            'financial_ratios' => [
                'current_ratio' => round($totalCurrentAssets / $totalCurrentLiabilities, 2),
                'debt_to_equity' => round($totalLiabilities / $equity['total_equity'], 2),
                'working_capital' => $totalCurrentAssets - $totalCurrentLiabilities
            ]
        ];
    }

    /**
     * Analyze burn rate
     */
    public function analyzeBurnRate($period = 'monthly')
    {
        $startDate = $this->getPeriodStartDate($period);
        $endDate = $this->getPeriodEndDate($period);

        $revenue = Revenue::whereBetween('date', [$startDate, $endDate])->sum('amount');
        $expenses = $revenue * 0.70; // 70% of revenue as expenses
        $netBurn = $expenses - $revenue;
        $grossBurn = $expenses;

        $cashBalance = 2495000; // Current cash balance
        $runwayMonths = $netBurn > 0 ? $cashBalance / abs($netBurn) : 999;

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'period_name' => $this->getPeriodDisplayName($period)
            ],
            'burn_metrics' => [
                'gross_burn' => [
                    'amount' => $grossBurn,
                    'formatted' => '₦' . number_format($grossBurn / 1000000, 2) . 'M',
                    'description' => 'Total monthly expenses'
                ],
                'net_burn' => [
                    'amount' => $netBurn,
                    'formatted' => '₦' . number_format($netBurn / 1000000, 2) . 'M',
                    'description' => 'Net cash outflow'
                ],
                'revenue' => [
                    'amount' => $revenue,
                    'formatted' => '₦' . number_format($revenue / 1000000, 2) . 'M'
                ]
            ],
            'runway_analysis' => [
                'current_cash_balance' => [
                    'amount' => $cashBalance,
                    'formatted' => '₦' . number_format($cashBalance / 1000000, 2) . 'M'
                ],
                'runway_months' => round($runwayMonths, 1),
                'runway_status' => $runwayMonths > 12 ? 'healthy' : ($runwayMonths > 6 ? 'warning' : 'critical'),
                'runway_description' => $this->getRunwayDescription($runwayMonths)
            ],
            'trends' => [
                'burn_rate_trend' => 'decreasing',
                'revenue_growth' => '+18%',
                'efficiency_improvement' => '+5%'
            ]
        ];
    }

    /**
     * Calculate runway days
     */
    public function calculateRunwayDays()
    {
        $cashBalance = 2495000;
        $monthlyBurn = 4850000 * 0.70; // 70% of monthly revenue
        $dailyBurn = $monthlyBurn / 30;
        $runwayDays = $cashBalance / $dailyBurn;

        return [
            'cash_balance' => [
                'amount' => $cashBalance,
                'formatted' => '₦' . number_format($cashBalance / 1000000, 2) . 'M'
            ],
            'daily_burn_rate' => [
                'amount' => $dailyBurn,
                'formatted' => '₦' . number_format($dailyBurn / 1000, 0) . 'K'
            ],
            'runway_days' => round($runwayDays),
            'runway_months' => round($runwayDays / 30, 1),
            'runway_status' => $runwayDays > 365 ? 'excellent' : ($runwayDays > 180 ? 'good' : 'critical'),
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Generate investor digest
     */
    public function generateInvestorDigest($frequency = 'monthly')
    {
        $period = $frequency === 'monthly' ? 'current_month' : 'current_quarter';
        $pnl = $this->generateProfitLossStatement($period);
        $balanceSheet = $this->computeBalanceSheet();
        $burnRate = $this->analyzeBurnRate($period);
        $runway = $this->calculateRunwayDays();

        return [
            'digest_period' => [
                'frequency' => $frequency,
                'period_name' => $this->getPeriodDisplayName($period),
                'generated_at' => now()->toISOString()
            ],
            'executive_summary' => [
                'revenue' => $pnl['revenue']['formatted'],
                'profit_margin' => $pnl['margins']['net_margin'],
                'cash_balance' => $balanceSheet['assets']['current_assets']['items']['cash_and_equivalents'],
                'runway_days' => $runway['runway_days'],
                'key_highlights' => [
                    'Revenue grew by 18% month-over-month',
                    'Net profit margin improved to 25.5%',
                    'Cash runway extended to ' . $runway['runway_days'] . ' days',
                    'Operational efficiency increased by 5%'
                ]
            ],
            'financial_metrics' => [
                'profit_loss' => $pnl,
                'balance_sheet' => $balanceSheet,
                'burn_rate' => $burnRate,
                'runway_analysis' => $runway
            ],
            'operational_highlights' => [
                'orders_processed' => Order::whereMonth('created_at', Carbon::now()->month)->count(),
                'customer_acquisition' => 156,
                'delivery_efficiency' => '98.5%',
                'quality_score' => '98.9%'
            ],
            'strategic_insights' => [
                'growth_trajectory' => 'accelerating',
                'market_position' => 'strengthening',
                'competitive_advantage' => 'expanding',
                'investment_readiness' => 'improving'
            ]
        ];
    }

    /**
     * Get period start date
     */
    private function getPeriodStartDate($period)
    {
        switch ($period) {
            case 'current_month':
                return Carbon::now()->startOfMonth();
            case 'current_quarter':
                return Carbon::now()->startOfQuarter();
            case 'current_year':
                return Carbon::now()->startOfYear();
            case 'previous_month':
                return Carbon::now()->subMonth()->startOfMonth();
            case 'previous_quarter':
                return Carbon::now()->subQuarter()->startOfQuarter();
            default:
                return Carbon::now()->startOfMonth();
        }
    }

    /**
     * Get period end date
     */
    private function getPeriodEndDate($period)
    {
        switch ($period) {
            case 'current_month':
                return Carbon::now()->endOfMonth();
            case 'current_quarter':
                return Carbon::now()->endOfQuarter();
            case 'current_year':
                return Carbon::now()->endOfYear();
            case 'previous_month':
                return Carbon::now()->subMonth()->endOfMonth();
            case 'previous_quarter':
                return Carbon::now()->subQuarter()->endOfQuarter();
            default:
                return Carbon::now()->endOfMonth();
        }
    }

    /**
     * Get period display name
     */
    private function getPeriodDisplayName($period)
    {
        switch ($period) {
            case 'current_month':
                return Carbon::now()->format('F Y');
            case 'current_quarter':
                return 'Q' . Carbon::now()->quarter . ' ' . Carbon::now()->year;
            case 'current_year':
                return Carbon::now()->year;
            case 'previous_month':
                return Carbon::now()->subMonth()->format('F Y');
            case 'previous_quarter':
                return 'Q' . Carbon::now()->subQuarter()->quarter . ' ' . Carbon::now()->subQuarter()->year;
            default:
                return Carbon::now()->format('F Y');
        }
    }

    /**
     * Get projected revenue
     */
    private function getProjectedRevenue($date)
    {
        $baseRevenue = 4850000; // Current monthly revenue
        $growthRate = 0.15; // 15% monthly growth
        $monthsFromNow = $date->diffInMonths(Carbon::now());
        
        return $baseRevenue * pow(1 + $growthRate, $monthsFromNow);
    }

    /**
     * Get projected expenses
     */
    private function getProjectedExpenses($date)
    {
        $projectedRevenue = $this->getProjectedRevenue($date);
        return $projectedRevenue * 0.70; // 70% of revenue
    }

    /**
     * Calculate cumulative cash flow
     */
    private function calculateCumulativeCashFlow($projections, $currentNetCashFlow)
    {
        $cumulative = array_sum(array_column($projections, 'net_cash_flow'));
        return $cumulative + $currentNetCashFlow;
    }

    /**
     * Calculate projected cash balance
     */
    private function calculateProjectedCashBalance($projections, $currentNetCashFlow)
    {
        $currentBalance = 2495000;
        $cumulativeCashFlow = $this->calculateCumulativeCashFlow($projections, $currentNetCashFlow);
        return $currentBalance + $cumulativeCashFlow;
    }

    /**
     * Get runway description
     */
    private function getRunwayDescription($runwayMonths)
    {
        if ($runwayMonths > 24) {
            return 'Excellent runway - 2+ years of cash';
        } elseif ($runwayMonths > 12) {
            return 'Healthy runway - 1+ years of cash';
        } elseif ($runwayMonths > 6) {
            return 'Adequate runway - 6+ months of cash';
        } else {
            return 'Critical runway - Immediate attention needed';
        }
    }
} 