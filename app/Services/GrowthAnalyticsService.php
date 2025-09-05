<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Revenue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GrowthAnalyticsService
{
    /**
     * Calculate ROAS for a specific platform
     */
    public function calculateROAS($platform, $period = 'current_month')
    {
        $startDate = $this->getPeriodStartDate($period);
        $endDate = $this->getPeriodEndDate($period);

        // Simulate platform-specific data
        $platformData = [
            'facebook' => [
                'spend' => 80000,
                'revenue' => 336000,
                'roas' => 4.2,
                'conversions' => 28,
                'ctr' => '2.1%'
            ],
            'tiktok' => [
                'spend' => 65000,
                'revenue' => 253500,
                'roas' => 3.9,
                'conversions' => 22,
                'ctr' => '1.8%'
            ],
            'google' => [
                'spend' => 50000,
                'revenue' => 255000,
                'roas' => 5.1,
                'conversions' => 18,
                'ctr' => '3.2%'
            ],
            'youtube' => [
                'spend' => 32000,
                'revenue' => 115200,
                'roas' => 3.6,
                'conversions' => 12,
                'ctr' => '2.5%'
            ],
            'whatsapp' => [
                'spend' => 12000,
                'revenue' => 74400,
                'roas' => 6.2,
                'conversions' => 8,
                'ctr' => '4.1%'
            ]
        ];

        $data = $platformData[$platform] ?? [
            'spend' => 0,
            'revenue' => 0,
            'roas' => 0,
            'conversions' => 0,
            'ctr' => '0%'
        ];

        return [
            'platform' => $platform,
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'period_name' => $this->getPeriodDisplayName($period)
            ],
            'metrics' => [
                'spend' => [
                    'amount' => $data['spend'],
                    'formatted' => '₦' . number_format($data['spend'] / 1000, 0) . 'K'
                ],
                'revenue' => [
                    'amount' => $data['revenue'],
                    'formatted' => '₦' . number_format($data['revenue'] / 1000, 0) . 'K'
                ],
                'roas' => [
                    'value' => $data['roas'],
                    'formatted' => $data['roas'] . 'x',
                    'performance' => $this->getROASPerformance($data['roas'])
                ],
                'conversions' => $data['conversions'],
                'ctr' => $data['ctr']
            ],
            'trends' => [
                'roas_trend' => '+0.3x vs previous period',
                'spend_trend' => '+12% vs previous period',
                'revenue_trend' => '+18% vs previous period'
            ],
            'benchmarks' => [
                'industry_average' => 3.5,
                'target' => 4.0,
                'previous_period' => $data['roas'] - 0.3
            ]
        ];
    }

    /**
     * Analyze CAC by channel
     */
    public function analyzeCACByChannel()
    {
        $channelData = [
            'paid_ads' => [
                'cac' => 5300,
                'ltv' => 18500,
                'ltv_cac_ratio' => 3.5,
                'conversions' => 45,
                'spend' => 239000
            ],
            'organic_social' => [
                'cac' => 1200,
                'ltv' => 16500,
                'ltv_cac_ratio' => 13.8,
                'conversions' => 38,
                'spend' => 45600
            ],
            'referrals' => [
                'cac' => 3000,
                'ltv' => 19500,
                'ltv_cac_ratio' => 6.5,
                'conversions' => 28,
                'spend' => 84000
            ],
            'direct' => [
                'cac' => 800,
                'ltv' => 14500,
                'ltv_cac_ratio' => 18.1,
                'conversions' => 25,
                'spend' => 20000
            ],
            'email' => [
                'cac' => 500,
                'ltv' => 17500,
                'ltv_cac_ratio' => 35.0,
                'conversions' => 20,
                'spend' => 10000
            ]
        ];

        $analysis = [];
        foreach ($channelData as $channel => $data) {
            $analysis[$channel] = [
                'cac' => [
                    'amount' => $data['cac'],
                    'formatted' => '₦' . number_format($data['cac'], 0),
                    'performance' => $this->getCACPerformance($data['cac'])
                ],
                'ltv' => [
                    'amount' => $data['ltv'],
                    'formatted' => '₦' . number_format($data['ltv'], 0)
                ],
                'ltv_cac_ratio' => [
                    'value' => $data['ltv_cac_ratio'],
                    'performance' => $this->getLTVCACPerformance($data['ltv_cac_ratio'])
                ],
                'conversions' => $data['conversions'],
                'spend' => [
                    'amount' => $data['spend'],
                    'formatted' => '₦' . number_format($data['spend'] / 1000, 0) . 'K'
                ]
            ];
        }

        return [
            'channel_analysis' => $analysis,
            'summary' => [
                'average_cac' => array_sum(array_column($channelData, 'cac')) / count($channelData),
                'best_performing_channel' => 'email',
                'most_efficient_channel' => 'direct',
                'highest_ltv_channel' => 'referrals'
            ],
            'optimization_opportunities' => [
                'increase_email_budget' => 'Potential 25% revenue increase',
                'optimize_paid_ads' => 'Potential 15% CAC reduction',
                'scale_referral_program' => 'Potential 30% volume increase'
            ],
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Compute LTV metrics
     */
    public function computeLTVMetrics()
    {
        $ltvData = [
            'average_ltv' => 17500,
            'ltv_by_segment' => [
                'new_customers' => 12500,
                'repeat_customers' => 18500,
                'vip_customers' => 25000
            ],
            'ltv_trends' => [
                'month_1' => 8500,
                'month_3' => 12500,
                'month_6' => 16500,
                'month_12' => 18500
            ],
            'retention_impact' => [
                'month_1_retention' => 85,
                'month_3_retention' => 67,
                'month_6_retention' => 52,
                'month_12_retention' => 38
            ]
        ];

        $ltvGrowth = (($ltvData['average_ltv'] - 16500) / 16500) * 100;

        return [
            'ltv_summary' => [
                'average_ltv' => [
                    'amount' => $ltvData['average_ltv'],
                    'formatted' => '₦' . number_format($ltvData['average_ltv'], 0),
                    'growth' => '+' . round($ltvGrowth, 1) . '%'
                ],
                'ltv_growth_rate' => round($ltvGrowth, 1),
                'ltv_payback_period' => '4.2 months',
                'ltv_forecast' => '₦22,500 (12 months)'
            ],
            'ltv_by_segment' => $ltvData['ltv_by_segment'],
            'ltv_trends' => $ltvData['ltv_trends'],
            'retention_analysis' => [
                'retention_rates' => $ltvData['retention_impact'],
                'churn_analysis' => [
                    'month_1_churn' => 15,
                    'month_3_churn' => 33,
                    'month_6_churn' => 48,
                    'month_12_churn' => 62
                ],
                'retention_strategies' => [
                    'improve_onboarding' => 'Potential 10% retention increase',
                    'enhance_customer_support' => 'Potential 15% retention increase',
                    'implement_loyalty_program' => 'Potential 20% retention increase'
                ]
            ],
            'optimization_opportunities' => [
                'increase_average_order_value' => 'Potential 15% LTV increase',
                'improve_retention' => 'Potential 25% LTV increase',
                'enhance_customer_experience' => 'Potential 20% LTV increase'
            ],
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Track marketing efficiency
     */
    public function trackMarketingEfficiency()
    {
        $efficiencyMetrics = [
            'overall_efficiency_score' => 87,
            'channel_efficiency' => [
                'paid_ads' => [
                    'efficiency_score' => 85,
                    'roas' => 4.6,
                    'cac' => 5300,
                    'conversion_rate' => '3.8%'
                ],
                'organic_social' => [
                    'efficiency_score' => 92,
                    'roas' => 'infinite',
                    'cac' => 1200,
                    'conversion_rate' => '2.1%'
                ],
                'referrals' => [
                    'efficiency_score' => 88,
                    'roas' => 8.2,
                    'cac' => 3000,
                    'conversion_rate' => '4.5%'
                ],
                'direct' => [
                    'efficiency_score' => 95,
                    'roas' => 'infinite',
                    'cac' => 800,
                    'conversion_rate' => '1.8%'
                ],
                'email' => [
                    'efficiency_score' => 94,
                    'roas' => 47.0,
                    'cac' => 500,
                    'conversion_rate' => '2.1%'
                ]
            ],
            'campaign_performance' => [
                'active_campaigns' => 8,
                'performing_campaigns' => 6,
                'underperforming_campaigns' => 2,
                'average_campaign_roas' => 4.6
            ],
            'budget_allocation' => [
                'paid_ads' => 65,
                'organic_social' => 15,
                'referrals' => 12,
                'direct' => 5,
                'email' => 3
            ]
        ];

        return [
            'efficiency_summary' => [
                'overall_score' => $efficiencyMetrics['overall_efficiency_score'],
                'grade' => $this->getEfficiencyGrade($efficiencyMetrics['overall_efficiency_score']),
                'trend' => 'improving',
                'target' => 90
            ],
            'channel_efficiency' => $efficiencyMetrics['channel_efficiency'],
            'campaign_performance' => $efficiencyMetrics['campaign_performance'],
            'budget_allocation' => $efficiencyMetrics['budget_allocation'],
            'optimization_recommendations' => [
                'reallocate_budget' => 'Shift 10% from paid ads to email',
                'optimize_underperforming' => 'Improve 2 underperforming campaigns',
                'scale_high_performing' => 'Increase budget for top 3 campaigns'
            ],
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Generate growth projections
     */
    public function generateGrowthProjections()
    {
        $currentMetrics = [
            'monthly_revenue' => 4850000,
            'customer_acquisition' => 156,
            'growth_rate' => 18,
            'churn_rate' => 3.2
        ];

        $projections = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyGrowth = 0.15; // 15% monthly growth
            $projectedRevenue = $currentMetrics['monthly_revenue'] * pow(1 + $monthlyGrowth, $i);
            $projectedCustomers = $currentMetrics['customer_acquisition'] * pow(1 + $monthlyGrowth, $i);
            
            $projections[] = [
                'month' => $i,
                'revenue' => $projectedRevenue,
                'revenue_formatted' => '₦' . number_format($projectedRevenue / 1000000, 2) . 'M',
                'customers' => round($projectedCustomers),
                'growth_rate' => round($monthlyGrowth * 100, 1) . '%'
            ];
        }

        return [
            'current_metrics' => $currentMetrics,
            'projections' => $projections,
            'growth_assumptions' => [
                'monthly_growth_rate' => '15%',
                'churn_rate' => '3.2%',
                'market_expansion' => '2 new states per quarter',
                'product_launches' => '1 new product per quarter'
            ],
            'scenarios' => [
                'conservative' => [
                    'growth_rate' => '10%',
                    'annual_revenue' => '₦85M',
                    'customers' => 2800
                ],
                'moderate' => [
                    'growth_rate' => '15%',
                    'annual_revenue' => '₦120M',
                    'customers' => 3800
                ],
                'aggressive' => [
                    'growth_rate' => '20%',
                    'annual_revenue' => '₦180M',
                    'customers' => 5200
                ]
            ],
            'key_drivers' => [
                'market_expansion' => 'Geographic expansion to new states',
                'product_development' => 'New product launches',
                'marketing_optimization' => 'Improved ROAS and CAC',
                'operational_efficiency' => 'Automation and process improvements'
            ],
            'risks' => [
                'market_saturation' => 'Medium risk - 25% probability',
                'competition_increase' => 'Low risk - 15% probability',
                'regulatory_changes' => 'Low risk - 10% probability',
                'economic_downturn' => 'Medium risk - 20% probability'
            ],
            'last_updated' => now()->toISOString()
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
     * Get ROAS performance
     */
    private function getROASPerformance($roas)
    {
        if ($roas >= 5.0) return 'excellent';
        if ($roas >= 4.0) return 'good';
        if ($roas >= 3.0) return 'average';
        return 'poor';
    }

    /**
     * Get CAC performance
     */
    private function getCACPerformance($cac)
    {
        if ($cac <= 2000) return 'excellent';
        if ($cac <= 4000) return 'good';
        if ($cac <= 6000) return 'average';
        return 'poor';
    }

    /**
     * Get LTV/CAC performance
     */
    private function getLTVCACPerformance($ratio)
    {
        if ($ratio >= 5.0) return 'excellent';
        if ($ratio >= 3.0) return 'good';
        if ($ratio >= 1.5) return 'average';
        return 'poor';
    }

    /**
     * Get efficiency grade
     */
    private function getEfficiencyGrade($score)
    {
        if ($score >= 90) return 'A+';
        if ($score >= 85) return 'A';
        if ($score >= 80) return 'B+';
        if ($score >= 75) return 'B';
        if ($score >= 70) return 'C+';
        return 'C';
    }
} 