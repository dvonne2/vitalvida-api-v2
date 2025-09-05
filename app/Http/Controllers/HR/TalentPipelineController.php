<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\AIAssessment;
use App\Models\TeamMatch;
use App\Services\AIScreeningService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TalentPipelineController extends Controller
{
    protected $aiScreeningService;

    public function __construct(AIScreeningService $aiScreeningService)
    {
        $this->aiScreeningService = $aiScreeningService;
    }

    /**
     * Get talent pipeline with candidates and AI insights
     */
    public function getPipeline(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->get('status', 'all'),
                'search' => $request->get('search', ''),
                'job_id' => $request->get('job_id'),
                'ai_score_min' => $request->get('ai_score_min'),
                'ai_score_max' => $request->get('ai_score_max')
            ];

            $query = JobApplication::with(['candidate', 'jobPosting.department', 'jobPosting.position']);

            // Apply filters
            if ($filters['status'] !== 'all') {
                $query->where('status', $filters['status']);
            }

            if ($filters['job_id']) {
                $query->where('job_posting_id', $filters['job_id']);
            }

            if ($filters['search']) {
                $query->whereHas('candidate', function($q) use ($filters) {
                    $q->where('first_name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                });
            }

            if ($filters['ai_score_min']) {
                $query->where('ai_score', '>=', $filters['ai_score_min']);
            }

            if ($filters['ai_score_max']) {
                $query->where('ai_score', '<=', $filters['ai_score_max']);
            }

            $applications = $query->orderBy('applied_at', 'desc')->paginate(20);

            $candidates = $applications->getCollection()->map(function ($application) {
                $candidate = $application->candidate;
                $jobPosting = $application->jobPosting;
                
                // Get AI insights
                $aiInsights = $this->getAIInsights($candidate, $jobPosting);
                
                return [
                    'id' => $application->id,
                    'candidate_id' => $candidate->id,
                    'name' => $candidate->first_name . ' ' . $candidate->last_name,
                    'email' => $candidate->email,
                    'position' => $jobPosting->position->title ?? 'Unknown',
                    'department' => $jobPosting->department->name ?? 'Unknown',
                    'mbti_type' => $this->generateMBTI($candidate),
                    'experience' => $this->formatExperience($candidate->years_of_experience),
                    'emotional_intelligence' => $this->calculateEmotionalIntelligence($candidate),
                    'cultural_fit' => $this->formatCulturalFit($application->cultural_fit_score),
                    'status' => $application->status,
                    'ai_score' => $application->ai_score,
                    'applied_at' => $application->applied_at->format('M j, Y'),
                    'actions' => $this->getCandidateActions($application),
                    'ai_insights' => $aiInsights
                ];
            });

            // Get sidebar data for first candidate (if any)
            $sidebarData = null;
            if ($candidates->isNotEmpty()) {
                $firstApplication = $applications->first();
                $sidebarData = $this->getSidebarData($firstApplication);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'filters' => $filters,
                    'candidates' => $candidates,
                    'sidebar' => $sidebarData,
                    'pagination' => [
                        'current_page' => $applications->currentPage(),
                        'last_page' => $applications->lastPage(),
                        'per_page' => $applications->perPage(),
                        'total' => $applications->total()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Talent Pipeline Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load talent pipeline',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get candidate details with AI analysis
     */
    public function getCandidateDetails(int $candidateId, int $applicationId): JsonResponse
    {
        try {
            $application = JobApplication::with(['candidate', 'jobPosting.department', 'jobPosting.position'])
                ->where('id', $applicationId)
                ->where('candidate_id', $candidateId)
                ->firstOrFail();

            $candidate = $application->candidate;
            $jobPosting = $application->jobPosting;

            // Get comprehensive AI insights
            $aiInsights = $this->aiScreeningService->generateCandidateInsights($candidate);
            
            // Get team match analysis
            $teamMatch = TeamMatch::where('candidate_id', $candidateId)
                ->where('job_posting_id', $jobPosting->id)
                ->first();

            // Get AI assessment history
            $aiAssessments = AIAssessment::where('candidate_id', $candidateId)
                ->where('job_posting_id', $jobPosting->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'candidate' => [
                        'id' => $candidate->id,
                        'name' => $candidate->first_name . ' ' . $candidate->last_name,
                        'email' => $candidate->email,
                        'phone' => $candidate->phone,
                        'current_position' => $candidate->current_position,
                        'current_company' => $candidate->current_company,
                        'years_of_experience' => $candidate->years_of_experience,
                        'highest_education' => $candidate->highest_education,
                        'institution' => $candidate->institution,
                        'field_of_study' => $candidate->field_of_study,
                        'graduation_year' => $candidate->graduation_year,
                        'location' => $candidate->city . ', ' . $candidate->state,
                        'skills' => json_decode($candidate->skills ?? '[]', true) ?? [],
                        'certifications' => json_decode($candidate->certifications ?? '[]', true) ?? [],
                        'languages' => json_decode($candidate->languages ?? '[]', true) ?? [],
                        'resume_path' => $candidate->resume_path,
                        'portfolio_url' => $candidate->portfolio_url,
                        'linkedin_url' => $candidate->linkedin_url,
                        'github_url' => $candidate->github_url
                    ],
                    'application' => [
                        'id' => $application->id,
                        'status' => $application->status,
                        'ai_score' => $application->ai_score,
                        'technical_score' => $application->technical_score,
                        'cultural_fit_score' => $application->cultural_fit_score,
                        'applied_at' => $application->applied_at->format('M j, Y'),
                        'expected_salary' => $application->expected_salary,
                        'earliest_start_date' => $application->earliest_start_date?->format('M j, Y'),
                        'cover_letter' => $application->cover_letter,
                        'ai_assessment' => $application->ai_assessment
                    ],
                    'job_posting' => [
                        'id' => $jobPosting->id,
                        'title' => $jobPosting->title,
                        'department' => $jobPosting->department->name,
                        'position' => $jobPosting->position->title,
                        'location' => $jobPosting->location,
                        'type' => ucfirst(str_replace('_', ' ', $jobPosting->type)),
                        'required_skills' => json_decode($jobPosting->required_skills ?? '[]', true) ?? [],
                        'preferred_skills' => json_decode($jobPosting->preferred_skills ?? '[]', true) ?? []
                    ],
                    'ai_insights' => $aiInsights,
                    'team_match' => $teamMatch ? [
                        'overall_match_score' => $teamMatch->overall_match_score,
                        'skill_match_score' => $teamMatch->skill_match_score,
                        'personality_match_score' => $teamMatch->personality_match_score,
                        'cultural_fit_score' => $teamMatch->cultural_fit_score,
                        'team_impact_prediction' => $teamMatch->team_impact_prediction,
                        'recommendations' => $teamMatch->recommendations
                    ] : null,
                    'ai_assessments' => $aiAssessments->map(function ($assessment) {
                        return [
                            'id' => $assessment->id,
                            'assessment_type' => $assessment->assessment_type,
                            'overall_score' => $assessment->overall_score,
                            'technical_score' => $assessment->technical_score,
                            'cultural_fit_score' => $assessment->cultural_fit_score,
                            'confidence_score' => $assessment->confidence_score,
                            'created_at' => $assessment->created_at->format('M j, Y'),
                            'recommendations' => $assessment->recommendations
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Candidate Details Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load candidate details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update candidate status
     */
    public function updateCandidateStatus(Request $request, int $applicationId): JsonResponse
    {
        try {
            $application = JobApplication::findOrFail($applicationId);
            
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'status' => 'required|in:screening,shortlisted,interview_scheduled,interviewed,reference_check,background_check,offer_sent,offer_accepted,offer_declined,hired,rejected,withdrawn',
                'notes' => 'nullable|string',
                'next_action' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $application->update([
                'status' => $request->status,
                'additional_notes' => $request->notes
            ]);

            // Update timestamp based on status
            $timestampField = $request->status . '_at';
            if (in_array($timestampField, $application->getFillable())) {
                $application->update([$timestampField => now()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Candidate status updated successfully',
                'data' => [
                    'application_id' => $application->id,
                    'new_status' => $application->status,
                    'updated_at' => $application->updated_at->format('M j, Y H:i')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Update Candidate Status Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update candidate status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run AI screening on candidate
     */
    public function runAIScreening(int $applicationId): JsonResponse
    {
        try {
            $application = JobApplication::with(['candidate', 'jobPosting'])->findOrFail($applicationId);
            
            // Run AI screening
            $assessment = $this->aiScreeningService->scoreCandidateApplication($application);
            
            return response()->json([
                'success' => true,
                'message' => 'AI screening completed successfully',
                'data' => [
                    'application_id' => $application->id,
                    'ai_score' => $assessment['overall_score'],
                    'technical_score' => $assessment['technical_score'],
                    'cultural_fit_score' => $assessment['cultural_fit_score'],
                    'skill_matches' => $assessment['skill_matches'],
                    'strengths' => $assessment['strengths'],
                    'weaknesses' => $assessment['weaknesses'],
                    'recommendations' => $assessment['recommendations'],
                    'ai_recommended' => $application->ai_recommended
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AI Screening Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to run AI screening',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AI insights for candidate
     */
    private function getAIInsights(Candidate $candidate, JobPosting $jobPosting): array
    {
        return [
            'technical_profile' => $this->analyzeTechnicalProfile($candidate),
            'personality_insights' => $this->analyzePersonality($candidate),
            'career_progression' => $this->analyzeCareerProgression($candidate),
            'learning_potential' => $this->assessLearningPotential($candidate),
            'leadership_potential' => $this->assessLeadershipPotential($candidate),
            'team_collaboration' => $this->assessTeamCollaboration($candidate),
            'cultural_alignment' => $this->assessCulturalAlignment($candidate)
        ];
    }

    /**
     * Get sidebar data for candidate
     */
    private function getSidebarData(JobApplication $application): array
    {
        $candidate = $application->candidate;
        
        return [
            'cv_highlights' => [
                'education' => $candidate->highest_education . ', ' . $candidate->institution,
                'previous_role' => $candidate->current_position . ' at ' . $candidate->current_company,
                'key_skills' => json_decode($candidate->skills ?? '[]', true) ?? [],
                'location' => $candidate->city . ', ' . $candidate->state
            ],
            'ai_performance_score' => [
                'overall' => $application->ai_score ? $application->ai_score . '/100' : 'Not assessed',
                'breakdown' => 'Overall candidate rating based on role requirements'
            ],
            'team_match_analysis' => [
                'e_commerce_frontend' => 'Good fit - Strong React skills and e-commerce experience',
                'mobile_development' => 'Good fit - React Native potential, needs training'
            ],
            'key_strengths' => 'Exceptional technical skills and strong cultural fit'
        ];
    }

    /**
     * Generate MBTI type (simulated)
     */
    private function generateMBTI(Candidate $candidate): string
    {
        $types = ['ENFP', 'ENTJ', 'INTJ', 'INFJ', 'ENFJ', 'ENTP', 'INTP', 'INFP'];
        return $types[array_rand($types)];
    }

    /**
     * Format experience years
     */
    private function formatExperience($years): string
    {
        if (!$years) return 'Not specified';
        return $years . ' year' . ($years > 1 ? 's' : '');
    }

    /**
     * Calculate emotional intelligence (simulated)
     */
    private function calculateEmotionalIntelligence(Candidate $candidate): int
    {
        return rand(70, 95);
    }

    /**
     * Format cultural fit score
     */
    private function formatCulturalFit($score): string
    {
        if (!$score) return 'Not assessed';
        
        if ($score >= 9.0) return 'Excellent (' . round($score * 10) . '%)';
        if ($score >= 8.0) return 'Very Good (' . round($score * 10) . '%)';
        if ($score >= 7.0) return 'Good (' . round($score * 10) . '%)';
        if ($score >= 6.0) return 'Fair (' . round($score * 10) . '%)';
        return 'Poor (' . round($score * 10) . '%)';
    }

    /**
     * Get available actions for candidate
     */
    private function getCandidateActions(JobApplication $application): array
    {
        $actions = ['view_details', 'run_ai_screening'];
        
        switch ($application->status) {
            case 'applied':
                $actions[] = 'shortlist';
                $actions[] = 'reject';
                break;
            case 'shortlisted':
                $actions[] = 'schedule_interview';
                $actions[] = 'send_to_team_lead';
                break;
            case 'interviewed':
                $actions[] = 'send_offer';
                $actions[] = 'reject';
                break;
            case 'offer_sent':
                $actions[] = 'mark_accepted';
                $actions[] = 'mark_declined';
                break;
        }
        
        return $actions;
    }

    // Helper methods for AI insights (simulated)
    private function analyzeTechnicalProfile(Candidate $candidate): array
    {
        return [
            'skill_diversity' => count(json_decode($candidate->skills ?? '[]', true) ?? []),
            'experience_level' => $candidate->years_of_experience ?? 0,
            'education_relevance' => 8.0,
            'certification_count' => count(json_decode($candidate->certifications ?? '[]', true) ?? [])
        ];
    }

    private function analyzePersonality(Candidate $candidate): array
    {
        return [
            'communication_style' => 'assertive',
            'work_preference' => 'collaborative',
            'stress_management' => 'good',
            'adaptability' => 'high'
        ];
    }

    private function analyzeCareerProgression(Candidate $candidate): array
    {
        return [
            'growth_trajectory' => 'positive',
            'role_advancement' => 'steady',
            'skill_development' => 'continuous'
        ];
    }

    private function assessLearningPotential(Candidate $candidate): float
    {
        return 8.5;
    }

    private function assessLeadershipPotential(Candidate $candidate): float
    {
        return 7.8;
    }

    private function assessTeamCollaboration(Candidate $candidate): float
    {
        return 8.2;
    }

    private function assessCulturalAlignment(Candidate $candidate): float
    {
        return 8.7;
    }
}
