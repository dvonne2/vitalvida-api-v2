<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MarketingTikTokService
{
    protected $accessToken;
    protected $advertiserId;
    protected $baseUrl = 'https://business-api.tiktok.com/open_api/v1.3';
    protected $companyId;

    public function __construct($companyId = null)
    {
        $this->companyId = $companyId;
        $this->accessToken = config('services.tiktok.access_token');
        $this->advertiserId = config('services.tiktok.advertiser_id');
    }

    /**
     * Create TikTok ad
     */
    public function createAd($content, $budget, $campaignId = null, $targeting = [])
    {
        try {
            $adData = [
                'advertiser_id' => $this->advertiserId,
                'campaign_id' => $campaignId,
                'ad_name' => 'VitalVida Ad - ' . date('Y-m-d H:i:s'),
                'ad_text' => $content,
                'budget' => $budget,
                'targeting' => $this->buildTargeting($targeting),
                'creative' => $this->buildCreative($content)
            ];

            $response = Http::withHeaders([
                'Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/ad/create/', $adData);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info("TikTok ad created successfully", [
                    'ad_id' => $result['data']['ad_id'] ?? null,
                    'campaign_id' => $campaignId,
                    'company_id' => $this->companyId
                ]);

                return [
                    'status' => 'success',
                    'ad_id' => $result['data']['ad_id'] ?? null,
                    'data' => $result['data']
                ];
            } else {
                Log::error("Failed to create TikTok ad", [
                    'response' => $response->body(),
                    'campaign_id' => $campaignId
                ]);

                return [
                    'status' => 'error',
                    'error' => $response->body()
                ];
            }

        } catch (\Exception $e) {
            Log::error("Exception creating TikTok ad", [
                'error' => $e->getMessage(),
                'campaign_id' => $campaignId
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create TikTok campaign
     */
    public function createCampaign($name, $objective, $budget, $startDate = null, $endDate = null)
    {
        try {
            $campaignData = [
                'advertiser_id' => $this->advertiserId,
                'campaign_name' => $name,
                'objective_type' => $this->mapObjective($objective),
                'budget' => $budget,
                'start_time' => $startDate ?? now()->timestamp,
                'end_time' => $endDate ?? now()->addDays(30)->timestamp,
                'status' => 'ENABLE'
            ];

            $response = Http::withHeaders([
                'Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/campaign/create/', $campaignData);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info("TikTok campaign created successfully", [
                    'campaign_id' => $result['data']['campaign_id'] ?? null,
                    'company_id' => $this->companyId
                ]);

                return [
                    'status' => 'success',
                    'campaign_id' => $result['data']['campaign_id'] ?? null,
                    'data' => $result['data']
                ];
            } else {
                Log::error("Failed to create TikTok campaign", [
                    'response' => $response->body()
                ]);

                return [
                    'status' => 'error',
                    'error' => $response->body()
                ];
            }

        } catch (\Exception $e) {
            Log::error("Exception creating TikTok campaign", [
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get ad performance metrics
     */
    public function getAdPerformance($adId, $startDate = null, $endDate = null)
    {
        try {
            $params = [
                'advertiser_id' => $this->advertiserId,
                'ad_id' => $adId,
                'start_date' => $startDate ?? now()->subDays(7)->format('Y-m-d'),
                'end_date' => $endDate ?? now()->format('Y-m-d'),
                'fields' => [
                    'spend', 'impressions', 'clicks', 'ctr', 'cpc', 'cpm',
                    'reach', 'frequency', 'video_views', 'video_view_rate'
                ]
            ];

            $response = Http::withHeaders([
                'Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/report/integrated/get/', $params);

            if ($response->successful()) {
                $result = $response->json();
                
                return [
                    'status' => 'success',
                    'metrics' => $result['data']['list'] ?? [],
                    'summary' => $this->calculateSummary($result['data']['list'] ?? [])
                ];
            } else {
                Log::error("Failed to get TikTok ad performance", [
                    'ad_id' => $adId,
                    'response' => $response->body()
                ]);

                return [
                    'status' => 'error',
                    'error' => $response->body()
                ];
            }

        } catch (\Exception $e) {
            Log::error("Exception getting TikTok ad performance", [
                'ad_id' => $adId,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get campaign performance
     */
    public function getCampaignPerformance($campaignId, $startDate = null, $endDate = null)
    {
        try {
            $params = [
                'advertiser_id' => $this->advertiserId,
                'campaign_id' => $campaignId,
                'start_date' => $startDate ?? now()->subDays(7)->format('Y-m-d'),
                'end_date' => $endDate ?? now()->format('Y-m-d'),
                'fields' => [
                    'spend', 'impressions', 'clicks', 'ctr', 'cpc', 'cpm',
                    'reach', 'frequency', 'video_views', 'video_view_rate'
                ]
            ];

            $response = Http::withHeaders([
                'Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/report/integrated/get/', $params);

            if ($response->successful()) {
                $result = $response->json();
                
                return [
                    'status' => 'success',
                    'metrics' => $result['data']['list'] ?? [],
                    'summary' => $this->calculateSummary($result['data']['list'] ?? [])
                ];
            } else {
                Log::error("Failed to get TikTok campaign performance", [
                    'campaign_id' => $campaignId,
                    'response' => $response->body()
                ]);

                return [
                    'status' => 'error',
                    'error' => $response->body()
                ];
            }

        } catch (\Exception $e) {
            Log::error("Exception getting TikTok campaign performance", [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update ad status
     */
    public function updateAdStatus($adId, $status)
    {
        try {
            $updateData = [
                'advertiser_id' => $this->advertiserId,
                'ad_id' => $adId,
                'status' => $status === 'active' ? 'ENABLE' : 'DISABLE'
            ];

            $response = Http::withHeaders([
                'Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/ad/update/', $updateData);

            if ($response->successful()) {
                Log::info("TikTok ad status updated", [
                    'ad_id' => $adId,
                    'status' => $status
                ]);

                return [
                    'status' => 'success',
                    'message' => 'Ad status updated successfully'
                ];
            } else {
                Log::error("Failed to update TikTok ad status", [
                    'ad_id' => $adId,
                    'response' => $response->body()
                ]);

                return [
                    'status' => 'error',
                    'error' => $response->body()
                ];
            }

        } catch (\Exception $e) {
            Log::error("Exception updating TikTok ad status", [
                'ad_id' => $adId,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get available targeting options
     */
    public function getTargetingOptions()
    {
        try {
            $cacheKey = 'tiktok_targeting_options';
            
            // Check cache first
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            $response = Http::withHeaders([
                'Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/targeting/search/');

            if ($response->successful()) {
                $result = $response->json();
                
                // Cache for 24 hours
                Cache::put($cacheKey, $result['data'] ?? [], 86400);
                
                return $result['data'] ?? [];
            } else {
                Log::error("Failed to get TikTok targeting options", [
                    'response' => $response->body()
                ]);

                return [];
            }

        } catch (\Exception $e) {
            Log::error("Exception getting TikTok targeting options", [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Build targeting parameters
     */
    protected function buildTargeting($targeting)
    {
        $defaultTargeting = [
            'age' => [18, 65],
            'gender' => ['MALE', 'FEMALE'],
            'location' => ['NG'], // Nigeria
            'interests' => ['health', 'wellness', 'fitness'],
            'language' => ['en']
        ];

        return array_merge($defaultTargeting, $targeting);
    }

    /**
     * Build creative parameters
     */
    protected function buildCreative($content)
    {
        return [
            'ad_text' => $content,
            'call_to_action' => 'SHOP_NOW',
            'landing_page_url' => config('app.url') . '/products',
            'display_name' => 'VitalVida'
        ];
    }

    /**
     * Map objective to TikTok format
     */
    protected function mapObjective($objective)
    {
        $mapping = [
            'awareness' => 'REACH',
            'consideration' => 'TRAFFIC',
            'conversion' => 'CONVERSIONS',
            'sales' => 'SALES'
        ];

        return $mapping[$objective] ?? 'REACH';
    }

    /**
     * Calculate performance summary
     */
    protected function calculateSummary($metrics)
    {
        if (empty($metrics)) {
            return [];
        }

        $summary = [
            'total_spend' => 0,
            'total_impressions' => 0,
            'total_clicks' => 0,
            'total_reach' => 0,
            'total_video_views' => 0
        ];

        foreach ($metrics as $metric) {
            $summary['total_spend'] += $metric['spend'] ?? 0;
            $summary['total_impressions'] += $metric['impressions'] ?? 0;
            $summary['total_clicks'] += $metric['clicks'] ?? 0;
            $summary['total_reach'] += $metric['reach'] ?? 0;
            $summary['total_video_views'] += $metric['video_views'] ?? 0;
        }

        // Calculate averages
        if ($summary['total_impressions'] > 0) {
            $summary['avg_ctr'] = ($summary['total_clicks'] / $summary['total_impressions']) * 100;
            $summary['avg_cpm'] = ($summary['total_spend'] / $summary['total_impressions']) * 1000;
        }

        if ($summary['total_clicks'] > 0) {
            $summary['avg_cpc'] = $summary['total_spend'] / $summary['total_clicks'];
        }

        return $summary;
    }

    /**
     * Validate access token
     */
    public function validateAccessToken()
    {
        try {
            $response = Http::withHeaders([
                'Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/user/info/');

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
