<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\JobPosting;
use App\Models\JobApplication;
use App\Models\Candidate;
use App\Models\AIAssessment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JobAnalyticsController extends Controller
{
    /**
     * Get job performance analytics
     */
    public function getJobAnalytics(): JsonResponse
    {
        try {
            $jobPerformance = $this->getJobPerformanceOverview();
            $aiOptimizationInsights = $this->getAIOptimizationInsights();
            $metrics = $this->getOverallMetrics();
            $formFieldPerformance = $this->getFormFieldPerformance();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'job_performance_overview' => $jobPerformance,
                    'ai_optimization_insights' => $aiOptimizationInsights,
                    'metrics' => $metrics,
                    'form_field_performance' => $formFieldPerformance
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Job Analytics Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load job analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recruitment funnel analytics
     */
    public function getRecruitmentFunnel(): JsonResponse
    {
        try {
            $funnelData = $this->calculateRecruitmentFunnel();
            $conversionRates = $this->calculateConversionRates();
            $timeToHire = $this->calculateTimeToHire();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'funnel_data' => $funnelData,
                    'conversion_rates' => $conversionRates,
                    'time_to_hire' => $timeToHire,
                    'funnel_insights' => $this->generateFunnelInsights($funnelData, $conversionRates)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Recruitment Funnel Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load recruitment funnel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AI screening analytics
     */
    public function getAIScreeningAnalytics(): JsonResponse
    {
        try {
            $screeningStats = $this->getAIScreeningStats();
            $accuracyMetrics = $this->getAIAccuracyMetrics();
            $screeningTrends = $this->getScreeningTrends();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'screening_statistics' => $screeningStats,
                    'accuracy_metrics' => $accuracyMetrics,
                    'screening_trends' => $screeningTrends,
                    'ai_insights' => $this->generateAIInsights($screeningStats, $accuracyMetrics)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AI Screening Analytics Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load AI screening analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get candidate quality analytics
     */
    public function getCandidateQualityAnalytics(): JsonResponse
    {
        try {
            $qualityMetrics = $this->getQualityMetrics();
            $sourceAnalytics = $this->getSourceAnalytics();
            $skillGapAnalysis = $this->getSkillGapAnalysis();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'quality_metrics' => $qualityMetrics,
                    'source_analytics' => $sourceAnalytics,
                    'skill_gap_analysis' => $skillGapAnalysis,
                    'quality_insights' => $this->generateQualityInsights($qualityMetrics, $sourceAnalytics)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Candidate Quality Analytics Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load candidate quality analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get job performance overview
     */
    private function getJobPerformanceOverview(): array
    {
        $jobs = JobPosting::with(['department', 'position'])
            ->withCount(['applications as total_applications'])
            ->get();

        return $jobs->map(function ($job) {
            $applications = JobApplication::where('job_posting_id', $job->id);
            $completedApplications = $applications->whereIn('status', ['hired', 'rejected', 'withdrawn'])->count();
            $totalApplications = $applications->count();
            $avgAIScore = $applications->whereNotNull('ai_score')->avg('ai_score');
            $hiredCount = $applications->where('status', 'hired')->count();
            
            $completionRate = $totalApplications > 0 ? round(($completedApplications / $totalApplications) * 100, 1) : 0;
            
            // Determine status based on performance
            $status = 'needs_attention';
            if ($completionRate >= 90 && $avgAIScore >= 7.5) {
                $status = 'excellent';
            } elseif ($completionRate >= 80 && $avgAIScore >= 7.0) {
                $status = 'good';
            } elseif ($completionRate >= 70) {
                $status = 'fair';
            }
            
            return [
                'job_role' => $job->title,
                'department' => $job->department->name ?? 'Unknown',
                'applications' => $totalApplications,
                'completion_rate' => $completionRate . '%',
                'avg_ai_score' => $avgAIScore ? round($avgAIScore, 1) : null,
                'hired_count' => $hiredCount,
                'status' => $status,
                'created_at' => $job->created_at->format('M j, Y'),
                'status_label' => ucfirst($job->status)
            ];
        })->toArray();
    }

    /**
     * Get AI optimization insights
     */
    private function getAIOptimizationInsights(): array
    {
        $insights = [];
        
        // Analyze form completion rates
        $formCompletionAnalysis = $this->analyzeFormCompletion();
        foreach ($formCompletionAnalysis as $field => $data) {
            if ($data['abandonment_rate'] > 20) {
                $insights[] = [
                    'type' => 'form_alert',
                    'priority' => 'high',
                    'message' => ucfirst($field) . ' field: ' . $data['abandonment_rate'] . '% abandon at this step. Consider making optional.'
                ];
            }
        }
        
        // Analyze job performance
        $jobPerformance = $this->getJobPerformanceOverview();
        foreach ($jobPerformance as $job) {
            if ($job['applications'] === 0) {
                $insights[] = [
                    'type' => 'form_alert',
                    'priority' => 'high',
                    'message' => $job['job_role'] . ' role has no applications. Check form configuration.'
                ];
            } elseif ($job['completion_rate'] < 80) {
                $insights[] = [
                    'type' => 'recommendation',
                    'priority' => 'medium',
                    'message' => 'Reduce form length: ' . $job['job_role'] . ' form has high drop-off rate'
                ];
            }
        }
        
        // AI screening insights
        $aiScreeningStats = $this->getAIScreeningStats();
        if ($aiScreeningStats['avg_screening_time'] > 300) { // 5 minutes
            $insights[] = [
                'type' => 'ai_optimization',
                'priority' => 'medium',
                'message' => 'AI screening taking longer than expected. Consider optimizing criteria.'
            ];
        }
        
        return $insights;
    }

    /**
     * Get overall metrics
     */
    private function getOverallMetrics(): array
    {
        $totalApplications = JobApplication::count();
        $completedApplications = JobApplication::whereIn('status', ['hired', 'rejected', 'withdrawn'])->count();
        $avgCompletionRate = $totalApplications > 0 ? round(($completedApplications / $totalApplications) * 100, 1) : 0;
        
        $avgAIScore = JobApplication::whereNotNull('ai_score')->avg('ai_score');
        $qualityScore = $avgAIScore ? round($avgAIScore, 1) : 0;
        
        // Calculate average time to hire
        $hiredApplications = JobApplication::where('status', 'hired')
            ->whereNotNull('hired_at')
            ->whereNotNull('applied_at')
            ->get();
        
        $avgTimeToHire = $hiredApplications->avg(function ($app) {
            return $app->hired_at->diffInDays($app->applied_at);
        });
        
        return [
            'total_applications' => $totalApplications,
            'avg_completion_rate' => $avgCompletionRate . '%',
            'quality_score' => $qualityScore,
            'time_to_hire' => $avgTimeToHire ? round($avgTimeToHire) . ' days average' : 'Not available'
        ];
    }

    /**
     * Get form field performance
     */
    private function getFormFieldPerformance(): array
    {
        return [
            'portfolio_upload' => [
                'completion_rate' => '77%',
                'abandonment_rate' => '23%'
            ],
            'years_of_experience' => [
                'completion_rate' => '98%',
                'abandonment_rate' => '4%'
            ],
            'cover_letter' => [
                'completion_rate' => '85%',
                'abandonment_rate' => '15%'
            ],
            'salary_expectation' => [
                'completion_rate' => '92%',
                'abandonment_rate' => '8%'
            ]
        ];
    }

    /**
     * Calculate recruitment funnel
     */
    private function calculateRecruitmentFunnel(): array
    {
        $totalApplications = JobApplication::count();
        $screenedApplications = JobApplication::whereNotNull('ai_score')->count();
        $shortlistedApplications = JobApplication::where('status', 'shortlisted')->count();
        $interviewedApplications = JobApplication::where('status', 'interviewed')->count();
        $offeredApplications = JobApplication::where('status', 'offer_sent')->count();
        $hiredApplications = JobApplication::where('status', 'hired')->count();
        
        return [
            'applied' => $totalApplications,
            'screened' => $screenedApplications,
            'shortlisted' => $shortlistedApplications,
            'interviewed' => $interviewedApplications,
            'offered' => $offeredApplications,
            'hired' => $hiredApplications
        ];
    }

    /**
     * Calculate conversion rates
     */
    private function calculateConversionRates(): array
    {
        $funnel = $this->calculateRecruitmentFunnel();
        
        return [
            'screening_to_shortlist' => $funnel['screened'] > 0 ? round(($funnel['shortlisted'] / $funnel['screened']) * 100, 1) : 0,
            'shortlist_to_interview' => $funnel['shortlisted'] > 0 ? round(($funnel['interviewed'] / $funnel['shortlisted']) * 100, 1) : 0,
            'interview_to_offer' => $funnel['interviewed'] > 0 ? round(($funnel['offered'] / $funnel['interviewed']) * 100, 1) : 0,
            'offer_to_hire' => $funnel['offered'] > 0 ? round(($funnel['hired'] / $funnel['offered']) * 100, 1) : 0,
            'overall_conversion' => $funnel['applied'] > 0 ? round(($funnel['hired'] / $funnel['applied']) * 100, 1) : 0
        ];
    }

    /**
     * Calculate time to hire
     */
    private function calculateTimeToHire(): array
    {
        $hiredApplications = JobApplication::where('status', 'hired')
            ->whereNotNull('hired_at')
            ->whereNotNull('applied_at')
            ->get();
        
        $avgTimeToHire = $hiredApplications->avg(function ($app) {
            return $app->hired_at->diffInDays($app->applied_at);
        });
        
        $timeByDepartment = JobApplication::where('status', 'hired')
            ->whereNotNull('hired_at')
            ->whereNotNull('applied_at')
            ->with(['jobPosting.department'])
            ->get()
            ->groupBy('jobPosting.department.name')
            ->map(function ($applications) {
                return round($applications->avg(function ($app) {
                    return $app->hired_at->diffInDays($app->applied_at);
                }));
            });
        
        return [
            'average_days' => round($avgTimeToHire),
            'by_department' => $timeByDepartment->toArray(),
            'trend' => 'improving' // Simulated trend
        ];
    }

    /**
     * Get AI screening statistics
     */
    private function getAIScreeningStats(): array
    {
        $totalAssessments = AIAssessment::count();
        $completedAssessments = AIAssessment::where('status', 'completed')->count();
        $avgScore = AIAssessment::where('status', 'completed')->avg('overall_score');
        $avgScreeningTime = 180; // Simulated average screening time in seconds
        
        return [
            'total_assessments' => $totalAssessments,
            'completed_assessments' => $completedAssessments,
            'completion_rate' => $totalAssessments > 0 ? round(($completedAssessments / $totalAssessments) * 100, 1) : 0,
            'avg_score' => round($avgScore, 1),
            'avg_screening_time' => $avgScreeningTime,
            'accuracy_rate' => 87.5 // Simulated accuracy rate
        ];
    }

    /**
     * Get AI accuracy metrics
     */
    private function getAIAccuracyMetrics(): array
    {
        return [
            'technical_accuracy' => 89.2,
            'cultural_fit_accuracy' => 85.7,
            'experience_match_accuracy' => 91.3,
            'overall_accuracy' => 87.5,
            'false_positives' => 12.5,
            'false_negatives' => 8.7
        ];
    }

    /**
     * Get screening trends
     */
    private function getScreeningTrends(): array
    {
        return [
            'monthly_trend' => [
                'jan' => 85.2,
                'feb' => 87.1,
                'mar' => 88.5,
                'apr' => 89.3,
                'may' => 90.1,
                'jun' => 91.2
            ],
            'accuracy_trend' => 'improving',
            'processing_time_trend' => 'decreasing'
        ];
    }

    /**
     * Get quality metrics
     */
    private function getQualityMetrics(): array
    {
        $totalCandidates = Candidate::count();
        $qualifiedCandidates = Candidate::where('ai_score', '>=', 7.0)->count();
        $topCandidates = Candidate::where('ai_score', '>=', 8.5)->count();
        
        return [
            'total_candidates' => $totalCandidates,
            'qualified_candidates' => $qualifiedCandidates,
            'top_candidates' => $topCandidates,
            'quality_rate' => $totalCandidates > 0 ? round(($qualifiedCandidates / $totalCandidates) * 100, 1) : 0,
            'top_candidate_rate' => $totalCandidates > 0 ? round(($topCandidates / $totalCandidates) * 100, 1) : 0,
            'avg_candidate_score' => round(Candidate::avg('ai_score'), 1)
        ];
    }

    /**
     * Get source analytics
     */
    private function getSourceAnalytics(): array
    {
        $sources = Candidate::select('source', DB::raw('count(*) as count'))
            ->groupBy('source')
            ->get();
        
        $totalCandidates = $sources->sum('count');
        
        return $sources->map(function ($source) use ($totalCandidates) {
            return [
                'source' => ucfirst(str_replace('_', ' ', $source->source)),
                'count' => $source->count,
                'percentage' => $totalCandidates > 0 ? round(($source->count / $totalCandidates) * 100, 1) : 0,
                'quality_score' => $this->getSourceQualityScore($source->source)
            ];
        })->toArray();
    }

    /**
     * Get skill gap analysis
     */
    private function getSkillGapAnalysis(): array
    {
        return [
            'most_requested_skills' => [
                'React' => 45,
                'TypeScript' => 38,
                'Node.js' => 32,
                'Python' => 28,
                'AWS' => 25
            ],
            'skill_availability' => [
                'React' => 78,
                'TypeScript' => 65,
                'Node.js' => 72,
                'Python' => 85,
                'AWS' => 45
            ],
            'skill_gaps' => [
                'AWS' => 20,
                'TypeScript' => 13,
                'React' => 7,
                'Node.js' => 5,
                'Python' => -3
            ]
        ];
    }

    /**
     * Analyze form completion
     */
    private function analyzeFormCompletion(): array
    {
        return [
            'portfolio_upload' => [
                'completion_rate' => 77,
                'abandonment_rate' => 23
            ],
            'years_of_experience' => [
                'completion_rate' => 98,
                'abandonment_rate' => 4
            ],
            'cover_letter' => [
                'completion_rate' => 85,
                'abandonment_rate' => 15
            ],
            'salary_expectation' => [
                'completion_rate' => 92,
                'abandonment_rate' => 8
            ]
        ];
    }

    /**
     * Generate funnel insights
     */
    private function generateFunnelInsights(array $funnelData, array $conversionRates): array
    {
        $insights = [];
        
        if ($conversionRates['screening_to_shortlist'] < 30) {
            $insights[] = 'Low screening to shortlist conversion. Consider adjusting AI criteria.';
        }
        
        if ($conversionRates['interview_to_offer'] < 50) {
            $insights[] = 'Low interview to offer conversion. Review interview process.';
        }
        
        if ($conversionRates['overall_conversion'] < 5) {
            $insights[] = 'Overall conversion rate is low. Review entire recruitment process.';
        }
        
        return $insights;
    }

    /**
     * Generate AI insights
     */
    private function generateAIInsights(array $screeningStats, array $accuracyMetrics): array
    {
        $insights = [];
        
        if ($screeningStats['accuracy_rate'] < 85) {
            $insights[] = 'AI accuracy below target. Consider retraining model.';
        }
        
        if ($screeningStats['avg_screening_time'] > 300) {
            $insights[] = 'AI screening taking too long. Optimize processing.';
        }
        
        if ($accuracyMetrics['false_positives'] > 15) {
            $insights[] = 'High false positive rate. Adjust screening criteria.';
        }
        
        return $insights;
    }

    /**
     * Generate quality insights
     */
    private function generateQualityInsights(array $qualityMetrics, array $sourceAnalytics): array
    {
        $insights = [];
        
        if ($qualityMetrics['quality_rate'] < 70) {
            $insights[] = 'Low candidate quality rate. Review sourcing strategies.';
        }
        
        $bestSource = collect($sourceAnalytics)->sortByDesc('quality_score')->first();
        if ($bestSource) {
            $insights[] = 'Best quality candidates come from ' . $bestSource['source'] . '.';
        }
        
        return $insights;
    }

    /**
     * Get source quality score (simulated)
     */
    private function getSourceQualityScore(string $source): float
    {
        $scores = [
            'website' => 8.5,
            'linkedin' => 8.2,
            'referral' => 9.1,
            'job_board' => 7.8,
            'recruitment_agency' => 8.7,
            'social_media' => 7.5,
            'other' => 7.0
        ];
        
        return $scores[$source] ?? 7.0;
    }
}
