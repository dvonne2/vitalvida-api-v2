<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\RetargetingCampaign;
use App\Models\AIInteraction;
use App\Services\OmnichannelRetargeting;
use App\Services\AIContentGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LaunchRetargetingCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    public function __construct(
        public Customer $customer,
        public string $platform,
        public string $campaignType,
        public array $parameters = []
    ) {}

    public function handle()
    {
        $contentGenerator = app(AIContentGenerator::class);
        $retargetingService = app(OmnichannelRetargeting::class);

        try {
            // Generate personalized content based on customer and platform
            $content = $this->generatePersonalizedContent($contentGenerator);
            
            // Launch campaign on specified platform
            $result = $this->launchOnPlatform($content);
            
            // Log the action
            $this->logRetargetingAction($result);
            
            // Update customer metrics
            $this->updateCustomerMetrics($result);
            
        } catch (\Exception $e) {
            Log::error("Retargeting campaign failed", [
                'customer_id' => $this->customer->id,
                'platform' => $this->platform,
                'campaign_type' => $this->campaignType,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function generatePersonalizedContent(AIContentGenerator $generator): array
    {
        $context = [
            'customer_name' => $this->customer->name,
            'last_purchase' => $this->customer->orders()->latest()->first()?->products,
            'campaign_type' => $this->campaignType,
            'platform' => $this->platform,
            'urgency' => $this->parameters['urgency'] ?? 'medium',
            'persona' => $this->customer->persona_tag,
            'location' => $this->customer->state,
            'stage' => $this->getCustomerStage(),
            'last_interaction' => $this->getLastInteraction(),
            'goal' => $this->getCampaignGoal()
        ];

        switch ($this->platform) {
            case 'whatsapp':
                return ['message' => $generator->generateWhatsAppMessage($context)];
            case 'meta':
                return $generator->generateAdCopy($context);
            case 'sms':
                return ['message' => $generator->generateSMSMessage($context)];
            case 'email':
                return $generator->generateEmailContent($context);
            default:
                throw new \Exception("Unsupported platform: {$this->platform}");
        }
    }

    private function launchOnPlatform(array $content): array
    {
        $campaignData = [
            'customer_id' => $this->customer->id,
            'platform' => $this->platform,
            'campaign_type' => $this->campaignType,
            'status' => 'scheduled',
            'message_content' => $content,
            'target_audience' => $this->getTargetAudience(),
            'scheduled_at' => now(),
            'cost' => $this->calculateCampaignCost()
        ];

        // Create retargeting campaign record
        $campaign = RetargetingCampaign::create($campaignData);

        // Simulate platform-specific launch
        $result = $this->simulatePlatformLaunch($content, $campaign);

        // Update campaign with results
        $campaign->update([
            'status' => $result['status'],
            'sent_at' => now(),
            'response_received' => $result['response_received'],
            'conversion_achieved' => $result['conversion_achieved'],
            'revenue_generated' => $result['revenue_generated'],
            'performance_metrics' => $result['metrics']
        ]);

        return [
            'campaign_id' => $campaign->id,
            'status' => $result['status'],
            'response_received' => $result['response_received'],
            'conversion_achieved' => $result['conversion_achieved'],
            'revenue_generated' => $result['revenue_generated'],
            'cost' => $campaign->cost
        ];
    }

    private function simulatePlatformLaunch(array $content, RetargetingCampaign $campaign): array
    {
        // Simulate platform-specific behavior
        $platformSuccessRates = [
            'whatsapp' => 0.85,
            'meta' => 0.70,
            'sms' => 0.60,
            'email' => 0.45,
            'tiktok' => 0.75,
            'google' => 0.65,
            'youtube' => 0.55
        ];

        $successRate = $platformSuccessRates[$this->platform] ?? 0.5;
        $responseReceived = rand(1, 100) <= ($successRate * 100);
        $conversionAchieved = $responseReceived && rand(1, 100) <= 30; // 30% conversion rate

        $revenueGenerated = 0;
        if ($conversionAchieved) {
            $revenueGenerated = $this->calculatePotentialRevenue();
        }

        return [
            'status' => 'sent',
            'response_received' => $responseReceived,
            'conversion_achieved' => $conversionAchieved,
            'revenue_generated' => $revenueGenerated,
            'metrics' => [
                'delivery_rate' => $successRate,
                'response_rate' => $responseReceived ? 1 : 0,
                'conversion_rate' => $conversionAchieved ? 1 : 0
            ]
        ];
    }

    private function logRetargetingAction(array $result): void
    {
        AIInteraction::create([
            'customer_id' => $this->customer->id,
            'interaction_type' => 'retargeting_message',
            'platform' => $this->platform,
            'content_generated' => ['campaign_type' => $this->campaignType],
            'ai_model_used' => 'claude-3-sonnet',
            'confidence_score' => 0.85,
            'response_received' => $result['response_received'],
            'conversion_achieved' => $result['conversion_achieved'],
            'cost' => $result['cost'],
            'revenue_generated' => $result['revenue_generated'],
            'performance_metrics' => [
                'campaign_id' => $result['campaign_id'],
                'platform' => $this->platform,
                'campaign_type' => $this->campaignType
            ]
        ]);
    }

    private function updateCustomerMetrics(array $result): void
    {
        if ($result['conversion_achieved']) {
            // Update customer lifetime value prediction
            $currentLTV = $this->customer->lifetime_value_prediction;
            $newLTV = $currentLTV + ($result['revenue_generated'] * 0.1); // 10% of revenue goes to LTV
            
            $this->customer->update([
                'lifetime_value_prediction' => $newLTV,
                'churn_probability' => max(0, $this->customer->churn_probability - 0.1) // Reduce churn risk
            ]);
        }
    }

    private function getCustomerStage(): string
    {
        if ($this->customer->orders_count === 0) return 'new';
        if ($this->customer->orders_count === 1) return 'first_time';
        if ($this->customer->orders_count >= 5) return 'loyal';
        return 'returning';
    }

    private function getLastInteraction(): string
    {
        $lastOrder = $this->customer->orders()->latest()->first();
        if (!$lastOrder) return 'none';
        
        $daysAgo = $lastOrder->created_at->diffInDays(now());
        if ($daysAgo <= 7) return 'recent';
        if ($daysAgo <= 30) return 'month_ago';
        return 'old';
    }

    private function getCampaignGoal(): string
    {
        return match($this->campaignType) {
            'abandoned_cart' => 'recovery',
            'reorder_reminder' => 'retention',
            'churn_prevention' => 'retention',
            'viral_amplification' => 'acquisition',
            default => 'conversion'
        };
    }

    private function getTargetAudience(): array
    {
        return [
            'age_range' => $this->customer->age ? $this->getAgeRange($this->customer->age) : '25-45',
            'location' => $this->customer->state ?? 'Nigeria',
            'persona' => $this->customer->persona_tag ?? 'general',
            'customer_tier' => $this->customer->getCustomerTier(),
            'acquisition_source' => $this->customer->acquisition_source ?? 'organic'
        ];
    }

    private function getAgeRange(int $age): string
    {
        if ($age < 18) return 'under_18';
        if ($age < 25) return '18-24';
        if ($age < 35) return '25-34';
        if ($age < 45) return '35-44';
        if ($age < 55) return '45-54';
        return '55_plus';
    }

    private function calculateCampaignCost(): float
    {
        $baseCosts = [
            'whatsapp' => 50,
            'meta' => 2000,
            'sms' => 25,
            'email' => 100,
            'tiktok' => 1500,
            'google' => 2500,
            'youtube' => 3000
        ];

        $baseCost = $baseCosts[$this->platform] ?? 1000;
        
        // Adjust based on customer value
        if ($this->customer->lifetime_value_prediction > 100000) {
            $baseCost *= 2;
        }
        
        // Adjust based on urgency
        if (($this->parameters['urgency'] ?? 'medium') === 'high') {
            $baseCost *= 1.5;
        }
        
        return $baseCost;
    }

    private function calculatePotentialRevenue(): float
    {
        $baseRevenue = 15000; // Average order value
        
        // Adjust based on customer tier
        $tierMultipliers = [
            'bronze' => 0.8,
            'silver' => 1.0,
            'gold' => 1.5,
            'platinum' => 2.0
        ];
        
        $multiplier = $tierMultipliers[$this->customer->getCustomerTier()] ?? 1.0;
        
        return $baseRevenue * $multiplier;
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Retargeting campaign job failed', [
            'customer_id' => $this->customer->id,
            'platform' => $this->platform,
            'campaign_type' => $this->campaignType,
            'error' => $exception->getMessage()
        ]);
    }
} 