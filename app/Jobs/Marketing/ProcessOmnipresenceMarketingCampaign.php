<?php

namespace App\Jobs\Marketing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Marketing\MarketingCampaign;
use App\Models\Marketing\MarketingCustomerTouchpoint;
use App\Services\Marketing\MarketingWhatsAppBusinessService;
use App\Services\Marketing\MarketingEmailService;
use App\Services\Marketing\MarketingTikTokService;
use App\Services\Marketing\MarketingGoogleAdsService;
use Illuminate\Support\Facades\Log;

class ProcessOmnipresenceMarketingCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaignId;
    protected $companyId;

    public function __construct($campaignId, $companyId = null)
    {
        $this->campaignId = $campaignId;
        $this->companyId = $companyId;
    }

    public function handle()
    {
        try {
            $campaign = MarketingCampaign::findOrFail($this->campaignId);
            
            Log::info("Processing omnipresence campaign: {$campaign->name}", [
                'campaign_id' => $this->campaignId,
                'company_id' => $this->companyId
            ]);

            // Process different channels based on campaign settings
            if ($campaign->whatsapp_enabled) {
                $this->processWhatsAppChannel($campaign);
            }

            if ($campaign->email_enabled) {
                $this->processEmailChannel($campaign);
            }

            if ($campaign->tiktok_enabled) {
                $this->processTikTokChannel($campaign);
            }

            if ($campaign->google_ads_enabled) {
                $this->processGoogleAdsChannel($campaign);
            }

            // Update campaign status
            $campaign->update([
                'status' => 'processing',
                'last_processed_at' => now()
            ]);

            Log::info("Omnipresence campaign processed successfully", [
                'campaign_id' => $this->campaignId
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to process omnipresence campaign", [
                'campaign_id' => $this->campaignId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    protected function processWhatsAppChannel($campaign)
    {
        $whatsappService = app(MarketingWhatsAppBusinessService::class);
        
        // Get target customers
        $customers = $this->getTargetCustomers($campaign);
        
        foreach ($customers as $customer) {
            if ($customer->whatsapp) {
                $whatsappService->sendBulkMessage(
                    $customer->whatsapp,
                    $campaign->whatsapp_message,
                    $campaign->id,
                    $this->companyId
                );
            }
        }
    }

    protected function processEmailChannel($campaign)
    {
        $emailService = app(MarketingEmailService::class);
        
        $customers = $this->getTargetCustomers($campaign);
        
        foreach ($customers as $customer) {
            if ($customer->email) {
                $emailService->sendCampaignEmail(
                    $customer->email,
                    $campaign->email_subject,
                    $campaign->email_content,
                    $campaign->id
                );
            }
        }
    }

    protected function processTikTokChannel($campaign)
    {
        $tiktokService = app(MarketingTikTokService::class);
        
        if ($campaign->tiktok_content) {
            $tiktokService->createAd(
                $campaign->tiktok_content,
                $campaign->tiktok_budget,
                $campaign->id
            );
        }
    }

    protected function processGoogleAdsChannel($campaign)
    {
        $googleAdsService = app(MarketingGoogleAdsService::class);
        
        if ($campaign->google_ads_content) {
            $googleAdsService->createCampaign(
                $campaign->google_ads_content,
                $campaign->google_ads_budget,
                $campaign->id
            );
        }
    }

    protected function getTargetCustomers($campaign)
    {
        // Get customers based on campaign targeting criteria
        $query = \App\Models\Customer::query();
        
        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        }
        
        if ($campaign->target_audience) {
            $query->whereJsonContains('tags', $campaign->target_audience);
        }
        
        if ($campaign->status_filter) {
            $query->where('status', $campaign->status_filter);
        }
        
        return $query->limit($campaign->max_recipients ?? 1000)->get();
    }
}
