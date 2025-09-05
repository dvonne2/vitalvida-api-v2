<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Investor;
use App\Models\Order;
use App\Models\Customer;
use App\Models\MarketingCampaign;
use Carbon\Carbon;

class NeilGrowthController extends Controller
{
    /**
     * Get Neil Growth dashboard
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_NEIL_GROWTH) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Neil Growth access required.'
                ], 403);
            }

            $data = [
                'neil_score' => $this->getNeilScore(),
                'key_metrics_summary' => $this->getKeyMetricsSummary(),
                'paid_ads_performance' => $this->getPaidAdsPerformance(),
                'weekly_report_ready' => $this->getWeeklyReportReady(),
                'growth_trends' => $this->getGrowthTrends(),
                'marketing_channels' => $this->getMarketingChannels(),
                'customer_acquisition_metrics' => $this->getCustomerAcquisitionMetrics(),
                'campaign_performance' => $this->getCampaignPerformance(),
                'revenue_attribution' => $this->getRevenueAttribution()
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
                'message' => 'Failed to load Neil Growth dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Neil score
     */
    private function getNeilScore()
    {
        return [
            'overall_score' => '97/100',
            'performance_grade' => 'A+',
            'areas' => [
                'paid_ads' => 95,
                'organic_social' => 92,
                'seo' => 88,
                'affiliates' => 94,
                'email_whatsapp' => 98,
                'content_hub' => 89
            ],
            'score_breakdown' => [
                'paid_ads' => [
                    'score' => 95,
                    'grade' => 'A+',
                    'performance' => 'Excellent ROAS across all platforms',
                    'improvement' => '+3 points vs last week'
                ],
                'organic_social' => [
                    'score' => 92,
                    'grade' => 'A',
                    'performance' => 'Strong organic reach and engagement',
                    'improvement' => '+5 points vs last week'
                ],
                'seo' => [
                    'score' => 88,
                    'grade' => 'B+',
                    'performance' => 'Good organic traffic growth',
                    'improvement' => '+2 points vs last week'
                ],
                'affiliates' => [
                    'score' => 94,
                    'grade' => 'A',
                    'performance' => 'High-performing affiliate network',
                    'improvement' => '+1 point vs last week'
                ],
                'email_whatsapp' => [
                    'score' => 98,
                    'grade' => 'A+',
                    'performance' => 'Exceptional conversion rates',
                    'improvement' => '+2 points vs last week'
                ],
                'content_hub' => [
                    'score' => 89,
                    'grade' => 'B+',
                    'performance' => 'Strong content engagement',
                    'improvement' => '+4 points vs last week'
                ]
            ]
        ];
    }

    /**
     * Get key metrics summary
     */
    private function getKeyMetricsSummary()
    {
        return [
            'total_ad_spend' => 239000,
            'total_ad_spend_formatted' => '₦239K',
            'avg_roas' => '4.6x',
            'organic_sessions' => 12500,
            'conversion_metrics' => '+23.4%',
            'customer_acquisition_cost' => 3200,
            'customer_acquisition_cost_formatted' => '₦3,200',
            'lifetime_value' => 18500,
            'lifetime_value_formatted' => '₦18,500',
            'ltv_cac_ratio' => '5.8x',
            'monthly_recurring_revenue' => 4850000,
            'monthly_recurring_revenue_formatted' => '₦4.85M',
            'growth_rate' => '+18% week-over-week'
        ];
    }

    /**
     * Get paid ads performance
     */
    private function getPaidAdsPerformance()
    {
        return [
            'roas_by_platform' => [
                'facebook' => 4.2,
                'tiktok' => 3.9,
                'google' => 5.1,
                'youtube' => 3.6,
                'whatsapp' => 6.2
            ],
            'spend_distribution' => [
                'facebook' => 80000,
                'facebook_formatted' => '₦80K',
                'tiktok' => 65000,
                'tiktok_formatted' => '₦65K',
                'google' => 50000,
                'google_formatted' => '₦50K',
                'youtube' => 32000,
                'youtube_formatted' => '₦32K',
                'whatsapp' => 12000,
                'whatsapp_formatted' => '₦12K'
            ],
            'platform_performance_table' => [
                [
                    'platform' => 'Facebook',
                    'spend' => 80000,
                    'spend_formatted' => '₦80K',
                    'roas' => '4.2x',
                    'ctr' => '2.1%',
                    'cac' => 6500,
                    'cac_formatted' => '₦6,500',
                    'top_creative' => 'Before/After Story',
                    'performance_grade' => 'A'
                ],
                [
                    'platform' => 'TikTok',
                    'spend' => 65000,
                    'spend_formatted' => '₦65K',
                    'roas' => '3.8x',
                    'ctr' => '1.8%',
                    'cac' => 9800,
                    'cac_formatted' => '₦9,800',
                    'top_creative' => 'Hair Growth Hook',
                    'performance_grade' => 'B+'
                ],
                [
                    'platform' => 'Google',
                    'spend' => 50000,
                    'spend_formatted' => '₦50K',
                    'roas' => '5.1x',
                    'ctr' => '3.2%',
                    'cac' => 7200,
                    'cac_formatted' => '₦7,200',
                    'top_creative' => 'Search Ad 1',
                    'performance_grade' => 'A+'
                ],
                [
                    'platform' => 'YouTube',
                    'spend' => 32000,
                    'spend_formatted' => '₦32K',
                    'roas' => '3.6x',
                    'ctr' => '2.5%',
                    'cac' => 9100,
                    'cac_formatted' => '₦9,100',
                    'top_creative' => 'Demo Video',
                    'performance_grade' => 'B+'
                ],
                [
                    'platform' => 'WhatsApp',
                    'spend' => 12000,
                    'spend_formatted' => '₦12K',
                    'roas' => '6.2x',
                    'ctr' => '4.1%',
                    'cac' => 5800,
                    'cac_formatted' => '₦5,800',
                    'top_creative' => 'Broadcast #7',
                    'performance_grade' => 'A+'
                ]
            ],
            'top_performing_campaigns' => [
                [
                    'campaign_name' => 'Hair Growth Transformation',
                    'platform' => 'Facebook',
                    'roas' => '5.8x',
                    'spend' => 25000,
                    'revenue' => 145000,
                    'conversions' => 12
                ],
                [
                    'campaign_name' => 'Skin Health Boost',
                    'platform' => 'Google',
                    'roas' => '6.2x',
                    'spend' => 18000,
                    'revenue' => 111600,
                    'conversions' => 8
                ],
                [
                    'campaign_name' => 'Wellness Journey',
                    'platform' => 'WhatsApp',
                    'roas' => '7.1x',
                    'spend' => 8000,
                    'revenue' => 56800,
                    'conversions' => 5
                ]
            ]
        ];
    }

    /**
     * Get weekly report ready
     */
    private function getWeeklyReportReady()
    {
        return [
            'title' => 'Weekly Neil Report Ready',
            'description' => 'Complete marketing performance snapshot across all channels',
            'report_period' => 'December 2-8, 2024',
            'generated_at' => now()->format('M j, Y g:i A'),
            'actions' => [
                [
                    'label' => 'Preview Report',
                    'endpoint' => '/api/investor/growth/weekly-preview',
                    'method' => 'GET'
                ],
                [
                    'label' => 'Download Neil Report',
                    'endpoint' => '/api/investor/growth/export-report',
                    'method' => 'GET'
                ],
                [
                    'label' => 'Share with Team',
                    'endpoint' => '/api/investor/growth/share-report',
                    'method' => 'POST'
                ]
            ],
            'report_sections' => [
                'executive_summary' => 'Complete',
                'paid_ads_performance' => 'Complete',
                'organic_growth_metrics' => 'Complete',
                'customer_acquisition' => 'Complete',
                'revenue_attribution' => 'Complete',
                'growth_forecasts' => 'Complete'
            ]
        ];
    }

    /**
     * Get growth trends
     */
    private function getGrowthTrends()
    {
        return [
            'customer_acquisition' => [
                'new_customers_week' => 156,
                'repeat_rate' => '67%',
                'churn_rate' => '3.2%',
                'net_customer_growth' => '+89 customers',
                'acquisition_channels' => [
                    'paid_ads' => 45,
                    'organic_social' => 38,
                    'referrals' => 28,
                    'direct' => 25,
                    'email' => 20
                ]
            ],
            'revenue_growth' => [
                'week_over_week' => '+18%',
                'month_over_month' => '+34%',
                'growth_trajectory' => 'accelerating',
                'revenue_by_channel' => [
                    'paid_ads' => 1100000,
                    'organic_social' => 850000,
                    'referrals' => 650000,
                    'direct' => 580000,
                    'email' => 470000
                ]
            ],
            'market_expansion' => [
                'new_states_entered' => 2,
                'da_network_growth' => '+15 agents',
                'geographic_penetration' => 'improving',
                'expansion_targets' => [
                    'lagos' => '85% penetration',
                    'abuja' => '72% penetration',
                    'port_harcourt' => '68% penetration',
                    'kano' => '45% penetration'
                ]
            ],
            'product_performance' => [
                'top_product' => 'Moringa Capsules 60ct',
                'growth_rate' => '+23%',
                'market_share' => '12.5%',
                'customer_satisfaction' => '94.5%'
            ]
        ];
    }

    /**
     * Get marketing channels
     */
    private function getMarketingChannels()
    {
        return [
            'paid_advertising' => [
                'total_spend' => 239000,
                'total_revenue' => 1100000,
                'roas' => '4.6x',
                'conversion_rate' => '3.8%',
                'platforms' => ['Facebook', 'TikTok', 'Google', 'YouTube', 'WhatsApp']
            ],
            'organic_social' => [
                'followers_growth' => '+1,250',
                'engagement_rate' => '4.2%',
                'organic_reach' => 12500,
                'conversions' => 38,
                'platforms' => ['Instagram', 'Facebook', 'TikTok', 'YouTube']
            ],
            'content_marketing' => [
                'blog_posts' => 8,
                'video_content' => 12,
                'email_campaigns' => 5,
                'engagement_rate' => '6.8%',
                'conversions' => 20
            ],
            'referral_program' => [
                'active_referrers' => 156,
                'referral_conversions' => 28,
                'commission_paid' => 84000,
                'roi' => '8.2x'
            ],
            'email_marketing' => [
                'subscribers' => 8500,
                'open_rate' => '34.2%',
                'click_rate' => '8.7%',
                'conversion_rate' => '2.1%',
                'revenue' => 470000
            ]
        ];
    }

    /**
     * Get customer acquisition metrics
     */
    private function getCustomerAcquisitionMetrics()
    {
        return [
            'acquisition_funnel' => [
                'awareness' => 12500,
                'consideration' => 3200,
                'intent' => 1800,
                'purchase' => 156,
                'conversion_rate' => '1.25%'
            ],
            'customer_segments' => [
                'new_customers' => [
                    'count' => 156,
                    'percentage' => '67%',
                    'avg_order_value' => 12500,
                    'acquisition_cost' => 3200
                ],
                'repeat_customers' => [
                    'count' => 78,
                    'percentage' => '33%',
                    'avg_order_value' => 18500,
                    'retention_rate' => '74%'
                ]
            ],
            'geographic_distribution' => [
                'lagos' => [
                    'customers' => 89,
                    'percentage' => '38%',
                    'growth_rate' => '+15%'
                ],
                'abuja' => [
                    'customers' => 45,
                    'percentage' => '19%',
                    'growth_rate' => '+22%'
                ],
                'port_harcourt' => [
                    'customers' => 34,
                    'percentage' => '15%',
                    'growth_rate' => '+18%'
                ],
                'other_states' => [
                    'customers' => 66,
                    'percentage' => '28%',
                    'growth_rate' => '+12%'
                ]
            ],
            'acquisition_channels' => [
                'paid_ads' => [
                    'customers' => 45,
                    'percentage' => '29%',
                    'cost_per_acquisition' => 5300,
                    'lifetime_value' => 18500
                ],
                'organic_social' => [
                    'customers' => 38,
                    'percentage' => '24%',
                    'cost_per_acquisition' => 1200,
                    'lifetime_value' => 16500
                ],
                'referrals' => [
                    'customers' => 28,
                    'percentage' => '18%',
                    'cost_per_acquisition' => 3000,
                    'lifetime_value' => 19500
                ],
                'direct' => [
                    'customers' => 25,
                    'percentage' => '16%',
                    'cost_per_acquisition' => 800,
                    'lifetime_value' => 14500
                ],
                'email' => [
                    'customers' => 20,
                    'percentage' => '13%',
                    'cost_per_acquisition' => 500,
                    'lifetime_value' => 17500
                ]
            ]
        ];
    }

    /**
     * Get campaign performance
     */
    private function getCampaignPerformance()
    {
        return [
            'active_campaigns' => [
                [
                    'name' => 'Hair Growth Transformation',
                    'platform' => 'Facebook',
                    'status' => 'active',
                    'spend' => 25000,
                    'revenue' => 145000,
                    'roas' => '5.8x',
                    'conversions' => 12,
                    'ctr' => '2.8%'
                ],
                [
                    'name' => 'Skin Health Boost',
                    'platform' => 'Google',
                    'status' => 'active',
                    'spend' => 18000,
                    'revenue' => 111600,
                    'roas' => '6.2x',
                    'conversions' => 8,
                    'ctr' => '3.5%'
                ],
                [
                    'name' => 'Wellness Journey',
                    'platform' => 'WhatsApp',
                    'status' => 'active',
                    'spend' => 8000,
                    'revenue' => 56800,
                    'roas' => '7.1x',
                    'conversions' => 5,
                    'ctr' => '4.2%'
                ]
            ],
            'campaign_insights' => [
                'best_performing_creative' => 'Before/After Story',
                'highest_roas_platform' => 'WhatsApp (6.2x)',
                'lowest_cac_platform' => 'Email (₦500)',
                'highest_conversion_rate' => 'Google (3.2%)'
            ],
            'optimization_opportunities' => [
                'increase_whatsapp_budget' => 'Potential 25% revenue increase',
                'optimize_google_keywords' => 'Potential 15% cost reduction',
                'scale_facebook_campaigns' => 'Potential 30% volume increase'
            ]
        ];
    }

    /**
     * Get revenue attribution
     */
    private function getRevenueAttribution()
    {
        return [
            'attribution_model' => 'Multi-touch attribution',
            'revenue_by_channel' => [
                'paid_ads' => [
                    'revenue' => 1100000,
                    'percentage' => '38%',
                    'attribution_weight' => '40%'
                ],
                'organic_social' => [
                    'revenue' => 850000,
                    'percentage' => '29%',
                    'attribution_weight' => '25%'
                ],
                'referrals' => [
                    'revenue' => 650000,
                    'percentage' => '22%',
                    'attribution_weight' => '20%'
                ],
                'direct' => [
                    'revenue' => 580000,
                    'percentage' => '20%',
                    'attribution_weight' => '15%'
                ],
                'email' => [
                    'revenue' => 470000,
                    'percentage' => '16%',
                    'attribution_weight' => '10%'
                ]
            ],
            'customer_journey_analysis' => [
                'average_touchpoints' => 3.2,
                'time_to_conversion' => '4.5 days',
                'most_common_path' => 'Social → Paid Ad → Purchase',
                'cross_channel_behavior' => '78% of customers interact with multiple channels'
            ]
        ];
    }

    /**
     * Get weekly report preview
     */
    public function getWeeklyPreview(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_NEIL_GROWTH) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Neil Growth access required.'
                ], 403);
            }

            $preview = [
                'report_title' => 'Weekly Neil Growth Report',
                'period' => 'December 2-8, 2024',
                'executive_summary' => [
                    'overall_performance' => 'Excellent week with 97/100 Neil Score',
                    'key_achievements' => [
                        'ROAS improved to 4.6x across all platforms',
                        'Customer acquisition cost reduced by 12%',
                        'Organic traffic grew by 23.4%',
                        'New customers acquired: 156'
                    ],
                    'growth_metrics' => [
                        'Revenue growth: +18% week-over-week',
                        'Customer growth: +12% week-over-week',
                        'Market expansion: 2 new states entered'
                    ]
                ],
                'detailed_sections' => [
                    'paid_ads_performance' => 'Complete analysis of all platforms',
                    'organic_growth' => 'Social media and SEO performance',
                    'customer_acquisition' => 'Detailed funnel analysis',
                    'revenue_attribution' => 'Multi-touch attribution model'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $preview
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load weekly preview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export growth report
     */
    public function exportReport(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_NEIL_GROWTH) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Neil Growth access required.'
                ], 403);
            }

            $format = $request->get('format', 'pdf');
            $reportData = $this->getDashboard($request)->getData();

            $exportData = [
                'report_type' => 'Neil Growth Report',
                'format' => $format,
                'download_url' => '/api/investor/growth/download-report/' . $format,
                'expires_at' => now()->addHours(24)->toISOString(),
                'file_size' => $format === 'pdf' ? '2.4MB' : '1.8MB',
                'sections_included' => [
                    'Executive Summary',
                    'Paid Ads Performance',
                    'Organic Growth Metrics',
                    'Customer Acquisition',
                    'Revenue Attribution',
                    'Growth Forecasts'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
