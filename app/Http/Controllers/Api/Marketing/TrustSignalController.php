<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingTrustSignals;
use App\Models\Marketing\MarketingBrand;
use App\Models\Marketing\MarketingCustomerTouchpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TrustSignalController extends Controller
{
    public function createTrustSignal(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|uuid|exists:marketing_brands,id',
            'signal_type' => 'required|in:authority,social_proof,familiarity,demonstration',
            'signal_content' => 'required|string|max:1000',
            'source_url' => 'nullable|url',
            'display_channels' => 'required|array|min:1',
            'display_channels.*' => 'string|in:whatsapp,email,sms,web,social_media,ads'
        ]);
        
        try {
            $trustSignal = MarketingTrustSignals::create([
                'brand_id' => $request->brand_id,
                'signal_type' => $request->signal_type,
                'signal_source' => $this->determineSignalSource($request->signal_type),
                'signal_content' => $request->signal_content,
                'source_url' => $request->source_url,
                'credibility_score' => $this->calculateCredibilityScore($request->signal_type, $request->signal_content),
                'display_channels' => $request->display_channels,
                'is_active' => true,
                'company_id' => auth()->user()->company_id,
                'created_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'trust_signal' => $trustSignal,
                'trust_principle' => $this->getTrustPrinciple($request->signal_type),
                'deployment_strategy' => $this->getDeploymentStrategy($trustSignal),
                'credibility_analysis' => $this->getCredibilityAnalysis($trustSignal)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    public function getTrustSignalRecommendations(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|uuid|exists:marketing_brands,id'
        ]);
        
        try {
            $brand = MarketingBrand::find($request->brand_id);
            $companyId = auth()->user()->company_id;
            
            // Analyze what trust signals this brand needs
            $existingSignals = MarketingTrustSignals::where('brand_id', $request->brand_id)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->groupBy('signal_type')
                ->pluck('signal_type')
                ->toArray();
                
            $allSignalTypes = ['authority', 'social_proof', 'familiarity', 'demonstration'];
            $neededSignals = array_diff($allSignalTypes, $existingSignals);
            
            $recommendations = [];
            foreach ($neededSignals as $signalType) {
                $recommendations[] = [
                    'signal_type' => $signalType,
                    'priority' => $this->getSignalPriority($signalType, $brand),
                    'suggested_content' => $this->getSuggestedContent($signalType, $brand),
                    'implementation_tips' => $this->getImplementationTips($signalType),
                    'expected_impact' => $this->getExpectedImpact($signalType)
                ];
            }
            
            return response()->json([
                'success' => true,
                'brand' => $brand->name,
                'trust_gaps' => $neededSignals,
                'recommendations' => $recommendations,
                'trust_building_strategy' => 'Build familiarity through consistent omnipresence',
                'current_trust_score' => $this->calculateBrandTrustScore($request->brand_id, $companyId)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    public function getTrustSignals(Request $request)
    {
        $request->validate([
            'brand_id' => 'nullable|uuid|exists:marketing_brands,id',
            'signal_type' => 'nullable|in:authority,social_proof,familiarity,demonstration',
            'is_active' => 'nullable|boolean'
        ]);
        
        $query = MarketingTrustSignals::where('company_id', auth()->user()->company_id)
            ->with(['brand', 'creator']);
            
        if ($request->brand_id) {
            $query->where('brand_id', $request->brand_id);
        }
        
        if ($request->signal_type) {
            $query->where('signal_type', $request->signal_type);
        }
        
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        $trustSignals = $query->orderByDesc('credibility_score')
            ->orderByDesc('created_at')
            ->paginate(20);
            
        return response()->json([
            'success' => true,
            'trust_signals' => $trustSignals,
            'trust_distribution' => $this->getTrustDistribution(auth()->user()->company_id),
            'performance_metrics' => $this->getTrustSignalPerformance(auth()->user()->company_id)
        ]);
    }
    
    public function updateTrustSignal(Request $request, $id)
    {
        $request->validate([
            'signal_content' => 'sometimes|string|max:1000',
            'source_url' => 'sometimes|nullable|url',
            'display_channels' => 'sometimes|array|min:1',
            'display_channels.*' => 'string|in:whatsapp,email,sms,web,social_media,ads',
            'is_active' => 'sometimes|boolean'
        ]);
        
        try {
            $trustSignal = MarketingTrustSignals::where('id', $id)
                ->where('company_id', auth()->user()->company_id)
                ->firstOrFail();
                
            $updateData = $request->only(['signal_content', 'source_url', 'display_channels', 'is_active']);
            
            // Recalculate credibility score if content changed
            if ($request->has('signal_content')) {
                $updateData['credibility_score'] = $this->calculateCredibilityScore(
                    $trustSignal->signal_type, 
                    $request->signal_content
                );
            }
            
            $trustSignal->update($updateData);
            
            return response()->json([
                'success' => true,
                'trust_signal' => $trustSignal->fresh(),
                'updated_deployment_strategy' => $this->getDeploymentStrategy($trustSignal)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    public function deleteTrustSignal($id)
    {
        try {
            $trustSignal = MarketingTrustSignals::where('id', $id)
                ->where('company_id', auth()->user()->company_id)
                ->firstOrFail();
                
            $trustSignal->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Trust signal deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    public function deployTrustSignal(Request $request, $id)
    {
        $request->validate([
            'channels' => 'required|array|min:1',
            'channels.*' => 'string|in:whatsapp,email,sms,web,social_media,ads',
            'target_audience' => 'nullable|array'
        ]);
        
        try {
            $trustSignal = MarketingTrustSignals::where('id', $id)
                ->where('company_id', auth()->user()->company_id)
                ->firstOrFail();
                
            // Deploy trust signal across specified channels
            $deploymentResults = [];
            
            foreach ($request->channels as $channel) {
                $result = $this->deployToChannel($trustSignal, $channel, $request->target_audience);
                $deploymentResults[$channel] = $result;
            }
            
            // Update deployment history
            $trustSignal->update([
                'last_deployed_at' => now(),
                'deployment_count' => $trustSignal->deployment_count + 1,
                'last_deployment_channels' => $request->channels
            ]);
            
            return response()->json([
                'success' => true,
                'deployment_results' => $deploymentResults,
                'trust_signal' => $trustSignal->fresh(),
                'deployment_summary' => $this->getDeploymentSummary($deploymentResults)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    public function getTrustSignalAnalytics(Request $request)
    {
        $companyId = auth()->user()->company_id;
        
        $analytics = [
            'trust_signal_distribution' => $this->getTrustDistribution($companyId),
            'performance_by_type' => $this->getPerformanceByType($companyId),
            'channel_effectiveness' => $this->getChannelEffectiveness($companyId),
            'brand_trust_scores' => $this->getBrandTrustScores($companyId),
            'deployment_trends' => $this->getDeploymentTrends($companyId)
        ];
        
        return response()->json([
            'success' => true,
            'analytics' => $analytics,
            'insights' => $this->generateTrustInsights($analytics),
            'recommendations' => $this->generateTrustRecommendations($analytics)
        ]);
    }
    
    // Private helper methods
    private function determineSignalSource($signalType)
    {
        return match($signalType) {
            'authority' => 'expertise_credentials',
            'social_proof' => 'customer_testimonials',
            'familiarity' => 'brand_consistency',
            'demonstration' => 'proof_of_results',
            default => 'unknown'
        };
    }
    
    private function calculateCredibilityScore($signalType, $content)
    {
        $baseScore = 5.0;
        $contentLength = strlen($content);
        
        // Scoring based on signal type
        $typeMultiplier = match($signalType) {
            'authority' => 1.2,
            'social_proof' => 1.1,
            'demonstration' => 1.15,
            'familiarity' => 1.0,
            default => 1.0
        };
        
        // Content quality factors
        $qualityScore = 0;
        
        // Length factor (optimal range: 100-500 characters)
        if ($contentLength >= 100 && $contentLength <= 500) {
            $qualityScore += 2.0;
        } elseif ($contentLength >= 50) {
            $qualityScore += 1.0;
        }
        
        // Keyword relevance (simple check for trust-building words)
        $trustKeywords = ['proven', 'certified', 'experienced', 'trusted', 'verified', 'guaranteed', 'results', 'success'];
        $keywordCount = 0;
        foreach ($trustKeywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $keywordCount++;
            }
        }
        $qualityScore += min(2.0, $keywordCount * 0.5);
        
        $finalScore = ($baseScore + $qualityScore) * $typeMultiplier;
        
        return min(10.0, max(1.0, $finalScore));
    }
    
    private function getTrustPrinciple($signalType)
    {
        return match($signalType) {
            'authority' => 'Establish expertise and credibility in your field',
            'social_proof' => 'Show that others trust and use your product/service',
            'familiarity' => 'Build reassuring omnipresence through consistent messaging',
            'demonstration' => 'Show results and proof, don\'t just make claims',
            default => 'Build trust through authentic communication'
        };
    }
    
    private function getDeploymentStrategy($trustSignal)
    {
        return [
            'primary_channels' => $trustSignal->display_channels,
            'optimal_timing' => $this->getOptimalTiming($trustSignal->signal_type),
            'frequency_recommendation' => $this->getFrequencyRecommendation($trustSignal->signal_type),
            'audience_targeting' => $this->getAudienceTargeting($trustSignal->signal_type),
            'content_placement' => $this->getContentPlacement($trustSignal->signal_type)
        ];
    }
    
    private function getSuggestedContent($signalType, $brand)
    {
        $nigerianContext = [
            'authority' => [
                'Years of experience serving Nigerian businesses',
                'Certified by relevant Nigerian regulatory bodies',
                'Featured in Nigerian business publications',
                'Partnerships with established Nigerian companies',
                'Awards from Nigerian industry associations'
            ],
            'social_proof' => [
                'Testimonials from satisfied Nigerian customers',
                'Before/after success stories from local clients',
                'Number of Nigerian businesses served',
                'Reviews from customers in major Nigerian cities',
                'Case studies from different Nigerian industries'
            ],
            'familiarity' => [
                'Consistent presence across Nigerian social media',
                'Regular valuable content about Nigerian market',
                'Community involvement in Nigerian business events',
                'Understanding of Nigerian business culture',
                'Local customer service in Nigerian time zones'
            ],
            'demonstration' => [
                'Live demos of product solving Nigerian business problems',
                'Free trials specifically for Nigerian market',
                'Money-back guarantees with Naira pricing',
                'Case studies with specific ROI in Naira',
                'Video testimonials from Nigerian business owners'
            ]
        ];
        
        return $nigerianContext[$signalType] ?? [];
    }
    
    private function getImplementationTips($signalType)
    {
        return match($signalType) {
            'authority' => [
                'Display credentials prominently on all channels',
                'Share industry insights regularly',
                'Participate in industry events and conferences',
                'Publish thought leadership content'
            ],
            'social_proof' => [
                'Collect and display customer testimonials',
                'Encourage user-generated content',
                'Show real customer numbers and statistics',
                'Feature customer success stories'
            ],
            'familiarity' => [
                'Maintain consistent brand voice across channels',
                'Share valuable content regularly',
                'Engage with your community consistently',
                'Be present where your customers are active'
            ],
            'demonstration' => [
                'Offer free trials or demos',
                'Share specific results and metrics',
                'Provide guarantees and warranties',
                'Show before/after comparisons'
            ],
            default => []
        };
    }
    
    private function getSignalPriority($signalType, $brand)
    {
        // Priority based on brand maturity and industry
        $industryPriorities = [
            'authority' => ['consulting', 'legal', 'medical', 'financial'],
            'social_proof' => ['ecommerce', 'retail', 'hospitality', 'consumer'],
            'demonstration' => ['software', 'technology', 'manufacturing', 'services'],
            'familiarity' => ['startup', 'new_brand', 'local_business']
        ];
        
        foreach ($industryPriorities as $type => $industries) {
            if ($type === $signalType && in_array($brand->industry, $industries)) {
                return 'high';
            }
        }
        
        return 'medium';
    }
    
    private function getTrustDistribution($companyId)
    {
        return MarketingTrustSignals::where('company_id', $companyId)
            ->where('is_active', true)
            ->groupBy('signal_type')
            ->select('signal_type', DB::raw('count(*) as count'), DB::raw('avg(credibility_score) as avg_score'))
            ->get();
    }
    
    private function calculateBrandTrustScore($brandId, $companyId)
    {
        $trustSignals = MarketingTrustSignals::where('brand_id', $brandId)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get();
            
        if ($trustSignals->isEmpty()) {
            return 0;
        }
        
        $typeWeights = [
            'authority' => 0.3,
            'social_proof' => 0.25,
            'demonstration' => 0.25,
            'familiarity' => 0.2
        ];
        
        $weightedScore = 0;
        $totalWeight = 0;
        
        foreach ($trustSignals->groupBy('signal_type') as $type => $signals) {
            $avgScore = $signals->avg('credibility_score');
            $weight = $typeWeights[$type] ?? 0.1;
            $weightedScore += $avgScore * $weight;
            $totalWeight += $weight;
        }
        
        return $totalWeight > 0 ? $weightedScore / $totalWeight : 0;
    }
    
    private function getOptimalTiming($signalType)
    {
        return match($signalType) {
            'authority' => 'During business hours when decision makers are active',
            'social_proof' => 'After positive customer interactions or purchases',
            'demonstration' => 'When prospects are in consideration phase',
            'familiarity' => 'Consistently throughout customer journey',
            default => 'Based on customer activity patterns'
        };
    }
    
    private function getFrequencyRecommendation($signalType)
    {
        return match($signalType) {
            'authority' => 'Weekly - establish ongoing credibility',
            'social_proof' => 'After each positive outcome - capitalize on success',
            'demonstration' => 'Monthly - avoid overwhelming prospects',
            'familiarity' => 'Daily - build consistent presence',
            default => 'Weekly'
        };
    }
    
    private function getAudienceTargeting($signalType)
    {
        return match($signalType) {
            'authority' => 'Decision makers and industry professionals',
            'social_proof' => 'Prospects in consideration phase',
            'demonstration' => 'Prospects ready to evaluate solutions',
            'familiarity' => 'All audience segments consistently',
            default => 'General audience'
        };
    }
    
    private function getContentPlacement($signalType)
    {
        return match($signalType) {
            'authority' => 'Header, about page, professional profiles',
            'social_proof' => 'Product pages, checkout process, landing pages',
            'demonstration' => 'Product demos, case study sections',
            'familiarity' => 'All touchpoints consistently',
            default => 'Strategic locations based on customer journey'
        };
    }
    
    private function getExpectedImpact($signalType)
    {
        return match($signalType) {
            'authority' => 'Increased credibility and trust from prospects',
            'social_proof' => 'Higher conversion rates and reduced hesitation',
            'demonstration' => 'Faster decision making and increased confidence',
            'familiarity' => 'Improved brand recognition and customer loyalty',
            default => 'Enhanced overall trust and engagement'
        };
    }
    
    private function deployToChannel($trustSignal, $channel, $targetAudience = null)
    {
        // Simulate deployment to different channels
        return [
            'status' => 'deployed',
            'channel' => $channel,
            'deployment_time' => now(),
            'estimated_reach' => $this->estimateReach($channel, $targetAudience),
            'deployment_id' => \Str::uuid()
        ];
    }
    
    private function estimateReach($channel, $targetAudience)
    {
        $baseReach = match($channel) {
            'whatsapp' => 500,
            'email' => 1000,
            'sms' => 300,
            'web' => 2000,
            'social_media' => 1500,
            'ads' => 5000,
            default => 100
        };
        
        return $targetAudience ? count($targetAudience) : $baseReach;
    }
    
    private function getDeploymentSummary($deploymentResults)
    {
        $totalReach = array_sum(array_column($deploymentResults, 'estimated_reach'));
        $channelCount = count($deploymentResults);
        
        return [
            'total_estimated_reach' => $totalReach,
            'channels_deployed' => $channelCount,
            'deployment_status' => 'completed',
            'deployment_time' => now()
        ];
    }
    
    private function getTrustSignalPerformance($companyId)
    {
        return [
            'total_signals' => MarketingTrustSignals::where('company_id', $companyId)->count(),
            'active_signals' => MarketingTrustSignals::where('company_id', $companyId)->where('is_active', true)->count(),
            'avg_credibility_score' => MarketingTrustSignals::where('company_id', $companyId)->avg('credibility_score'),
            'total_deployments' => MarketingTrustSignals::where('company_id', $companyId)->sum('deployment_count')
        ];
    }
    
    private function getPerformanceByType($companyId)
    {
        return MarketingTrustSignals::where('company_id', $companyId)
            ->groupBy('signal_type')
            ->select(
                'signal_type',
                DB::raw('count(*) as signal_count'),
                DB::raw('avg(credibility_score) as avg_credibility'),
                DB::raw('sum(deployment_count) as total_deployments')
            )
            ->get();
    }
    
    private function getChannelEffectiveness($companyId)
    {
        // This would require tracking actual performance data
        // For now, return placeholder data
        return [
            'whatsapp' => ['deployment_count' => 150, 'avg_engagement' => 8.2],
            'email' => ['deployment_count' => 200, 'avg_engagement' => 6.5],
            'sms' => ['deployment_count' => 100, 'avg_engagement' => 7.1],
            'web' => ['deployment_count' => 300, 'avg_engagement' => 5.8],
            'social_media' => ['deployment_count' => 250, 'avg_engagement' => 7.8],
            'ads' => ['deployment_count' => 180, 'avg_engagement' => 6.9]
        ];
    }
    
    private function getBrandTrustScores($companyId)
    {
        return MarketingBrand::where('company_id', $companyId)
            ->with(['trustSignals' => function($query) {
                $query->where('is_active', true);
            }])
            ->get()
            ->map(function($brand) use ($companyId) {
                return [
                    'brand_id' => $brand->id,
                    'brand_name' => $brand->name,
                    'trust_score' => $this->calculateBrandTrustScore($brand->id, $companyId),
                    'signal_count' => $brand->trustSignals->count()
                ];
            });
    }
    
    private function getDeploymentTrends($companyId)
    {
        return MarketingTrustSignals::where('company_id', $companyId)
            ->whereNotNull('last_deployed_at')
            ->selectRaw('DATE(last_deployed_at) as date, count(*) as deployments')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();
    }
    
    private function generateTrustInsights($analytics)
    {
        $insights = [];
        
        // Signal distribution insights
        $distribution = collect($analytics['trust_signal_distribution']);
        if ($distribution->isEmpty()) {
            $insights[] = "No trust signals deployed yet - start building trust with your audience";
        } else {
            $topType = $distribution->sortByDesc('count')->first();
            $insights[] = "Most used trust signal type: {$topType['signal_type']} with {$topType['count']} signals";
        }
        
        // Performance insights
        $performance = collect($analytics['performance_by_type']);
        if ($performance->isNotEmpty()) {
            $bestPerforming = $performance->sortByDesc('avg_credibility')->first();
            $insights[] = "Best performing signal type: {$bestPerforming['signal_type']} with {$bestPerforming['avg_credibility']} avg credibility";
        }
        
        return $insights;
    }
    
    private function generateTrustRecommendations($analytics)
    {
        $recommendations = [];
        
        $distribution = collect($analytics['trust_signal_distribution']);
        $allTypes = ['authority', 'social_proof', 'familiarity', 'demonstration'];
        $usedTypes = $distribution->pluck('signal_type')->toArray();
        $missingTypes = array_diff($allTypes, $usedTypes);
        
        foreach ($missingTypes as $type) {
            $recommendations[] = [
                'type' => 'missing_signal',
                'signal_type' => $type,
                'recommendation' => "Add {$type} signals to build comprehensive trust",
                'priority' => 'medium'
            ];
        }
        
        // Low credibility recommendations
        $lowCredibility = $distribution->where('avg_score', '<', 6.0);
        foreach ($lowCredibility as $signal) {
            $recommendations[] = [
                'type' => 'improve_quality',
                'signal_type' => $signal['signal_type'],
                'recommendation' => "Improve {$signal['signal_type']} signal quality (current score: {$signal['avg_score']})",
                'priority' => 'high'
            ];
        }
        
        return $recommendations;
    }
}
