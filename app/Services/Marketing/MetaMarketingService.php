<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MetaMarketingService
{
    protected $accessToken;
    protected $appId;
    protected $appSecret;
    protected $apiVersion;

    public function __construct()
    {
        $this->accessToken = config('services.facebook.access_token');
        $this->appId = config('services.facebook.app_id');
        $this->appSecret = config('services.facebook.app_secret');
        $this->apiVersion = config('services.facebook.api_version', 'v18.0');
    }

    /**
     * Get Facebook Ad Account data
     */
    public function getAdAccounts(): array
    {
        try {
            $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/me/adaccounts", [
                'access_token' => $this->accessToken,
                'fields' => 'id,name,account_status,currency,timezone_name,spend_cap'
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch ad accounts',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Meta Marketing Service Error - getAdAccounts', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get campaigns for an ad account
     */
    public function getCampaigns(string $adAccountId, array $params = []): array
    {
        try {
            $defaultParams = [
                'access_token' => $this->accessToken,
                'fields' => 'id,name,status,objective,start_time,stop_time,budget_remaining,spend_cap,created_time,updated_time'
            ];

            $requestParams = array_merge($defaultParams, $params);

            $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/act_{$adAccountId}/campaigns", $requestParams);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch campaigns',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Meta Marketing Service Error - getCampaigns', [
                'ad_account_id' => $adAccountId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get campaign insights
     */
    public function getCampaignInsights(string $campaignId, array $params = []): array
    {
        try {
            $defaultParams = [
                'access_token' => $this->accessToken,
                'fields' => 'impressions,reach,clicks,spend,actions,action_values,cost_per_action_type,cpm,cpc,ctr,conversions,conversion_values,roas',
                'date_preset' => 'last_30d'
            ];

            $requestParams = array_merge($defaultParams, $params);

            $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/{$campaignId}/insights", $requestParams);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch campaign insights',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Meta Marketing Service Error - getCampaignInsights', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get ad sets for a campaign
     */
    public function getAdSets(string $campaignId, array $params = []): array
    {
        try {
            $defaultParams = [
                'access_token' => $this->accessToken,
                'fields' => 'id,name,status,targeting,start_time,stop_time,budget_remaining,spend_cap,created_time,updated_time'
            ];

            $requestParams = array_merge($defaultParams, $params);

            $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/{$campaignId}/adsets", $requestParams);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch ad sets',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Meta Marketing Service Error - getAdSets', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get ads for an ad set
     */
    public function getAds(string $adSetId, array $params = []): array
    {
        try {
            $defaultParams = [
                'access_token' => $this->accessToken,
                'fields' => 'id,name,status,creative,created_time,updated_time'
            ];

            $requestParams = array_merge($defaultParams, $params);

            $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/{$adSetId}/ads", $requestParams);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch ads',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Meta Marketing Service Error - getAds', [
                'ad_set_id' => $adSetId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get ad insights
     */
    public function getAdInsights(string $adId, array $params = []): array
    {
        try {
            $defaultParams = [
                'access_token' => $this->accessToken,
                'fields' => 'impressions,reach,clicks,spend,actions,action_values,cost_per_action_type,cpm,cpc,ctr,conversions,conversion_values,roas',
                'date_preset' => 'last_30d'
            ];

            $requestParams = array_merge($defaultParams, $params);

            $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/{$adId}/insights", $requestParams);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch ad insights',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Meta Marketing Service Error - getAdInsights', [
                'ad_id' => $adId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a campaign
     */
    public function createCampaign(string $adAccountId, array $campaignData): array
    {
        try {
            $data = array_merge($campaignData, [
                'access_token' => $this->accessToken,
                'status' => 'PAUSED' // Always create as paused for safety
            ]);

            $response = Http::post("https://graph.facebook.com/{$this->apiVersion}/act_{$adAccountId}/campaigns", $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'campaign_id' => $response->json()['id'] ?? null
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to create campaign',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Meta Marketing Service Error - createCampaign', [
                'ad_account_id' => $adAccountId,
                'campaign_data' => $campaignData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update a campaign
     */
    public function updateCampaign(string $campaignId, array $updateData): array
    {
        try {
            $data = array_merge($updateData, [
                'access_token' => $this->accessToken
            ]);

            $response = Http::post("https://graph.facebook.com/{$this->apiVersion}/{$campaignId}", $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to update campaign',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Meta Marketing Service Error - updateCampaign', [
                'campaign_id' => $campaignId,
                'update_data' => $updateData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get user permissions
     */
    public function getUserPermissions(): array
    {
        try {
            $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/me/permissions", [
                'access_token' => $this->accessToken
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch permissions',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Meta Marketing Service Error - getUserPermissions', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        try {
            $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/me", [
                'access_token' => $this->accessToken,
                'fields' => 'id,name,email'
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Meta API connection successful',
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'API connection failed: ' . $response->status()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get account insights summary
     */
    public function getAccountInsights(string $adAccountId, array $params = []): array
    {
        try {
            $defaultParams = [
                'access_token' => $this->accessToken,
                'fields' => 'impressions,reach,clicks,spend,actions,action_values,cost_per_action_type,cpm,cpc,ctr,conversions,conversion_values,roas',
                'date_preset' => 'last_30d',
                'breakdowns' => 'publisher_platform,platform_position'
            ];

            $requestParams = array_merge($defaultParams, $params);

            $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/act_{$adAccountId}/insights", $requestParams);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch account insights',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Meta Marketing Service Error - getAccountInsights', [
                'ad_account_id' => $adAccountId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Service error: ' . $e->getMessage()
            ];
        }
    }
}
