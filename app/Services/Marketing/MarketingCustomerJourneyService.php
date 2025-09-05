<?php

namespace App\Services\Marketing;

use App\Models\Customer;
use App\Models\Marketing\MarketingCustomerTouchpoint;
use App\Models\Marketing\MarketingCampaign;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MarketingCustomerJourneyService
{
    protected $customer;
    protected $companyId;

    public function __construct($customer = null, $companyId = null)
    {
        $this->customer = $customer;
        $this->companyId = $companyId;
    }

    /**
     * Map customer journey stages
     */
    public function mapCustomerJourney($customerId = null)
    {
        $customer = $customerId ? Customer::find($customerId) : $this->customer;
        
        if (!$customer) {
            throw new \Exception('Customer not found');
        }

        $touchpoints = MarketingCustomerTouchpoint::where('customer_id', $customer->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $journeyStages = [
            'awareness' => $this->analyzeAwarenessStage($customer, $touchpoints),
            'consideration' => $this->analyzeConsiderationStage($customer, $touchpoints),
            'decision' => $this->analyzeDecisionStage($customer, $touchpoints),
            'purchase' => $this->analyzePurchaseStage($customer, $touchpoints),
            'retention' => $this->analyzeRetentionStage($customer, $touchpoints),
            'advocacy' => $this->analyzeAdvocacyStage($customer, $touchpoints)
        ];

        return [
            'customer_id' => $customer->id,
            'current_stage' => $this->determineCurrentStage($journeyStages),
            'stages' => $journeyStages,
            'touchpoints' => $touchpoints,
            'journey_score' => $this->calculateJourneyScore($journeyStages),
            'recommendations' => $this->generateJourneyRecommendations($journeyStages)
        ];
    }

    /**
     * Analyze awareness stage
     */
    protected function analyzeAwarenessStage($customer, $touchpoints)
    {
        $awarenessTouchpoints = $touchpoints->filter(function ($touchpoint) {
            return in_array($touchpoint->touchpoint_type, [
                'first_visit', 'social_media_exposure', 'ad_impression', 'search_click'
            ]);
        });

        return [
            'status' => $awarenessTouchpoints->count() > 0 ? 'completed' : 'pending',
            'touchpoints' => $awarenessTouchpoints,
            'score' => min(100, $awarenessTouchpoints->count() * 25),
            'duration' => $this->calculateStageDuration($awarenessTouchpoints),
            'channels' => $awarenessTouchpoints->pluck('channel')->unique()
        ];
    }

    /**
     * Analyze consideration stage
     */
    protected function analyzeConsiderationStage($customer, $touchpoints)
    {
        $considerationTouchpoints = $touchpoints->filter(function ($touchpoint) {
            return in_array($touchpoint->touchpoint_type, [
                'website_visit', 'product_view', 'content_consumption', 'email_open'
            ]);
        });

        return [
            'status' => $considerationTouchpoints->count() > 0 ? 'completed' : 'pending',
            'touchpoints' => $considerationTouchpoints,
            'score' => min(100, $considerationTouchpoints->count() * 20),
            'duration' => $this->calculateStageDuration($considerationTouchpoints),
            'channels' => $considerationTouchpoints->pluck('channel')->unique()
        ];
    }

    /**
     * Analyze decision stage
     */
    protected function analyzeDecisionStage($customer, $touchpoints)
    {
        $decisionTouchpoints = $touchpoints->filter(function ($touchpoint) {
            return in_array($touchpoint->touchpoint_type, [
                'cart_add', 'checkout_start', 'offer_view', 'comparison_view'
            ]);
        });

        return [
            'status' => $decisionTouchpoints->count() > 0 ? 'completed' : 'pending',
            'touchpoints' => $decisionTouchpoints,
            'score' => min(100, $decisionTouchpoints->count() * 30),
            'duration' => $this->calculateStageDuration($decisionTouchpoints),
            'channels' => $decisionTouchpoints->pluck('channel')->unique()
        ];
    }

    /**
     * Analyze purchase stage
     */
    protected function analyzePurchaseStage($customer, $touchpoints)
    {
        $purchaseTouchpoints = $touchpoints->filter(function ($touchpoint) {
            return in_array($touchpoint->touchpoint_type, [
                'purchase_completed', 'payment_success', 'order_confirmation'
            ]);
        });

        return [
            'status' => $purchaseTouchpoints->count() > 0 ? 'completed' : 'pending',
            'touchpoints' => $purchaseTouchpoints,
            'score' => $purchaseTouchpoints->count() > 0 ? 100 : 0,
            'duration' => $this->calculateStageDuration($purchaseTouchpoints),
            'channels' => $purchaseTouchpoints->pluck('channel')->unique()
        ];
    }

    /**
     * Analyze retention stage
     */
    protected function analyzeRetentionStage($customer, $touchpoints)
    {
        $retentionTouchpoints = $touchpoints->filter(function ($touchpoint) {
            return in_array($touchpoint->touchpoint_type, [
                'repeat_purchase', 'loyalty_program', 'feedback_provided', 'support_contact'
            ]);
        });

        return [
            'status' => $retentionTouchpoints->count() > 0 ? 'completed' : 'pending',
            'touchpoints' => $retentionTouchpoints,
            'score' => min(100, $retentionTouchpoints->count() * 25),
            'duration' => $this->calculateStageDuration($retentionTouchpoints),
            'channels' => $retentionTouchpoints->pluck('channel')->unique()
        ];
    }

    /**
     * Analyze advocacy stage
     */
    protected function analyzeAdvocacyStage($customer, $touchpoints)
    {
        $advocacyTouchpoints = $touchpoints->filter(function ($touchpoint) {
            return in_array($touchpoint->touchpoint_type, [
                'referral_made', 'review_posted', 'social_share', 'testimonial_provided'
            ]);
        });

        return [
            'status' => $advocacyTouchpoints->count() > 0 ? 'completed' : 'pending',
            'touchpoints' => $advocacyTouchpoints,
            'score' => min(100, $advocacyTouchpoints->count() * 50),
            'duration' => $this->calculateStageDuration($advocacyTouchpoints),
            'channels' => $advocacyTouchpoints->pluck('channel')->unique()
        ];
    }

    /**
     * Determine current stage
     */
    protected function determineCurrentStage($stages)
    {
        foreach ($stages as $stageName => $stage) {
            if ($stage['status'] === 'pending') {
                return $stageName;
            }
        }
        return 'advocacy'; // All stages completed
    }

    /**
     * Calculate journey score
     */
    protected function calculateJourneyScore($stages)
    {
        $totalScore = 0;
        $completedStages = 0;

        foreach ($stages as $stage) {
            $totalScore += $stage['score'];
            if ($stage['status'] === 'completed') {
                $completedStages++;
            }
        }

        return [
            'overall_score' => round($totalScore / 6, 2),
            'completed_stages' => $completedStages,
            'total_stages' => 6
        ];
    }

    /**
     * Generate journey recommendations
     */
    protected function generateJourneyRecommendations($stages)
    {
        $recommendations = [];

        foreach ($stages as $stageName => $stage) {
            if ($stage['status'] === 'pending') {
                $recommendations[] = $this->getStageRecommendations($stageName, $stage);
            }
        }

        return $recommendations;
    }

    /**
     * Get recommendations for specific stage
     */
    protected function getStageRecommendations($stageName, $stage)
    {
        $recommendations = [
            'stage' => $stageName,
            'priority' => 'high',
            'actions' => []
        ];

        switch ($stageName) {
            case 'awareness':
                $recommendations['actions'] = [
                    'Launch targeted social media campaigns',
                    'Create engaging content for SEO',
                    'Run display advertising campaigns',
                    'Implement influencer partnerships'
                ];
                break;

            case 'consideration':
                $recommendations['actions'] = [
                    'Send personalized email sequences',
                    'Create product comparison content',
                    'Implement retargeting campaigns',
                    'Offer free trials or demos'
                ];
                break;

            case 'decision':
                $recommendations['actions'] = [
                    'Send limited-time offers',
                    'Provide social proof and testimonials',
                    'Offer free shipping or discounts',
                    'Create urgency with countdown timers'
                ];
                break;

            case 'purchase':
                $recommendations['actions'] = [
                    'Streamline checkout process',
                    'Offer multiple payment options',
                    'Provide clear shipping information',
                    'Send order confirmation emails'
                ];
                break;

            case 'retention':
                $recommendations['actions'] = [
                    'Implement loyalty program',
                    'Send follow-up satisfaction surveys',
                    'Offer exclusive member benefits',
                    'Provide excellent customer support'
                ];
                break;

            case 'advocacy':
                $recommendations['actions'] = [
                    'Encourage reviews and testimonials',
                    'Implement referral program',
                    'Create shareable content',
                    'Recognize and reward advocates'
                ];
                break;
        }

        return $recommendations;
    }

    /**
     * Calculate stage duration
     */
    protected function calculateStageDuration($touchpoints)
    {
        if ($touchpoints->count() < 2) {
            return 0;
        }

        $firstTouchpoint = $touchpoints->first();
        $lastTouchpoint = $touchpoints->last();

        return Carbon::parse($firstTouchpoint->created_at)
            ->diffInDays(Carbon::parse($lastTouchpoint->created_at));
    }

    /**
     * Create customer journey automation
     */
    public function createJourneyAutomation($customerId, $triggerStage, $actions)
    {
        $customer = Customer::findOrFail($customerId);
        
        // Create automation record
        $automation = [
            'customer_id' => $customer->id,
            'trigger_stage' => $triggerStage,
            'actions' => $actions,
            'status' => 'active',
            'created_at' => now()
        ];

        // Log the automation
        Log::info("Customer journey automation created", $automation);

        return $automation;
    }

    /**
     * Track customer touchpoint
     */
    public function trackTouchpoint($customerId, $touchpointType, $channel, $metadata = [])
    {
        $touchpoint = MarketingCustomerTouchpoint::create([
            'customer_id' => $customerId,
            'touchpoint_type' => $touchpointType,
            'channel' => $channel,
            'status' => 'completed',
            'metadata' => $metadata,
            'company_id' => $this->companyId
        ]);

        Log::info("Customer touchpoint tracked", [
            'customer_id' => $customerId,
            'touchpoint_type' => $touchpointType,
            'channel' => $channel
        ]);

        return $touchpoint;
    }
}
