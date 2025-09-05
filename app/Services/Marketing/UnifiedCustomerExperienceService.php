<?php

namespace App\Services\Marketing;

use App\Models\Customer;
use App\Models\Marketing\UCXCustomerProfile;
use App\Models\Marketing\UCXContextualContinuity;
use App\Models\Marketing\UCXRealTimePersonalization;
use App\Models\Marketing\UCXEmotionalJourneyMapping;
use App\Models\Marketing\UCXSingleSourceTruth;
use App\Models\Marketing\MarketingCustomerTouchpoint;
use App\Models\Marketing\MarketingCustomerPresenceMap;
use App\Models\Sale;
use Carbon\Carbon;

class UnifiedCustomerExperienceService
{
    public function createUnifiedProfile($customerId, $companyId)
    {
        // Build single source of truth for customer
        $customer = Customer::with(['orders', 'interactions', 'preferences', 'marketingTouchpoints'])->find($customerId);
        
        // Gather data from all systems
        $unifiedData = [
            'demographic' => $this->gatherDemographicData($customer),
            'behavioral' => $this->gatherBehavioralData($customer),
            'transactional' => $this->gatherTransactionData($customer),
            'engagement' => $this->gatherEngagementData($customer),
            'preferences' => $this->gatherPreferenceData($customer),
            'context' => $this->gatherCurrentContext($customer)
        ];
        
        // Real-time context analysis
        $realTimeContext = [
            'current_activity' => $this->detectCurrentActivity($customer),
            'emotional_state' => $this->analyzeEmotionalState($customer),
            'intent_signals' => $this->detectIntentSignals($customer),
            'urgency_level' => $this->assessUrgencyLevel($customer),
            'channel_preferences' => $this->getCurrentChannelPreferences($customer)
        ];
        
        // Store in unified profile
        UCXCustomerProfile::updateOrCreate([
            'customer_id' => $customerId,
            'company_id' => $companyId
        ], [
            'unified_profile' => $unifiedData,
            'real_time_context' => $realTimeContext,
            'behavior_patterns' => $this->identifyBehaviorPatterns($customer),
            'preferences_learned' => $this->extractLearnedPreferences($customer),
            'emotional_state' => $realTimeContext['emotional_state'],
            'last_interaction' => now(),
            'current_journey_stage' => $this->determineJourneyStage($customer),
            'next_best_actions' => $this->generateNextBestActions($customer, $realTimeContext)
        ]);
        
        return $unifiedData;
    }
    
    public function maintainContextualContinuity($customerId, $fromChannel, $toChannel, $sessionId, $companyId)
    {
        // Ensure context follows customer across channels
        $activeSession = UCXContextualContinuity::where('customer_id', $customerId)
            ->where('session_active', true)
            ->first();
            
        if (!$activeSession) {
            // Create new session
            $activeSession = UCXContextualContinuity::create([
                'customer_id' => $customerId,
                'session_id' => $sessionId,
                'entry_channel' => $fromChannel,
                'current_channel' => $toChannel,
                'channel_progression' => [$fromChannel, $toChannel],
                'context_data' => $this->gatherChannelContext($customerId, $fromChannel),
                'carried_context' => $this->identifyPortableContext($customerId),
                'personalization_applied' => [],
                'session_start' => now(),
                'last_activity' => now(),
                'company_id' => $companyId
            ]);
        } else {
            // Update existing session
            $progression = $activeSession->channel_progression;
            if (end($progression) !== $toChannel) {
                $progression[] = $toChannel;
            }
            
            $activeSession->update([
                'current_channel' => $toChannel,
                'channel_progression' => $progression,
                'context_data' => array_merge(
                    $activeSession->context_data ?? [],
                    $this->gatherChannelContext($customerId, $toChannel)
                ),
                'last_activity' => now()
            ]);
        }
        
        return $activeSession;
    }
    
    public function applyRealTimePersonalization($customerId, $triggerEvent, $channel, $companyId)
    {
        // Real-time personalization based on live customer data
        $customerProfile = UCXCustomerProfile::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->first();
            
        if (!$customerProfile) {
            $customerProfile = $this->createUnifiedProfile($customerId, $companyId);
        }
        
        $currentContext = $customerProfile->real_time_context;
        
        // Determine personalization strategy
        $personalizationStrategy = $this->calculatePersonalizationStrategy(
            $currentContext,
            $triggerEvent,
            $channel
        );
        
        // Apply personalization
        $personalizationApplied = [
            'content_adaptation' => $this->adaptContent($personalizationStrategy),
            'channel_optimization' => $this->optimizeForChannel($channel, $currentContext),
            'timing_optimization' => $this->optimizeTiming($currentContext),
            'emotional_targeting' => $this->targetEmotions($currentContext['emotional_state']),
            'relevancy_enhancement' => $this->enhanceRelevancy($customerProfile, $triggerEvent)
        ];
        
        // Log personalization
        UCXRealTimePersonalization::create([
            'customer_id' => $customerId,
            'trigger_event' => $triggerEvent,
            'customer_context_at_trigger' => $currentContext,
            'personalization_applied' => $personalizationApplied,
            'channel_applied' => $channel,
            'decision_factors' => $personalizationStrategy['decision_factors'],
            'relevancy_score' => $personalizationStrategy['relevancy_score'],
            'applied_at' => now(),
            'company_id' => $companyId
        ]);
        
        return $personalizationApplied;
    }
    
    public function mapEmotionalJourney($customerId, $journeyId, $companyId)
    {
        // Track emotional states throughout customer journey
        $customer = Customer::find($customerId);
        $currentStage = $this->determineJourneyStage($customer);
        
        // Analyze current emotional state
        $emotionalAnalysis = $this->analyzeEmotionalState($customer);
        
        // Identify emotional triggers
        $triggers = $this->identifyEmotionalTriggers($customer);
        
        // Map emotions to journey
        UCXEmotionalJourneyMapping::create([
            'customer_id' => $customerId,
            'journey_id' => $journeyId,
            'journey_stage' => $currentStage,
            'emotional_markers' => $emotionalAnalysis['markers'],
            'emotional_intensity' => $emotionalAnalysis['intensity'],
            'triggers_identified' => $triggers,
            'sentiment_analysis' => $emotionalAnalysis['sentiment'],
            'channel_when_measured' => request()->header('Channel', 'web'),
            'response_strategy' => $this->generateEmotionalResponseStrategy($emotionalAnalysis),
            'measured_at' => now(),
            'company_id' => $companyId
        ]);
        
        return $emotionalAnalysis;
    }
    
    public function unifyDataSources($customerId, $companyId)
    {
        // Break down data silos and create single source of truth
        $dataSources = [
            'crm' => $this->getCRMData($customerId),
            'marketing' => $this->getMarketingData($customerId),
            'sales' => $this->getSalesData($customerId),
            'support' => $this->getSupportData($customerId),
            'analytics' => $this->getAnalyticsData($customerId),
            'social' => $this->getSocialData($customerId)
        ];
        
        // Resolve conflicts and merge data
        $unifiedData = $this->resolveDataConflicts($dataSources);
        
        // Store unified data
        foreach (['profile', 'preferences', 'behavior', 'context'] as $dataType) {
            UCXSingleSourceTruth::updateOrCreate([
                'customer_id' => $customerId,
                'data_type' => $dataType,
                'company_id' => $companyId
            ], [
                'unified_data' => $unifiedData[$dataType],
                'data_sources' => array_keys($dataSources),
                'last_sync' => now(),
                'sync_conflicts' => $unifiedData['conflicts'][$dataType] ?? null,
                'access_permissions' => $this->getDataAccessPermissions($dataType)
            ]);
        }
        
        return $unifiedData;
    }
    
    private function analyzeEmotionalState($customer)
    {
        // Analyze customer's current emotional state
        $recentTouchpoints = MarketingCustomerTouchpoint::where('customer_id', $customer->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->get();
            
        $emotionalMarkers = [];
        $sentimentScores = [];
        
        foreach ($recentTouchpoints as $touchpoint) {
            if ($touchpoint->emotional_response) {
                $emotionalMarkers[] = $touchpoint->emotional_response['emotion'];
                $sentimentScores[] = $touchpoint->emotional_response['sentiment_score'];
            }
        }
        
        // Determine primary emotional state
        $primaryEmotion = $this->calculatePrimaryEmotion($emotionalMarkers);
        $averageSentiment = count($sentimentScores) > 0 ? array_sum($sentimentScores) / count($sentimentScores) : 5;
        
        return [
            'markers' => $emotionalMarkers,
            'primary_emotion' => $primaryEmotion,
            'intensity' => $this->calculateEmotionalIntensity($emotionalMarkers),
            'sentiment' => [
                'score' => $averageSentiment,
                'label' => $this->getSentimentLabel($averageSentiment)
            ],
            'confidence' => $this->calculateEmotionalConfidence($emotionalMarkers)
        ];
    }
    
    private function generateNextBestActions($customer, $context)
    {
        // AI-powered next best action recommendations
        $actions = [];
        
        // Based on emotional state
        if ($context['emotional_state']['primary_emotion'] === 'confused') {
            $actions[] = [
                'action' => 'provide_clarification',
                'channel' => 'whatsapp',
                'priority' => 'high',
                'content_type' => 'educational'
            ];
        }
        
        // Based on journey stage
        if ($context['intent_signals']['purchase_intent'] > 8) {
            $actions[] = [
                'action' => 'provide_incentive',
                'channel' => $this->getPreferredChannel($customer),
                'priority' => 'urgent',
                'content_type' => 'offer'
            ];
        }
        
        // Based on engagement patterns
        if ($context['urgency_level'] === 'high') {
            $actions[] = [
                'action' => 'immediate_outreach',
                'channel' => 'whatsapp',
                'priority' => 'critical',
                'content_type' => 'personal_message'
            ];
        }
        
        return $actions;
    }
    
    private function calculatePersonalizationStrategy($context, $triggerEvent, $channel)
    {
        // Calculate how to personalize experience
        $strategy = [
            'decision_factors' => [
                'emotional_state' => $context['emotional_state']['primary_emotion'],
                'journey_stage' => $this->determineStageFromContext($context),
                'channel_preference' => $context['channel_preferences'][$channel] ?? 5,
                'urgency_level' => $context['urgency_level'],
                'trigger_event' => $triggerEvent
            ]
        ];
        
        // Calculate relevancy score
        $relevancyScore = $this->calculateContextualRelevancy($strategy['decision_factors']);
        $strategy['relevancy_score'] = $relevancyScore;
        
        return $strategy;
    }
    
    // Helper methods for data gathering and analysis
    private function gatherDemographicData($customer)
    {
        return [
            'age' => $customer->age ?? null,
            'gender' => $customer->gender ?? null,
            'location' => $customer->location ?? null,
            'occupation' => $customer->occupation ?? null,
            'income_level' => $customer->income_level ?? null
        ];
    }
    
    private function gatherBehavioralData($customer)
    {
        $touchpoints = $customer->marketingTouchpoints()->latest()->take(50)->get();
        
        return [
            'interaction_frequency' => $touchpoints->count(),
            'preferred_channels' => $touchpoints->groupBy('channel')->map->count()->toArray(),
            'engagement_patterns' => $this->analyzeEngagementPatterns($touchpoints),
            'response_times' => $this->calculateResponseTimes($touchpoints)
        ];
    }
    
    private function gatherTransactionData($customer)
    {
        $orders = $customer->orders ?? collect();
        
        return [
            'total_orders' => $orders->count(),
            'total_spent' => $orders->sum('total_amount'),
            'average_order_value' => $orders->avg('total_amount'),
            'last_purchase' => $orders->max('created_at'),
            'purchase_frequency' => $this->calculatePurchaseFrequency($orders)
        ];
    }
    
    private function determineJourneyStage($customer)
    {
        $orders = $customer->orders ?? collect();
        $touchpoints = $customer->marketingTouchpoints ?? collect();
        
        if ($orders->count() > 3) {
            return 'loyal_customer';
        } elseif ($orders->count() > 0) {
            return 'repeat_customer';
        } elseif ($touchpoints->where('interaction_type', 'clicked')->count() > 5) {
            return 'consideration';
        } elseif ($touchpoints->count() > 0) {
            return 'awareness';
        }
        
        return 'unknown';
    }
    
    private function calculatePrimaryEmotion($emotionalMarkers)
    {
        if (empty($emotionalMarkers)) {
            return 'neutral';
        }
        
        $emotionCounts = array_count_values($emotionalMarkers);
        return array_key_first($emotionCounts);
    }
    
    private function calculateEmotionalIntensity($emotionalMarkers)
    {
        // Simple intensity calculation based on frequency and variety
        $uniqueEmotions = count(array_unique($emotionalMarkers));
        $totalMarkers = count($emotionalMarkers);
        
        if ($totalMarkers === 0) return 0;
        
        return min(10, ($totalMarkers / 5) + ($uniqueEmotions / 2));
    }
    
    private function getSentimentLabel($score)
    {
        if ($score >= 7) return 'positive';
        if ($score >= 4) return 'neutral';
        return 'negative';
    }
    
    private function calculateEmotionalConfidence($emotionalMarkers)
    {
        if (empty($emotionalMarkers)) return 0;
        
        $emotionCounts = array_count_values($emotionalMarkers);
        $maxCount = max($emotionCounts);
        $totalCount = count($emotionalMarkers);
        
        return ($maxCount / $totalCount) * 10;
    }
    
    private function getPreferredChannel($customer)
    {
        $presenceMap = MarketingCustomerPresenceMap::where('customer_id', $customer->id)
            ->orderByDesc('engagement_score')
            ->first();
            
        return $presenceMap->channel ?? 'email';
    }
    
    private function calculateContextualRelevancy($factors)
    {
        // Weight relevancy factors based on proven marketing psychology
        $weights = [
            'emotional_state' => 0.25,
            'journey_stage' => 0.20,
            'channel_preference' => 0.15,
            'urgency_level' => 0.15,
            'trigger_event' => 0.25
        ];
        
        $score = 0;
        foreach ($factors as $factor => $value) {
            if (isset($weights[$factor])) {
                $normalizedValue = is_numeric($value) ? $value : $this->normalizeFactorValue($factor, $value);
                $score += ($normalizedValue * $weights[$factor]);
            }
        }
        
        return min(10, max(0, $score));
    }
    
    private function normalizeFactorValue($factor, $value)
    {
        // Convert non-numeric values to scores
        return match($factor) {
            'emotional_state' => match($value) {
                'excited', 'happy', 'interested' => 9,
                'confused', 'frustrated' => 3,
                'angry', 'disappointed' => 1,
                default => 5
            },
            'journey_stage' => match($value) {
                'loyal_customer' => 10,
                'repeat_customer' => 8,
                'consideration' => 6,
                'awareness' => 4,
                default => 2
            },
            'urgency_level' => match($value) {
                'critical' => 10,
                'high' => 8,
                'medium' => 5,
                'low' => 2,
                default => 1
            },
            default => 5
        };
    }
    
    // Additional helper methods would go here...
    private function gatherChannelContext($customerId, $channel) { return []; }
    private function identifyPortableContext($customerId) { return []; }
    private function adaptContent($strategy) { return []; }
    private function optimizeForChannel($channel, $context) { return []; }
    private function optimizeTiming($context) { return []; }
    private function targetEmotions($emotionalState) { return []; }
    private function enhanceRelevancy($profile, $event) { return []; }
    private function identifyEmotionalTriggers($customer) { return []; }
    private function generateEmotionalResponseStrategy($analysis) { return []; }
    private function getCRMData($customerId) { return []; }
    private function getMarketingData($customerId) { return []; }
    private function getSalesData($customerId) { return []; }
    private function getSupportData($customerId) { return []; }
    private function getAnalyticsData($customerId) { return []; }
    private function getSocialData($customerId) { return []; }
    private function resolveDataConflicts($dataSources) { return []; }
    private function getDataAccessPermissions($dataType) { return []; }
}
