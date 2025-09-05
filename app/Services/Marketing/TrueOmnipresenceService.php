<?php

namespace App\Services\Marketing;

use App\Models\Customer;
use App\Models\Marketing\MarketingCustomerPresenceMap;
use App\Models\Marketing\MarketingRelevancyScoring;
use App\Models\Marketing\MarketingIntimacyTracking;
use App\Models\Marketing\MarketingTrustSignals;
use App\Models\Marketing\MarketingUnifiedExperience;
use App\Models\Marketing\MarketingContentLibrary;
use App\Models\Marketing\MarketingCustomerTouchpoint;
use Illuminate\Support\Facades\DB;

class TrueOmnipresenceService
{
    public function analyzeCustomerPresence($customerId, $companyId)
    {
        $customer = Customer::find($customerId);
        if (!$customer) throw new \Exception('Customer not found');

        $presenceData = [
            'whatsapp' => $this->analyzeChannelPresence($customer, 'whatsapp'),
            'facebook' => $this->analyzeChannelPresence($customer, 'facebook'),
            'instagram' => $this->analyzeChannelPresence($customer, 'instagram'),
            'email' => $this->analyzeChannelPresence($customer, 'email'),
            'sms' => $this->analyzeChannelPresence($customer, 'sms'),
            'tiktok' => $this->analyzeChannelPresence($customer, 'tiktok'),
            'google' => $this->analyzeChannelPresence($customer, 'google'),
        ];
        
        foreach ($presenceData as $channel => $data) {
            MarketingCustomerPresenceMap::updateOrCreate([
                'customer_id' => $customerId,
                'channel' => $channel,
                'company_id' => $companyId
            ], $data);
        }
        
        return $this->getRecommendedChannels($customerId);
    }
    
    public function calculateRelevancyScore($customerId, $contentId, $companyId)
    {
        $customer = Customer::with(['orders'])->find($customerId);
        $content = MarketingContentLibrary::find($contentId);
        
        if (!$customer || !$content) throw new \Exception('Customer or content not found');

        $factors = [
            'customer_stage' => $this->getCustomerStage($customer),
            'purchase_history' => $this->analyzePurchaseHistory($customer),
            'interaction_history' => $this->analyzeInteractionHistory($customer),
            'demographic_match' => $this->calculateDemographicMatch($customer, $content),
            'behavioral_match' => $this->calculateBehavioralMatch($customer, $content),
            'timing_relevance' => $this->calculateTimingRelevance($customer),
            'emotional_state' => $this->determineEmotionalState($customer)
        ];
        
        $relevancyScore = $this->calculateWeightedRelevancy($factors);
        
        MarketingRelevancyScoring::create([
            'customer_id' => $customerId,
            'content_id' => $contentId,
            'relevancy_score' => $relevancyScore,
            'relevancy_factors' => $factors,
            'customer_stage' => $this->getCustomerStageString($factors['customer_stage']),
            'personalization_data' => $this->generatePersonalizationData($customer, $factors),
            'company_id' => $companyId,
            'scored_at' => now()
        ]);
        
        return $relevancyScore;
    }
    
    public function buildIntimacy($customerId, $brandId, $companyId)
    {
        $intimacyData = MarketingIntimacyTracking::firstOrCreate([
            'customer_id' => $customerId,
            'brand_id' => $brandId,
            'company_id' => $companyId
        ], [
            'intimacy_score' => 0,
            'relationship_started' => now()
        ]);
        
        $factors = [
            'interaction_frequency' => $this->calculateInteractionFrequency($customerId, $brandId),
            'interaction_quality' => $this->assessInteractionQuality($customerId, $brandId),
            'emotional_connection' => $this->measureEmotionalConnection($customerId, $brandId),
            'trust_level' => $this->assessTrustLevel($customerId, $brandId),
            'personalization_success' => $this->measurePersonalizationSuccess($customerId, $brandId)
        ];
        
        $newIntimacyScore = $this->calculateIntimacyScore($factors);
        
        $intimacyData->update([
            'intimacy_score' => $newIntimacyScore,
            'total_interactions' => $intimacyData->total_interactions + 1,
            'interaction_quality' => $factors,
            'emotional_triggers' => $this->identifyEmotionalTriggers($customerId, $brandId)
        ]);
        
        return $newIntimacyScore;
    }
    
    public function deployTrustSignals($brandId, $companyId)
    {
        $trustSignals = MarketingTrustSignals::where('brand_id', $brandId)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get();
            
        return [
            'authority' => $this->deploySignalsByType($trustSignals, 'authority'),
            'social_proof' => $this->deploySignalsByType($trustSignals, 'social_proof'),
            'familiarity' => $this->deploySignalsByType($trustSignals, 'familiarity'),
            'demonstration' => $this->deploySignalsByType($trustSignals, 'demonstration')
        ];
    }
    
    public function createUnifiedExperience($customerId, $sessionId, $companyId)
    {
        $customer = Customer::find($customerId);
        $activeSession = MarketingUnifiedExperience::where('customer_id', $customerId)
            ->whereNull('session_end')
            ->first();
            
        if (!$activeSession) {
            $activeSession = MarketingUnifiedExperience::create([
                'customer_id' => $customerId,
                'session_id' => $sessionId,
                'context_data' => $this->gatherCustomerContext($customer),
                'current_intent' => $this->determineCustomerIntent($customer),
                'current_channel' => request()->header('Channel', 'web'),
                'entry_channel' => request()->header('Channel', 'web'),
                'channel_progression' => [request()->header('Channel', 'web')],
                'session_start' => now(),
                'company_id' => $companyId
            ]);
        } else {
            $progression = $activeSession->channel_progression ?? [];
            $progression[] = request()->header('Channel', 'web');
            
            $activeSession->update([
                'current_channel' => request()->header('Channel', 'web'),
                'channel_progression' => $progression,
                'context_data' => $this->gatherCustomerContext($customer),
                'current_intent' => $this->determineCustomerIntent($customer)
            ]);
        }
        
        return $activeSession;
    }

    private function analyzeChannelPresence($customer, $channel)
    {
        $touchpoints = MarketingCustomerTouchpoint::where('customer_id', $customer->id)
            ->where('channel', $channel)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        return [
            'engagement_score' => $this->calculateEngagementScore($touchpoints, $channel),
            'frequency_hours' => $this->getActiveHours($touchpoints),
            'behavior_patterns' => $this->analyzeBehaviorPatterns($touchpoints),
            'conversion_rate' => $this->calculateConversionRate($customer, $channel),
            'last_active' => $touchpoints->max('created_at')
        ];
    }

    private function calculateEngagementScore($touchpoints, $channel)
    {
        if ($touchpoints->isEmpty()) return 0;

        $totalInteractions = $touchpoints->count();
        $positiveInteractions = $touchpoints->whereIn('interaction_type', ['like', 'click', 'reply'])->count();
        $baseScore = min(10, ($positiveInteractions / max(1, $totalInteractions)) * 10);
        
        $channelMultiplier = match($channel) {
            'whatsapp' => 1.2, 'email' => 1.1, 'sms' => 1.1,
            'facebook', 'instagram' => 1.0, 'tiktok' => 0.9, 'google' => 0.8,
            default => 1.0
        };

        return min(10, $baseScore * $channelMultiplier);
    }

    private function getActiveHours($touchpoints)
    {
        if ($touchpoints->isEmpty()) return null;
        return $touchpoints->pluck('created_at')->map(fn($date) => $date->hour)->countBy()->sortDesc()->keys()->first();
    }

    private function analyzeBehaviorPatterns($touchpoints)
    {
        if ($touchpoints->isEmpty()) return [];
        
        return [
            'primary_interactions' => $touchpoints->pluck('interaction_type')->countBy()->sortDesc()->take(3)->keys()->toArray(),
            'peak_hours' => $touchpoints->pluck('created_at')->map(fn($date) => $date->hour)->countBy()->sortDesc()->take(3)->keys()->toArray(),
            'interaction_frequency' => $touchpoints->count() / max(1, $touchpoints->pluck('created_at')->unique('toDateString')->count())
        ];
    }

    private function calculateConversionRate($customer, $channel)
    {
        $touchpoints = MarketingCustomerTouchpoint::where('customer_id', $customer->id)->where('channel', $channel)->count();
        $conversions = $customer->orders()->whereHas('marketingTouchpoints', fn($q) => $q->where('channel', $channel))->count();
        return $touchpoints > 0 ? $conversions / $touchpoints : 0;
    }

    private function getRecommendedChannels($customerId)
    {
        return MarketingCustomerPresenceMap::where('customer_id', $customerId)
            ->orderByDesc('engagement_score')->orderByDesc('conversion_rate')->limit(3)->pluck('channel')->toArray();
    }

    private function getCustomerStage($customer)
    {
        $orderCount = $customer->orders()->count();
        $lastOrder = $customer->orders()->latest()->first();
        
        if ($orderCount === 0) return 8.0;
        if ($orderCount === 1) return 7.0;
        if ($lastOrder && $lastOrder->created_at->diffInDays() < 30) return 9.0;
        if ($orderCount > 5) return 6.0;
        return 5.0;
    }

    private function analyzePurchaseHistory($customer)
    {
        $orders = $customer->orders()->get();
        if ($orders->isEmpty()) return 3.0;
        
        $totalValue = $orders->sum('total_amount');
        $avgOrderValue = $totalValue / $orders->count();
        $daysSinceLastOrder = $orders->max('created_at')->diffInDays();
        
        $recencyScore = max(0, 10 - ($daysSinceLastOrder / 10));
        $valueScore = min(10, $avgOrderValue / 1000);
        
        return ($recencyScore + $valueScore) / 2;
    }

    private function analyzeInteractionHistory($customer)
    {
        $interactions = MarketingCustomerTouchpoint::where('customer_id', $customer->id)
            ->where('created_at', '>=', now()->subDays(90))->get();
        if ($interactions->isEmpty()) return 2.0;
        
        $positiveInteractions = $interactions->whereIn('interaction_type', ['click', 'reply', 'like', 'share'])->count();
        return ($positiveInteractions / max(1, $interactions->count())) * 10;
    }

    private function calculateDemographicMatch($customer, $content)
    {
        $contentAudience = $content->brand->target_audience ?? [];
        $matches = 0; $total = 0;
        
        if (isset($contentAudience['age_range']) && $customer->age) {
            $total++; $ageRange = explode('-', $contentAudience['age_range']);
            if (count($ageRange) === 2 && $customer->age >= $ageRange[0] && $customer->age <= $ageRange[1]) $matches++;
        }
        
        if (isset($contentAudience['location']) && $customer->location) {
            $total++;
            if (stripos($customer->location, $contentAudience['location']) !== false) $matches++;
        }
        
        return $total > 0 ? ($matches / $total) * 10 : 5.0;
    }

    private function calculateBehavioralMatch($customer, $content)
    {
        $customerBehaviors = MarketingCustomerTouchpoint::where('customer_id', $customer->id)->pluck('touchpoint_type')->countBy();
        $contentType = $content->content_type;
        $similarEngagement = $customerBehaviors->get($contentType, 0);
        $totalEngagement = $customerBehaviors->sum();
        
        return $totalEngagement === 0 ? 5.0 : ($similarEngagement / $totalEngagement) * 10;
    }

    private function calculateTimingRelevance($customer)
    {
        $hour = now()->hour; $dayOfWeek = now()->dayOfWeek;
        
        $customerActivity = MarketingCustomerTouchpoint::where('customer_id', $customer->id)
            ->selectRaw('HOUR(created_at) as hour, DAYOFWEEK(created_at) as day_of_week, COUNT(*) as activity_count')
            ->groupBy('hour', 'day_of_week')->get();
            
        $currentTimeActivity = $customerActivity->where('hour', $hour)->where('day_of_week', $dayOfWeek + 1)->first();
        if (!$currentTimeActivity) return 5.0;
        
        $maxActivity = $customerActivity->max('activity_count');
        return $maxActivity > 0 ? ($currentTimeActivity->activity_count / $maxActivity) * 10 : 5.0;
    }

    private function determineEmotionalState($customer)
    {
        $recentTouchpoints = MarketingCustomerTouchpoint::where('customer_id', $customer->id)
            ->where('created_at', '>=', now()->subDays(7))->whereNotNull('emotional_response')->get();
        if ($recentTouchpoints->isEmpty()) return 5.0;
        
        $positiveEmotions = $recentTouchpoints->where('emotional_response.sentiment', 'positive')->count();
        return ($positiveEmotions / $recentTouchpoints->count()) * 10;
    }

    private function calculateWeightedRelevancy($factors)
    {
        $weights = [
            'customer_stage' => 0.25, 'behavioral_match' => 0.20, 'timing_relevance' => 0.15,
            'emotional_state' => 0.15, 'demographic_match' => 0.10, 'purchase_history' => 0.10, 'interaction_history' => 0.05
        ];
        
        $score = 0;
        foreach ($factors as $factor => $value) {
            $score += ($value * ($weights[$factor] ?? 0));
        }
        
        return min(10, max(0, $score));
    }

    private function getCustomerStageString($stageScore)
    {
        if ($stageScore >= 8.5) return 'awareness';
        if ($stageScore >= 7.0) return 'consideration';
        if ($stageScore >= 5.0) return 'decision';
        return 'loyalty';
    }

    private function generatePersonalizationData($customer, $factors)
    {
        return [
            'preferred_channels' => $this->getRecommendedChannels($customer->id),
            'optimal_timing' => $this->getOptimalTiming($customer),
            'emotional_triggers' => $this->getEmotionalTriggers($customer),
            'content_preferences' => $this->getContentPreferences($customer),
            'personalization_score' => array_sum($factors) / count($factors)
        ];
    }

    private function calculateInteractionFrequency($customerId, $brandId)
    {
        $interactions = MarketingCustomerTouchpoint::where('customer_id', $customerId)
            ->where('brand_id', $brandId)->where('created_at', '>=', now()->subDays(30))->count();
        return min(10, $interactions / 3);
    }

    private function assessInteractionQuality($customerId, $brandId)
    {
        $touchpoints = MarketingCustomerTouchpoint::where('customer_id', $customerId)->where('brand_id', $brandId)->get();
        if ($touchpoints->isEmpty()) return 0;
        
        $qualityScore = 0;
        foreach ($touchpoints as $touchpoint) {
            $qualityScore += match($touchpoint->interaction_type) {
                'view' => 1, 'click' => 2, 'like' => 3, 'share' => 4, 'reply' => 5, 'purchase' => 10, default => 0
            };
        }
        
        return min(10, $qualityScore / $touchpoints->count());
    }

    private function measureEmotionalConnection($customerId, $brandId)
    {
        $emotionalTouchpoints = MarketingCustomerTouchpoint::where('customer_id', $customerId)
            ->where('brand_id', $brandId)->whereNotNull('emotional_response')->get();
        if ($emotionalTouchpoints->isEmpty()) return 3.0;
        
        $positiveEmotions = $emotionalTouchpoints->where('emotional_response.sentiment', 'positive')->count();
        return ($positiveEmotions / $emotionalTouchpoints->count()) * 10;
    }

    private function assessTrustLevel($customerId, $brandId)
    {
        $customer = Customer::find($customerId);
        $orderCount = $customer->orders()->count();
        $repeatPurchases = $customer->orders()->where('created_at', '>=', now()->subMonths(6))->count();
        
        if ($orderCount === 0) return 2.0;
        if ($repeatPurchases > 1) return 9.0;
        if ($orderCount === 1) return 6.0;
        return 5.0;
    }

    private function measurePersonalizationSuccess($customerId, $brandId)
    {
        $personalizedTouchpoints = MarketingCustomerTouchpoint::where('customer_id', $customerId)
            ->where('brand_id', $brandId)->whereNotNull('relevancy_score')->get();
        return $personalizedTouchpoints->isEmpty() ? 5.0 : $personalizedTouchpoints->avg('relevancy_score');
    }

    private function calculateIntimacyScore($factors)
    {
        $weights = [
            'interaction_frequency' => 0.25, 'interaction_quality' => 0.25, 'emotional_connection' => 0.20,
            'trust_level' => 0.20, 'personalization_success' => 0.10
        ];
        
        $score = 0;
        foreach ($factors as $factor => $value) {
            $score += ($value * ($weights[$factor] ?? 0));
        }
        
        return min(10, max(0, $score));
    }

    private function identifyEmotionalTriggers($customerId, $brandId)
    {
        $touchpoints = MarketingCustomerTouchpoint::where('customer_id', $customerId)
            ->where('brand_id', $brandId)->whereNotNull('emotional_response')->get();
            
        $triggers = [];
        foreach ($touchpoints as $touchpoint) {
            $response = $touchpoint->emotional_response;
            if (is_array($response) && ($response['sentiment'] ?? '') === 'positive' && ($response['engagement'] ?? 0) > 7) {
                $triggers[] = [
                    'trigger' => $response['trigger_type'] ?? 'unknown',
                    'strength' => $response['engagement'] ?? 0,
                    'context' => $response['context'] ?? ''
                ];
            }
        }
        
        return $triggers;
    }

    private function deploySignalsByType($signals, $type)
    {
        return $signals->where('signal_type', $type)->map(function($signal) use ($type) {
            return [
                'type' => $type,
                'content' => $signal->signal_content,
                'credibility' => $signal->credibility_score,
                'channels' => $signal->display_channels,
                'deployment_strategy' => $this->getDeploymentStrategy($type)
            ];
        })->values()->toArray();
    }

    private function getDeploymentStrategy($type)
    {
        return match($type) {
            'authority' => 'Display prominently on high-traffic pages',
            'social_proof' => 'Show during decision-making moments',
            'familiarity' => 'Consistent presence across all touchpoints',
            'demonstration' => 'Include in product showcases and demos',
            default => 'Standard deployment'
        };
    }

    private function gatherCustomerContext($customer)
    {
        return [
            'recent_activity' => $customer->orders()->latest()->limit(3)->pluck('created_at'),
            'preferences' => $this->getContentPreferences($customer),
            'current_stage' => $this->getCustomerStageString($this->getCustomerStage($customer))
        ];
    }

    private function determineCustomerIntent($customer)
    {
        $recentActivity = MarketingCustomerTouchpoint::where('customer_id', $customer->id)
            ->where('created_at', '>=', now()->subHours(24))->get();
            
        if ($recentActivity->isEmpty()) return ['intent' => 'browse', 'confidence' => 0.5];
        
        $intentSignals = $recentActivity->pluck('touchpoint_type')->countBy();
        $topIntent = $intentSignals->sortDesc()->keys()->first();
        
        return [
            'intent' => $topIntent ?? 'browse',
            'confidence' => min(1.0, $intentSignals->max() / $recentActivity->count())
        ];
    }

    private function getOptimalTiming($customer)
    {
        $activity = MarketingCustomerTouchpoint::where('customer_id', $customer->id)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')->orderByDesc('count')->first();
        return $activity ? $activity->hour : 10;
    }

    private function getEmotionalTriggers($customer)
    {
        return MarketingCustomerTouchpoint::where('customer_id', $customer->id)
            ->whereNotNull('emotional_response')->where('emotional_response->sentiment', 'positive')
            ->pluck('emotional_response->trigger_type')->unique()->values()->toArray();
    }

    private function getContentPreferences($customer)
    {
        return MarketingCustomerTouchpoint::where('customer_id', $customer->id)
            ->join('marketing_content_library', 'marketing_customer_touchpoints.content_id', '=', 'marketing_content_library.id')
            ->selectRaw('marketing_content_library.content_type, COUNT(*) as engagement_count')
            ->groupBy('marketing_content_library.content_type')->orderByDesc('engagement_count')
            ->pluck('content_type')->toArray();
    }
}
