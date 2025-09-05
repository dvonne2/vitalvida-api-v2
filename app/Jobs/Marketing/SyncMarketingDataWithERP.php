<?php

namespace App\Jobs\Marketing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Marketing\MarketingCampaign;
use App\Models\Marketing\MarketingCustomerTouchpoint;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SyncMarketingDataWithERP implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $syncType;
    protected $companyId;
    protected $lastSyncTime;

    public function __construct($syncType = 'full', $companyId = null, $lastSyncTime = null)
    {
        $this->syncType = $syncType;
        $this->companyId = $companyId;
        $this->lastSyncTime = $lastSyncTime ?? now()->subDay();
    }

    public function handle()
    {
        try {
            Log::info("Starting marketing data sync with ERP", [
                'sync_type' => $this->syncType,
                'company_id' => $this->companyId,
                'last_sync_time' => $this->lastSyncTime
            ]);

            switch ($this->syncType) {
                case 'customers':
                    $this->syncCustomers();
                    break;
                case 'campaigns':
                    $this->syncCampaigns();
                    break;
                case 'performance':
                    $this->syncPerformanceData();
                    break;
                case 'full':
                default:
                    $this->syncCustomers();
                    $this->syncCampaigns();
                    $this->syncPerformanceData();
                    break;
            }

            // Update last sync time
            $this->updateLastSyncTime();

            Log::info("Marketing data sync completed successfully", [
                'sync_type' => $this->syncType,
                'company_id' => $this->companyId
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to sync marketing data with ERP", [
                'sync_type' => $this->syncType,
                'company_id' => $this->companyId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    protected function syncCustomers()
    {
        $query = Customer::query();
        
        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        }

        // Only sync customers updated since last sync
        $customers = $query->where('updated_at', '>=', $this->lastSyncTime)->get();

        foreach ($customers as $customer) {
            $this->syncCustomerToERP($customer);
        }

        Log::info("Customer sync completed", [
            'customers_synced' => $customers->count(),
            'company_id' => $this->companyId
        ]);
    }

    protected function syncCustomerToERP($customer)
    {
        $erpData = [
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'whatsapp' => $customer->whatsapp,
            'address' => $customer->address,
            'city' => $customer->city,
            'state' => $customer->state,
            'country' => $customer->country,
            'postal_code' => $customer->postal_code,
            'status' => $customer->status,
            'source' => $customer->source,
            'preferences' => $customer->preferences,
            'tags' => $customer->tags,
            'company_id' => $customer->company_id,
            'last_contacted_at' => $customer->last_contacted_at,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at
        ];

        // Send to ERP system
        $this->sendToERP('customers', $erpData);
    }

    protected function syncCampaigns()
    {
        $query = MarketingCampaign::query();
        
        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        }

        $campaigns = $query->where('updated_at', '>=', $this->lastSyncTime)->get();

        foreach ($campaigns as $campaign) {
            $this->syncCampaignToERP($campaign);
        }

        Log::info("Campaign sync completed", [
            'campaigns_synced' => $campaigns->count(),
            'company_id' => $this->companyId
        ]);
    }

    protected function syncCampaignToERP($campaign)
    {
        $erpData = [
            'campaign_id' => $campaign->id,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'status' => $campaign->status,
            'start_date' => $campaign->start_date,
            'end_date' => $campaign->end_date,
            'budget' => $campaign->budget,
            'target_audience' => $campaign->target_audience,
            'channels' => $campaign->channels,
            'performance_metrics' => $campaign->performance_metrics,
            'company_id' => $campaign->company_id,
            'created_at' => $campaign->created_at,
            'updated_at' => $campaign->updated_at
        ];

        $this->sendToERP('campaigns', $erpData);
    }

    protected function syncPerformanceData()
    {
        $query = MarketingCustomerTouchpoint::query();
        
        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        }

        $touchpoints = $query->where('created_at', '>=', $this->lastSyncTime)->get();

        foreach ($touchpoints as $touchpoint) {
            $this->syncTouchpointToERP($touchpoint);
        }

        Log::info("Performance data sync completed", [
            'touchpoints_synced' => $touchpoints->count(),
            'company_id' => $this->companyId
        ]);
    }

    protected function syncTouchpointToERP($touchpoint)
    {
        $erpData = [
            'touchpoint_id' => $touchpoint->id,
            'customer_id' => $touchpoint->customer_id,
            'campaign_id' => $touchpoint->campaign_id,
            'touchpoint_type' => $touchpoint->touchpoint_type,
            'channel' => $touchpoint->channel,
            'status' => $touchpoint->status,
            'scheduled_at' => $touchpoint->scheduled_at,
            'sent_at' => $touchpoint->sent_at,
            'delivered_at' => $touchpoint->delivered_at,
            'opened_at' => $touchpoint->opened_at,
            'clicked_at' => $touchpoint->clicked_at,
            'converted_at' => $touchpoint->converted_at,
            'metadata' => $touchpoint->metadata,
            'company_id' => $touchpoint->company_id,
            'created_at' => $touchpoint->created_at,
            'updated_at' => $touchpoint->updated_at
        ];

        $this->sendToERP('touchpoints', $erpData);
    }

    protected function sendToERP($endpoint, $data)
    {
        try {
            $erpUrl = config('services.erp.base_url') . "/api/{$endpoint}";
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.erp.api_token'),
                'Content-Type' => 'application/json',
                'X-Company-ID' => $this->companyId
            ])->post($erpUrl, $data);

            if (!$response->successful()) {
                Log::warning("Failed to sync data to ERP", [
                    'endpoint' => $endpoint,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Exception during ERP sync", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function updateLastSyncTime()
    {
        // Store the last sync time in cache or database
        $syncKey = "marketing_erp_sync_{$this->companyId}";
        cache()->put($syncKey, now(), now()->addDays(30));
    }
}
