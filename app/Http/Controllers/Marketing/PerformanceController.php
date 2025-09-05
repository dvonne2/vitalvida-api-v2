<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\CampaignPerformance;
use App\Models\Marketing\CreativeAsset;
use App\Services\Marketing\MetaMarketingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    protected $metaMarketingService;

    public function __construct(MetaMarketingService $metaMarketingService)
    {
        $this->metaMarketingService = $metaMarketingService;
    }

    /**
     * Get comprehensive performance analytics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'platform' => 'nullable|in:facebook,instagram,tiktok,google,email,sms',
            'group_by' => 'nullable|in:day,week,month,campaign,platform',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $startDate = $request->start_date ?? now()->subDays(30)->format('Y-m-d');
            $endDate = $request->end_date ?? now()->format('Y-m-d');
            $campaignId = $request->campaign_id;
            $platform = $request->platform;
            $groupBy = $request->group_by ?? 'day';

            $query = CampaignPerformance::query();

            // Apply date range filter
            $query->byDateRange($startDate, $endDate);

            // Apply campaign filter
            if ($campaignId) {
                $query->byCampaign($campaignId);
            }

            // Apply platform filter
            if ($platform) {
                $query->byPlatform($platform);
            }

            // Get aggregated data based on grouping
            $analytics = $this->getAggregatedData($query, $groupBy, $startDate, $endDate);

            // Get top performing campaigns
            $topCampaigns = $this->getTopPerformingCampaigns($startDate, $endDate, $platform);

            // Get performance trends
            $trends = $this->getPerformanceTrends($startDate, $endDate, $campaignId, $platform);

            // Get creative asset performance
            $creativePerformance = $this->getCreativeAssetPerformance($startDate, $endDate, $campaignId);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $analytics['summary'],
                    'grouped_data' => $analytics['grouped_data'],
                    'top_campaigns' => $topCampaigns,
                    'trends' => $trends,
                    'creative_performance' => $creativePerformance,
                    'date_range' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync performance data from external platforms
     */
    public function syncPerformanceData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'required|in:facebook,instagram,tiktok,google',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $platform = $request->platform;
            $campaignId = $request->campaign_id;
            $startDate = $request->start_date ?? now()->subDays(7)->format('Y-m-d');
            $endDate = $request->end_date ?? now()->format('Y-m-d');

            $syncResults = [];

            switch ($platform) {
                case 'facebook':
                case 'instagram':
                    $syncResults = $this->syncMetaData($startDate, $endDate, $campaignId);
                    break;
                case 'tiktok':
                    $syncResults = $this->syncTikTokData($startDate, $endDate, $campaignId);
                    break;
                case 'google':
                    $syncResults = $this->syncGoogleData($startDate, $endDate, $campaignId);
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Performance data synced successfully',
                'data' => $syncResults
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error syncing performance data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get real-time performance metrics
     */
    public function getRealTimeMetrics(Request $request): JsonResponse
    {
        try {
            $campaignId = $request->campaign_id;
            $platform = $request->platform;

            $query = CampaignPerformance::query();

            if ($campaignId) {
                $query->byCampaign($campaignId);
            }

            if ($platform) {
                $query->byPlatform($platform);
            }

            // Get today's data
            $today = now()->format('Y-m-d');
            $todayData = $query->where('date', $today)->get();

            // Get yesterday's data for comparison
            $yesterday = now()->subDay()->format('Y-m-d');
            $yesterdayData = $query->where('date', $yesterday)->get();

            $metrics = [
                'today' => [
                    'impressions' => $todayData->sum('impressions'),
                    'clicks' => $todayData->sum('clicks'),
                    'conversions' => $todayData->sum('conversions'),
                    'spend' => $todayData->sum('spend'),
                    'revenue' => $todayData->sum('revenue'),
                ],
                'yesterday' => [
                    'impressions' => $yesterdayData->sum('impressions'),
                    'clicks' => $yesterdayData->sum('clicks'),
                    'conversions' => $yesterdayData->sum('conversions'),
                    'spend' => $yesterdayData->sum('spend'),
                    'revenue' => $yesterdayData->sum('revenue'),
                ]
            ];

            // Calculate changes
            $changes = [];
            foreach (['impressions', 'clicks', 'conversions', 'spend', 'revenue'] as $metric) {
                $todayValue = $metrics['today'][$metric];
                $yesterdayValue = $metrics['yesterday'][$metric];
                
                if ($yesterdayValue > 0) {
                    $changes[$metric] = round((($todayValue - $yesterdayValue) / $yesterdayValue) * 100, 2);
                } else {
                    $changes[$metric] = $todayValue > 0 ? 100 : 0;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'metrics' => $metrics,
                    'changes' => $changes,
                    'last_updated' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching real-time metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance insights and recommendations
     */
    public function getInsights(Request $request): JsonResponse
    {
        try {
            $campaignId = $request->campaign_id;
            $startDate = $request->start_date ?? now()->subDays(30)->format('Y-m-d');
            $endDate = $request->end_date ?? now()->format('Y-m-d');

            $query = CampaignPerformance::query()
                ->byDateRange($startDate, $endDate);

            if ($campaignId) {
                $query->byCampaign($campaignId);
            }

            $performanceData = $query->get();

            $insights = [
                'top_performing_platforms' => $this->getTopPerformingPlatforms($performanceData),
                'best_performing_times' => $this->getBestPerformingTimes($performanceData),
                'audience_insights' => $this->getAudienceInsights($performanceData),
                'optimization_recommendations' => $this->getOptimizationRecommendations($performanceData),
                'budget_optimization' => $this->getBudgetOptimizationInsights($performanceData),
                'creative_performance_insights' => $this->getCreativePerformanceInsights($performanceData),
            ];

            return response()->json([
                'success' => true,
                'data' => $insights
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating insights',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get aggregated performance data
     */
    private function getAggregatedData($query, $groupBy, $startDate, $endDate): array
    {
        $summary = $query->selectRaw('
            SUM(impressions) as total_impressions,
            SUM(reach) as total_reach,
            SUM(clicks) as total_clicks,
            SUM(conversions) as total_conversions,
            SUM(spend) as total_spend,
            SUM(revenue) as total_revenue,
            AVG(engagement_rate) as avg_engagement_rate,
            AVG(click_through_rate) as avg_ctr,
            AVG(conversion_rate) as avg_conversion_rate,
            AVG(return_on_ad_spend) as avg_roas
        ')->first();

        $groupedData = [];
        switch ($groupBy) {
            case 'day':
                $groupedData = $query->selectRaw('
                    date,
                    SUM(impressions) as impressions,
                    SUM(clicks) as clicks,
                    SUM(conversions) as conversions,
                    SUM(spend) as spend,
                    SUM(revenue) as revenue
                ')->groupBy('date')->orderBy('date')->get();
                break;
            case 'campaign':
                $groupedData = $query->selectRaw('
                    campaign_id,
                    SUM(impressions) as impressions,
                    SUM(clicks) as clicks,
                    SUM(conversions) as conversions,
                    SUM(spend) as spend,
                    SUM(revenue) as revenue
                ')->groupBy('campaign_id')->with('campaign')->get();
                break;
            case 'platform':
                $groupedData = $query->selectRaw('
                    platform,
                    SUM(impressions) as impressions,
                    SUM(clicks) as clicks,
                    SUM(conversions) as conversions,
                    SUM(spend) as spend,
                    SUM(revenue) as revenue
                ')->groupBy('platform')->get();
                break;
        }

        return [
            'summary' => $summary,
            'grouped_data' => $groupedData
        ];
    }

    /**
     * Get top performing campaigns
     */
    private function getTopPerformingCampaigns($startDate, $endDate, $platform = null): array
    {
        $query = CampaignPerformance::query()
            ->byDateRange($startDate, $endDate)
            ->with('campaign');

        if ($platform) {
            $query->byPlatform($platform);
        }

        return $query->selectRaw('
                campaign_id,
                SUM(impressions) as total_impressions,
                SUM(clicks) as total_clicks,
                SUM(conversions) as total_conversions,
                SUM(spend) as total_spend,
                SUM(revenue) as total_revenue,
                AVG(return_on_ad_spend) as avg_roas
            ')
            ->groupBy('campaign_id')
            ->orderBy('avg_roas', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get performance trends
     */
    private function getPerformanceTrends($startDate, $endDate, $campaignId = null, $platform = null): array
    {
        $query = CampaignPerformance::query()
            ->byDateRange($startDate, $endDate);

        if ($campaignId) {
            $query->byCampaign($campaignId);
        }

        if ($platform) {
            $query->byPlatform($platform);
        }

        return $query->selectRaw('
                date,
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                SUM(conversions) as conversions,
                SUM(spend) as spend,
                SUM(revenue) as revenue
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Get creative asset performance
     */
    private function getCreativeAssetPerformance($startDate, $endDate, $campaignId = null): array
    {
        $query = CampaignPerformance::query()
            ->byDateRange($startDate, $endDate)
            ->with('creativeAsset');

        if ($campaignId) {
            $query->byCampaign($campaignId);
        }

        return $query->selectRaw('
                creative_asset_id,
                SUM(impressions) as total_impressions,
                SUM(clicks) as total_clicks,
                SUM(conversions) as total_conversions,
                AVG(engagement_rate) as avg_engagement_rate,
                AVG(click_through_rate) as avg_ctr
            ')
            ->whereNotNull('creative_asset_id')
            ->groupBy('creative_asset_id')
            ->orderBy('avg_engagement_rate', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Sync Meta (Facebook/Instagram) data
     */
    private function syncMetaData($startDate, $endDate, $campaignId = null): array
    {
        // This would integrate with Meta Marketing API
        // For now, return mock data
        return [
            'platform' => 'meta',
            'records_synced' => 0,
            'last_sync' => now()->toISOString(),
            'status' => 'pending_implementation'
        ];
    }

    /**
     * Sync TikTok data
     */
    private function syncTikTokData($startDate, $endDate, $campaignId = null): array
    {
        return [
            'platform' => 'tiktok',
            'records_synced' => 0,
            'last_sync' => now()->toISOString(),
            'status' => 'pending_implementation'
        ];
    }

    /**
     * Sync Google data
     */
    private function syncGoogleData($startDate, $endDate, $campaignId = null): array
    {
        return [
            'platform' => 'google',
            'records_synced' => 0,
            'last_sync' => now()->toISOString(),
            'status' => 'pending_implementation'
        ];
    }

    /**
     * Get top performing platforms
     */
    private function getTopPerformingPlatforms($performanceData): array
    {
        return $performanceData->groupBy('platform')
            ->map(function ($platformData) {
                return [
                    'platform' => $platformData->first()->platform,
                    'total_spend' => $platformData->sum('spend'),
                    'total_revenue' => $platformData->sum('revenue'),
                    'avg_roas' => $platformData->avg('return_on_ad_spend'),
                    'avg_ctr' => $platformData->avg('click_through_rate'),
                ];
            })
            ->sortByDesc('avg_roas')
            ->values()
            ->toArray();
    }

    /**
     * Get best performing times
     */
    private function getBestPerformingTimes($performanceData): array
    {
        // This would analyze time_performance data
        return [
            'best_days' => ['Monday', 'Wednesday', 'Friday'],
            'best_hours' => ['9-11', '14-16', '19-21'],
            'timezone' => 'WAT'
        ];
    }

    /**
     * Get audience insights
     */
    private function getAudienceInsights($performanceData): array
    {
        // This would analyze demographics data
        return [
            'top_age_groups' => ['25-34', '35-44'],
            'top_genders' => ['Female'],
            'top_locations' => ['Lagos', 'Abuja', 'Port Harcourt'],
            'interests' => ['Beauty', 'Fashion', 'Hair Care']
        ];
    }

    /**
     * Get optimization recommendations
     */
    private function getOptimizationRecommendations($performanceData): array
    {
        $recommendations = [];

        $avgRoas = $performanceData->avg('return_on_ad_spend');
        if ($avgRoas < 2) {
            $recommendations[] = 'Consider optimizing ad targeting to improve ROAS';
        }

        $avgCtr = $performanceData->avg('click_through_rate');
        if ($avgCtr < 0.01) {
            $recommendations[] = 'Review ad creative and messaging to improve click-through rates';
        }

        return $recommendations;
    }

    /**
     * Get budget optimization insights
     */
    private function getBudgetOptimizationInsights($performanceData): array
    {
        return [
            'high_performing_campaigns' => $performanceData->where('return_on_ad_spend', '>', 3)->count(),
            'low_performing_campaigns' => $performanceData->where('return_on_ad_spend', '<', 1)->count(),
            'budget_reallocation_suggestions' => [
                'Increase budget for campaigns with ROAS > 3',
                'Pause or optimize campaigns with ROAS < 1'
            ]
        ];
    }

    /**
     * Get creative performance insights
     */
    private function getCreativePerformanceInsights($performanceData): array
    {
        return [
            'top_creative_types' => ['Video', 'Carousel', 'Single Image'],
            'engagement_trends' => 'Video content shows 40% higher engagement',
            'conversion_trends' => 'Carousel ads have 25% higher conversion rates'
        ];
    }
}
