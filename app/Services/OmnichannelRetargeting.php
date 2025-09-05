<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\RetargetingCampaign;
use App\Jobs\LaunchRetargetingCampaign;
use Illuminate\Support\Facades\Log;

class OmnichannelRetargeting
{
    private array $platforms = [
        'meta' => MetaAdsService::class,
        'tiktok' => TikTokAdsService::class,
        'google' => GoogleAdsService::class,
        'youtube' => YouTubeAdsService::class,
        'whatsapp' => WhatsAppService::class,
        'sms' => TermiiService::class,
        'email' => ZohoCampaignsService::class
    ];

    public function triggerAbandonedCartSequence(Customer $customer, array $cartItems): void
    {
        $sequence = [
            ['platform' => 'whatsapp', 'delay' => 15, 'urgency' => 'low'],
            ['platform' => 'meta', 'delay' => 360, 'urgency' => 'medium'], // 6 hours
            ['platform' => 'sms', 'delay' => 1440, 'urgency' => 'high'], // 24 hours
            ['platform' => 'email', 'delay' => 4320, 'urgency' => 'final'] // 3 days
        ];

        foreach ($sequence as $step) {
            LaunchRetargetingCampaign::dispatch(
                $customer,
                $step['platform'],
                'abandoned_cart',
                ['items' => $cartItems, 'urgency' => $step['urgency']]
            )->delay(now()->addMinutes($step['delay']));
        }

        Log::info('Abandoned cart sequence triggered', [
            'customer_id' => $customer->id,
            'items_count' => count($cartItems)
        ]);
    }

    public function triggerReorderSequence(Customer $customer): void
    {
        if (!$customer->shouldTriggerReorderFlow()) {
            return;
        }

        $reorderSequence = [
            ['platform' => 'whatsapp', 'delay' => 0, 'message_type' => 'soft_reminder'],
            ['platform' => 'meta', 'delay' => 1440, 'message_type' => 'dynamic_product_ad'],
            ['platform' => 'sms', 'delay' => 2880, 'message_type' => 'discount_offer'],
            ['platform' => 'email', 'delay' => 4320, 'message_type' => 'loyalty_reward']
        ];

        foreach ($reorderSequence as $step) {
            LaunchRetargetingCampaign::dispatch(
                $customer,
                $step['platform'],
                'reorder_reminder',
                [
                    'last_product' => $customer->orders()->latest()->first()?->products,
                    'message_type' => $step['message_type']
                ]
            )->delay(now()->addMinutes($step['delay']));
        }

        Log::info('Reorder sequence triggered', [
            'customer_id' => $customer->id,
            'days_since_last_order' => $customer->last_purchase_date?->diffInDays(now())
        ]);
    }

    public function triggerChurnPreventionSequence(Customer $customer): void
    {
        if ($customer->calculateChurnRisk() < 0.7) {
            return;
        }

        $churnSequence = [
            ['platform' => 'whatsapp', 'delay' => 0, 'message_type' => 'check_in'],
            ['platform' => 'email', 'delay' => 1440, 'message_type' => 'special_offer'],
            ['platform' => 'sms', 'delay' => 2880, 'message_type' => 'urgent_reminder'],
            ['platform' => 'meta', 'delay' => 4320, 'message_type' => 'win_back_campaign']
        ];

        foreach ($churnSequence as $step) {
            LaunchRetargetingCampaign::dispatch(
                $customer,
                $step['platform'],
                'churn_prevention',
                [
                    'churn_risk' => $customer->calculateChurnRisk(),
                    'message_type' => $step['message_type'],
                    'lifetime_value' => $customer->lifetime_value_prediction
                ]
            )->delay(now()->addMinutes($step['delay']));
        }

        Log::info('Churn prevention sequence triggered', [
            'customer_id' => $customer->id,
            'churn_risk' => $customer->calculateChurnRisk()
        ]);
    }

    public function deployOmnipresentCampaign(Customer $customer, string $campaignType): void
    {
        $platforms = $this->selectOptimalPlatforms($customer);
        
        foreach ($platforms as $platform) {
            try {
                $service = app($this->platforms[$platform]);
                $creative = $this->getOptimalCreative($customer, $platform);
                
                $service->launchCampaign([
                    'customer' => $customer,
                    'creative' => $creative,
                    'campaign_type' => $campaignType,
                    'budget' => $this->calculateOptimalBudget($customer, $platform)
                ]);

                Log::info('Omnipresent campaign deployed', [
                    'customer_id' => $customer->id,
                    'platform' => $platform,
                    'campaign_type' => $campaignType
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to deploy campaign', [
                    'customer_id' => $customer->id,
                    'platform' => $platform,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    public function triggerViralAmplificationSequence(Customer $customer): void
    {
        if (!$customer->shouldReceiveHighValueCampaign()) {
            return;
        }

        $viralSequence = [
            ['platform' => 'whatsapp', 'delay' => 0, 'message_type' => 'referral_offer'],
            ['platform' => 'meta', 'delay' => 720, 'message_type' => 'social_share_campaign'],
            ['platform' => 'tiktok', 'delay' => 1440, 'message_type' => 'user_generated_content'],
            ['platform' => 'email', 'delay' => 2160, 'message_type' => 'loyalty_program']
        ];

        foreach ($viralSequence as $step) {
            LaunchRetargetingCampaign::dispatch(
                $customer,
                $step['platform'],
                'viral_amplification',
                [
                    'customer_tier' => $customer->getCustomerTier(),
                    'message_type' => $step['message_type'],
                    'referral_potential' => $this->calculateReferralPotential($customer)
                ]
            )->delay(now()->addMinutes($step['delay']));
        }
    }

    private function selectOptimalPlatforms(Customer $customer): array
    {
        $platforms = ['meta', 'whatsapp']; // Always include these
        
        // AI-based platform selection
        if ($customer->age >= 18 && $customer->age <= 34) {
            $platforms[] = 'tiktok';
        }
        
        if ($customer->total_spent > 50000) {
            $platforms[] = 'google';
            $platforms[] = 'youtube';
        }
        
        if ($customer->orders_count >= 2) {
            $platforms[] = 'email';
        }
        
        return $platforms;
    }

    private function getOptimalCreative(Customer $customer, string $platform): array
    {
        // This would integrate with the AICreative model to get the best performing creative
        // for this customer segment and platform
        return [
            'headline' => 'Transform Your Hair Today!',
            'primary_text' => 'Join thousands of satisfied customers with our proven formula.',
            'cta' => 'Order Now',
            'platform' => $platform,
            'target_audience' => $customer->getPersonaTag()
        ];
    }

    private function calculateOptimalBudget(Customer $customer, string $platform): int
    {
        // AI-based budget calculation
        $baseBudget = 2000; // ₦2,000 base
        
        if ($customer->total_spent > 100000) {
            $baseBudget *= 2; // High-value customers get 2x budget
        }
        
        if ($customer->churn_probability > 0.7) {
            $baseBudget *= 1.5; // At-risk customers get 1.5x budget
        }
        
        // Platform-specific adjustments
        $platformMultipliers = [
            'meta' => 1.0,
            'tiktok' => 0.8,
            'google' => 1.2,
            'youtube' => 1.3,
            'whatsapp' => 0.5,
            'sms' => 0.3,
            'email' => 0.4
        ];
        
        return (int) ($baseBudget * ($platformMultipliers[$platform] ?? 1.0));
    }

    private function calculateReferralPotential(Customer $customer): float
    {
        $baseScore = 0.5;
        
        // Higher lifetime value = higher referral potential
        if ($customer->lifetime_value_prediction > 100000) {
            $baseScore += 0.3;
        }
        
        // More orders = more satisfied customer
        if ($customer->orders_count > 5) {
            $baseScore += 0.2;
        }
        
        // Recent activity = higher engagement
        if ($customer->last_purchase_date && $customer->last_purchase_date->diffInDays(now()) < 30) {
            $baseScore += 0.2;
        }
        
        return min($baseScore, 1.0);
    }

    public function getCampaignPerformance(Customer $customer, string $campaignType): array
    {
        return RetargetingCampaign::where('customer_id', $customer->id)
            ->where('campaign_type', $campaignType)
            ->get()
            ->map(function ($campaign) {
                return [
                    'platform' => $campaign->platform,
                    'status' => $campaign->status,
                    'roi' => $campaign->getROI(),
                    'conversion_achieved' => $campaign->conversion_achieved,
                    'cost' => $campaign->cost,
                    'revenue_generated' => $campaign->revenue_generated
                ];
            })
            ->toArray();
    }
} 