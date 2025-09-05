<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\TrueOmnipresenceService;
use App\Models\Marketing\MarketingCustomerPresenceMap;
use App\Models\Marketing\MarketingRelevancyScoring;
use App\Models\Marketing\MarketingIntimacyTracking;
use App\Models\Marketing\MarketingTrustSignals;
use App\Models\Marketing\MarketingUnifiedExperience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OmnipresenceController extends Controller
{
    protected $omnipresenceService;
    
    public function __construct(TrueOmnipresenceService $omnipresenceService)
    {
        $this->omnipresenceService = $omnipresenceService;
    }
    
    public function analyzeCustomerPresence(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|integer|exists:customers,id'
        ]);
        
        $customerId = $request->customer_id;
        $companyId = auth()->user()->company_id;
        
        try {
            // Find where THIS customer actually is
            $presenceAnalysis = $this->omnipresenceService->analyzeCustomerPresence($customerId, $companyId);
            
            return response()->json([
                'success' => true,
                'customer_id' => $customerId,
                'recommended_channels' => $presenceAnalysis,
                'message' => 'Focus your marketing budget on these channels where your customer is actually active',
                'omnipresence_principle' => 'Be where your customers are, not everywhere',
                'channel_insights' => $this->getChannelInsights($customerId, $companyId)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    public function calculateRelevancy(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'content_id' => 'required|uuid|exists:marketing_content_library,id'
        ]);
        
        try {
            $relevancyScore = $this->omnipresenceService->calculateRelevancyScore(
                $request->customer_id,
                $request->content_id,
                auth()->user()->company_id
            );
            
            return response()->json([
                'success' => true,
                'relevancy_score' => $relevancyScore,
                'recommendation' => $relevancyScore >= 7.5 ? 'SEND - High relevancy' : 'HOLD - Low relevancy',
                'nuclear_effect_principle' => 'Relevancy: Quality of messages that relate to your audience',
                'score_breakdown' => $this->getRelevancyBreakdown($request->customer_id, $request->content_id)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    public function buildCustomerIntimacy(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'brand_id' => 'required|uuid|exists:marketing_brands,id',
            'interaction_type' => 'required|string'
        ]);
        
        try {
            $intimacyScore = $this->omnipresenceService->buildIntimacy(
                $request->customer_id,
                $request->brand_id,
                auth()->user()->company_id
            );
            
            return response()->json([
                'success' => true,
                'intimacy_score' => $intimacyScore,
                'relationship_strength' => $this->getRelationshipLevel($intimacyScore),
                'nuclear_effect_principle' => 'Intimacy: Personalized, relationship-building communications',
                'intimacy_insights' => $this->getIntimacyInsights($request->customer_id, $request->brand_id)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    public function deployTrustSignals(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|uuid|exists:marketing_brands,id'
        ]);
        
        try {
            $trustStrategy = $this->omnipresenceService->deployTrustSignals(
                $request->brand_id,
                auth()->user()->company_id
            );
            
            return response()->json([
                'success' => true,
                'trust_deployment' => $trustStrategy,
                'trust_principles' => [
                    'Authority' => 'Establish expertise in your field',
                    'Social Proof' => 'Testimonials, reviews, user-generated content',
                    'Familiarity' => 'Reassuring omnipresence through consistent messaging',
                    'Demonstration' => 'Show, don\'t just tell'
                ],
                'deployment_summary' => $this->getTrustDeploymentSummary($trustStrategy)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    public function createUnifiedExperience(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'session_id' => 'required|uuid',
            'current_channel' => 'required|string',
            'customer_intent' => 'nullable|string'
        ]);
        
        try {
            $unifiedSession = $this->omnipresenceService->createUnifiedExperience(
                $request->customer_id,
                $request->session_id,
                auth()->user()->company_id
            );
            
            return response()->json([
                'success' => true,
                'unified_session' => $unifiedSession,
                'experience_principle' => 'Context that follows the customer between channels',
                'next_best_action' => $this->getNextBestAction($unifiedSession),
                'session_insights' => $this->getSessionInsights($unifiedSession)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    public function getCustomerChannelMap(Request $request)
    {
        $companyId = auth()->user()->company_id;
        
        try {
            // Show where ALL customers are actually active
            $channelMap = DB::table('marketing_customer_presence_map')
                ->join('customers', 'marketing_customer_presence_map.customer_id', '=', 'customers.id')
                ->where('marketing_customer_presence_map.company_id', $companyId)
                ->select([
                    'channel',
                    DB::raw('COUNT(*) as customer_count'),
                    DB::raw('AVG(engagement_score) as avg_engagement'),
                    DB::raw('AVG(conversion_rate) as avg_conversion_rate'),
                    DB::raw('COUNT(CASE WHEN engagement_score >= 7.0 THEN 1 END) as high_engagement_customers')
                ])
                ->groupBy('channel')
                ->orderByDesc('avg_engagement')
                ->get();
            
            return response()->json([
                'success' => true,
                'channel_map' => $channelMap,
                'total_mapped_customers' => $channelMap->sum('customer_count'),
                'omnipresence_insights' => $this->getOmnipresenceInsights($channelMap),
                'recommendations' => $this->getChannelRecommendations($channelMap)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function getRelevancyAnalytics(Request $request)
    {
        $companyId = auth()->user()->company_id;
        
        try {
            $analytics = MarketingRelevancyScoring::forCompany($companyId)
                ->recent(30)
                ->select([
                    DB::raw('AVG(relevancy_score) as avg_relevancy'),
                    DB::raw('COUNT(CASE WHEN relevancy_score >= 7.5 THEN 1 END) as high_relevancy_count'),
                    DB::raw('COUNT(*) as total_scored'),
                    'customer_stage',
                    DB::raw('AVG(relevancy_score) as stage_avg_relevancy')
                ])
                ->groupBy('customer_stage')
                ->get();
            
            return response()->json([
                'success' => true,
                'relevancy_analytics' => $analytics,
                'overall_performance' => [
                    'avg_relevancy' => $analytics->avg('avg_relevancy'),
                    'high_relevancy_rate' => $analytics->sum('high_relevancy_count') / max(1, $analytics->sum('total_scored')),
                    'total_content_scored' => $analytics->sum('total_scored')
                ],
                'stage_insights' => $this->getStageInsights($analytics)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function getIntimacyLeaderboard(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $brandId = $request->input('brand_id');
        
        try {
            $query = MarketingIntimacyTracking::forCompany($companyId)
                ->with(['customer', 'brand'])
                ->highIntimacy();
                
            if ($brandId) {
                $query->byBrand($brandId);
            }
            
            $leaderboard = $query->orderByDesc('intimacy_score')
                ->limit(50)
                ->get();
            
            return response()->json([
                'success' => true,
                'intimacy_leaderboard' => $leaderboard,
                'leaderboard_stats' => [
                    'avg_intimacy' => $leaderboard->avg('intimacy_score'),
                    'top_performer' => $leaderboard->first(),
                    'total_strong_relationships' => $leaderboard->where('intimacy_score', '>=', 7.0)->count()
                ],
                'relationship_insights' => $this->getRelationshipInsights($leaderboard)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function getUnifiedExperienceJourney(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|integer|exists:customers,id'
        ]);
        
        $companyId = auth()->user()->company_id;
        
        try {
            $journeys = MarketingUnifiedExperience::forCompany($companyId)
                ->where('customer_id', $request->customer_id)
                ->orderByDesc('session_start')
                ->limit(10)
                ->get();
            
            return response()->json([
                'success' => true,
                'customer_journeys' => $journeys,
                'journey_analytics' => [
                    'total_sessions' => $journeys->count(),
                    'avg_session_duration' => $journeys->avg('session_duration'),
                    'multi_channel_sessions' => $journeys->where('is_multi_channel', true)->count(),
                    'most_common_entry_channel' => $journeys->pluck('entry_channel')->mode()[0] ?? 'web'
                ],
                'channel_flow_insights' => $this->getChannelFlowInsights($journeys)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Private helper methods
    private function getChannelInsights($customerId, $companyId)
    {
        return MarketingCustomerPresenceMap::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->get()
            ->map(function($presence) {
                return [
                    'channel' => $presence->channel,
                    'engagement_level' => $presence->engagement_level,
                    'conversion_effectiveness' => $presence->conversion_effectiveness,
                    'last_active' => $presence->last_active,
                    'behavior_summary' => $presence->behavior_patterns
                ];
            });
    }
    
    private function getRelevancyBreakdown($customerId, $contentId)
    {
        $scoring = MarketingRelevancyScoring::where('customer_id', $customerId)
            ->where('content_id', $contentId)
            ->latest('scored_at')
            ->first();
            
        return $scoring ? [
            'factors' => $scoring->relevancy_factors,
            'top_factors' => $scoring->top_relevancy_factors,
            'personalization_data' => $scoring->personalization_data,
            'customer_stage' => $scoring->customer_stage
        ] : null;
    }
    
    private function getRelationshipLevel($intimacyScore)
    {
        if ($intimacyScore >= 8.5) return 'Very Strong';
        if ($intimacyScore >= 7.0) return 'Strong';
        if ($intimacyScore >= 5.5) return 'Moderate';
        if ($intimacyScore >= 3.0) return 'Weak';
        return 'Very Weak';
    }
    
    private function getIntimacyInsights($customerId, $brandId)
    {
        $intimacy = MarketingIntimacyTracking::where('customer_id', $customerId)
            ->where('brand_id', $brandId)
            ->first();
            
        return $intimacy ? [
            'relationship_age' => $intimacy->relationship_age,
            'interaction_frequency' => $intimacy->interaction_frequency,
            'top_emotional_triggers' => $intimacy->top_emotional_triggers,
            'relationship_strength' => $intimacy->relationship_strength
        ] : null;
    }
    
    private function getTrustDeploymentSummary($trustStrategy)
    {
        $summary = [];
        foreach ($trustStrategy as $type => $signals) {
            $summary[$type] = [
                'signal_count' => count($signals),
                'avg_credibility' => collect($signals)->avg('credibility'),
                'deployment_channels' => collect($signals)->pluck('channels')->flatten()->unique()->values()
            ];
        }
        return $summary;
    }
    
    private function getNextBestAction($unifiedSession)
    {
        $intent = $unifiedSession->current_intent;
        $channel = $unifiedSession->current_channel;
        
        return match($intent['intent'] ?? 'browse') {
            'purchase' => [
                'action' => 'Show product recommendations',
                'priority' => 'high',
                'channel_specific' => $this->getChannelSpecificAction($channel, 'purchase')
            ],
            'support' => [
                'action' => 'Connect to customer service',
                'priority' => 'high',
                'channel_specific' => $this->getChannelSpecificAction($channel, 'support')
            ],
            'browse' => [
                'action' => 'Provide personalized content',
                'priority' => 'medium',
                'channel_specific' => $this->getChannelSpecificAction($channel, 'browse')
            ],
            default => [
                'action' => 'Continue engagement',
                'priority' => 'low',
                'channel_specific' => $this->getChannelSpecificAction($channel, 'default')
            ]
        };
    }
    
    private function getChannelSpecificAction($channel, $intent)
    {
        return match($channel) {
            'whatsapp' => "Send personalized WhatsApp message for {$intent}",
            'email' => "Send targeted email for {$intent}",
            'facebook' => "Show Facebook ad for {$intent}",
            'instagram' => "Display Instagram story for {$intent}",
            default => "Engage via {$channel} for {$intent}"
        };
    }
    
    private function getSessionInsights($unifiedSession)
    {
        return [
            'session_duration' => $unifiedSession->session_duration,
            'channel_count' => $unifiedSession->channel_count,
            'journey_path' => $unifiedSession->journey_path,
            'session_status' => $unifiedSession->session_status,
            'is_multi_channel' => $unifiedSession->is_multi_channel
        ];
    }
    
    private function getOmnipresenceInsights($channelMap)
    {
        $totalCustomers = $channelMap->sum('customer_count');
        $topChannel = $channelMap->first();
        
        return [
            'dominant_channel' => $topChannel->channel ?? 'none',
            'channel_diversity' => $channelMap->count(),
            'avg_engagement_across_channels' => $channelMap->avg('avg_engagement'),
            'high_engagement_rate' => $channelMap->sum('high_engagement_customers') / max(1, $totalCustomers),
            'omnipresence_score' => min(10, $channelMap->count() * 1.2) // More channels = higher omnipresence
        ];
    }
    
    private function getChannelRecommendations($channelMap)
    {
        $recommendations = [];
        
        foreach ($channelMap as $channel) {
            if ($channel->avg_engagement >= 7.0) {
                $recommendations[] = [
                    'channel' => $channel->channel,
                    'recommendation' => 'INVEST MORE - High engagement channel',
                    'priority' => 'high'
                ];
            } elseif ($channel->avg_engagement >= 5.0) {
                $recommendations[] = [
                    'channel' => $channel->channel,
                    'recommendation' => 'OPTIMIZE - Moderate engagement, room for improvement',
                    'priority' => 'medium'
                ];
            } else {
                $recommendations[] = [
                    'channel' => $channel->channel,
                    'recommendation' => 'EVALUATE - Low engagement, consider strategy change',
                    'priority' => 'low'
                ];
            }
        }
        
        return $recommendations;
    }
    
    private function getStageInsights($analytics)
    {
        return $analytics->map(function($stage) {
            return [
                'stage' => $stage->customer_stage,
                'avg_relevancy' => $stage->stage_avg_relevancy,
                'performance' => $stage->stage_avg_relevancy >= 7.5 ? 'Excellent' : 
                               ($stage->stage_avg_relevancy >= 6.0 ? 'Good' : 'Needs Improvement'),
                'recommendation' => $this->getStageRecommendation($stage->customer_stage, $stage->stage_avg_relevancy)
            ];
        });
    }
    
    private function getStageRecommendation($stage, $avgRelevancy)
    {
        if ($avgRelevancy >= 7.5) {
            return "Excellent relevancy for {$stage} stage - maintain current strategy";
        } elseif ($avgRelevancy >= 6.0) {
            return "Good relevancy for {$stage} stage - minor optimizations needed";
        } else {
            return "Low relevancy for {$stage} stage - review content strategy";
        }
    }
    
    private function getRelationshipInsights($leaderboard)
    {
        return [
            'relationship_distribution' => [
                'very_strong' => $leaderboard->where('intimacy_score', '>=', 8.5)->count(),
                'strong' => $leaderboard->whereBetween('intimacy_score', [7.0, 8.4])->count(),
                'moderate' => $leaderboard->whereBetween('intimacy_score', [5.5, 6.9])->count(),
                'weak' => $leaderboard->where('intimacy_score', '<', 5.5)->count()
            ],
            'avg_relationship_age' => $leaderboard->avg('relationship_age'),
            'most_common_triggers' => $leaderboard->pluck('top_emotional_triggers')->flatten()->countBy()->sortDesc()->take(5)
        ];
    }
    
    private function getChannelFlowInsights($journeys)
    {
        $channelFlows = [];
        
        foreach ($journeys as $journey) {
            if ($journey->is_multi_channel && $journey->channel_progression) {
                $flow = implode(' → ', $journey->channel_progression);
                $channelFlows[] = $flow;
            }
        }
        
        return [
            'common_flows' => collect($channelFlows)->countBy()->sortDesc()->take(5),
            'multi_channel_rate' => $journeys->where('is_multi_channel', true)->count() / max(1, $journeys->count()),
            'avg_channels_per_journey' => $journeys->avg('channel_count')
        ];
    }
}
