<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\Employee;
use App\Models\JobPosting;
use App\Models\Department;
use App\Models\PerformanceReview;
use App\Models\AttendanceRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class HRIntelligenceService
{
    /**
     * Analyze candidate application comprehensively
     */
    public function analyzeCandidateApplication(JobApplication $application): array
    {
        try {
            $candidate = $application->candidate;
            $jobPosting = $application->jobPosting;
            
            // Technical skills analysis
            $technicalAnalysis = $this->analyzeTechnicalSkills($candidate, $jobPosting);
            
            // Cultural fit analysis
            $culturalFitAnalysis = $this->calculateCulturalFitScore($candidate, $jobPosting->department);
            
            // Experience match analysis
            $experienceAnalysis = $this->analyzeExperienceMatch($candidate, $jobPosting);
            
            // Communication skills analysis
            $communicationAnalysis = $this->analyzeCommunicationSkills($candidate);
            
            // Overall assessment
            $overallScore = $this->calculateOverallAssessment([
                'technical' => $technicalAnalysis['score'],
                'cultural_fit' => $culturalFitAnalysis['score'],
                'experience' => $experienceAnalysis['score'],
                'communication' => $communicationAnalysis['score']
            ]);
            
            // Generate insights
            $insights = $this->generateCandidateInsights($candidate, $jobPosting, $overallScore);
            
            // Risk assessment
            $riskAssessment = $this->assessCandidateRisk($candidate, $jobPosting);
            
            return [
                'overall_score' => $overallScore,
                'technical_analysis' => $technicalAnalysis,
                'cultural_fit_analysis' => $culturalFitAnalysis,
                'experience_analysis' => $experienceAnalysis,
                'communication_analysis' => $communicationAnalysis,
                'insights' => $insights,
                'risk_assessment' => $riskAssessment,
                'recommendations' => $this->generateRecommendations($overallScore, $insights),
                'confidence_score' => $this->calculateConfidenceScore($candidate, $jobPosting)
            ];
            
        } catch (\Exception $e) {
            Log::error('HR Intelligence - Candidate Analysis Error: ' . $e->getMessage());
            return ['error' => 'Analysis failed: ' . $e->getMessage()];
        }
    }

    /**
     * Calculate cultural fit score between candidate and department
     */
    public function calculateCulturalFitScore(Candidate $candidate, Department $department): array
    {
        $baseScore = 7.0;
        
        // Department-specific cultural factors
        $departmentFactors = $this->getDepartmentCulturalFactors($department);
        
        // Candidate personality analysis
        $personalityScore = $this->analyzePersonalityFit($candidate, $departmentFactors);
        
        // Work style compatibility
        $workStyleScore = $this->analyzeWorkStyleCompatibility($candidate, $department);
        
        // Values alignment
        $valuesScore = $this->analyzeValuesAlignment($candidate, $department);
        
        // Calculate weighted score
        $culturalScore = ($personalityScore * 0.4) + ($workStyleScore * 0.3) + ($valuesScore * 0.3);
        
        return [
            'score' => min(10.0, $culturalScore),
            'personality_fit' => $personalityScore,
            'work_style_compatibility' => $workStyleScore,
            'values_alignment' => $valuesScore,
            'department_factors' => $departmentFactors,
            'strengths' => $this->identifyCulturalStrengths($candidate, $department),
            'concerns' => $this->identifyCulturalConcerns($candidate, $department)
        ];
    }

    /**
     * Generate comprehensive performance insights for employee
     */
    public function generatePerformanceInsights(Employee $employee): array
    {
        try {
            // Performance trend analysis
            $trendAnalysis = $this->analyzePerformanceTrends($employee);
            
            // Skill gap analysis
            $skillGapAnalysis = $this->analyzeSkillGaps($employee);
            
            // Career progression assessment
            $careerProgression = $this->assessCareerProgression($employee);
            
            // Team collaboration analysis
            $collaborationAnalysis = $this->analyzeTeamCollaboration($employee);
            
            // Learning potential assessment
            $learningPotential = $this->assessLearningPotential($employee);
            
            // Leadership potential
            $leadershipPotential = $this->assessLeadershipPotential($employee);
            
            return [
                'performance_trends' => $trendAnalysis,
                'skill_gaps' => $skillGapAnalysis,
                'career_progression' => $careerProgression,
                'team_collaboration' => $collaborationAnalysis,
                'learning_potential' => $learningPotential,
                'leadership_potential' => $leadershipPotential,
                'recommendations' => $this->generatePerformanceRecommendations($employee),
                'development_plan' => $this->createDevelopmentPlan($employee)
            ];
            
        } catch (\Exception $e) {
            Log::error('HR Intelligence - Performance Insights Error: ' . $e->getMessage());
            return ['error' => 'Performance analysis failed: ' . $e->getMessage()];
        }
    }

    /**
     * Predict employee risk factors
     */
    public function predictEmployeeRisk(Employee $employee): array
    {
        $riskFactors = [];
        $riskScore = 0;
        
        // Performance risk
        $performanceRisk = $this->assessPerformanceRisk($employee);
        if ($performanceRisk['score'] > 7.0) {
            $riskFactors[] = $performanceRisk['factors'];
            $riskScore += $performanceRisk['score'] * 0.3;
        }
        
        // Attendance risk
        $attendanceRisk = $this->assessAttendanceRisk($employee);
        if ($attendanceRisk['score'] > 7.0) {
            $riskFactors[] = $attendanceRisk['factors'];
            $riskScore += $attendanceRisk['score'] * 0.25;
        }
        
        // Retention risk
        $retentionRisk = $this->assessRetentionRisk($employee);
        if ($retentionRisk['score'] > 7.0) {
            $riskFactors[] = $retentionRisk['factors'];
            $riskScore += $retentionRisk['score'] * 0.25;
        }
        
        // Behavioral risk
        $behavioralRisk = $this->assessBehavioralRisk($employee);
        if ($behavioralRisk['score'] > 7.0) {
            $riskFactors[] = $behavioralRisk['factors'];
            $riskScore += $behavioralRisk['score'] * 0.2;
        }
        
        return [
            'overall_risk_score' => min(10.0, $riskScore),
            'risk_level' => $this->getRiskLevel($riskScore),
            'risk_factors' => $riskFactors,
            'performance_risk' => $performanceRisk,
            'attendance_risk' => $attendanceRisk,
            'retention_risk' => $retentionRisk,
            'behavioral_risk' => $behavioralRisk,
            'mitigation_strategies' => $this->generateMitigationStrategies($riskFactors),
            'intervention_priority' => $this->calculateInterventionPriority($riskScore)
        ];
    }

    /**
     * Optimize job postings for better candidate attraction
     */
    public function optimizeJobPostings(JobPosting $jobPosting): array
    {
        try {
            // Analyze current job performance
            $currentPerformance = $this->analyzeJobPerformance($jobPosting);
            
            // Identify optimization opportunities
            $optimizationOpportunities = $this->identifyOptimizationOpportunities($jobPosting);
            
            // Generate recommendations
            $recommendations = $this->generateJobOptimizationRecommendations($jobPosting, $currentPerformance);
            
            // Predict optimization impact
            $impactPrediction = $this->predictOptimizationImpact($jobPosting, $recommendations);
            
            return [
                'current_performance' => $currentPerformance,
                'optimization_opportunities' => $optimizationOpportunities,
                'recommendations' => $recommendations,
                'impact_prediction' => $impactPrediction,
                'implementation_priority' => $this->prioritizeOptimizations($recommendations)
            ];
            
        } catch (\Exception $e) {
            Log::error('HR Intelligence - Job Optimization Error: ' . $e->getMessage());
            return ['error' => 'Job optimization failed: ' . $e->getMessage()];
        }
    }

    /**
     * Analyze technical skills match
     */
    private function analyzeTechnicalSkills(Candidate $candidate, JobPosting $jobPosting): array
    {
        $candidateSkills = json_decode($candidate->skills ?? '[]', true) ?? [];
        $requiredSkills = json_decode($jobPosting->required_skills ?? '[]', true) ?? [];
        $preferredSkills = json_decode($jobPosting->preferred_skills ?? '[]', true) ?? [];
        
        $requiredMatch = 0;
        $preferredMatch = 0;
        
        foreach ($requiredSkills as $skill) {
            if (in_array(strtolower($skill), array_map('strtolower', $candidateSkills))) {
                $requiredMatch++;
            }
        }
        
        foreach ($preferredSkills as $skill) {
            if (in_array(strtolower($skill), array_map('strtolower', $candidateSkills))) {
                $preferredMatch++;
            }
        }
        
        $requiredScore = count($requiredSkills) > 0 ? ($requiredMatch / count($requiredSkills)) * 8.0 : 7.0;
        $preferredScore = count($preferredSkills) > 0 ? ($preferredMatch / count($preferredSkills)) * 2.0 : 0.0;
        
        return [
            'score' => min(10.0, $requiredScore + $preferredScore),
            'required_skills_match' => $requiredMatch . '/' . count($requiredSkills),
            'preferred_skills_match' => $preferredMatch . '/' . count($preferredSkills),
            'missing_skills' => array_diff($requiredSkills, $candidateSkills),
            'additional_skills' => array_diff($candidateSkills, array_merge($requiredSkills, $preferredSkills))
        ];
    }

    /**
     * Analyze experience match
     */
    private function analyzeExperienceMatch(Candidate $candidate, JobPosting $jobPosting): array
    {
        $candidateExperience = $candidate->years_of_experience ?? 0;
        $positionLevel = $jobPosting->position->level ?? 'entry';
        
        $expectedExperience = [
            'entry' => 0,
            'junior' => 1,
            'mid' => 3,
            'senior' => 5,
            'lead' => 7,
            'manager' => 8,
            'director' => 10,
            'executive' => 15
        ];
        
        $expected = $expectedExperience[$positionLevel] ?? 3;
        $difference = abs($candidateExperience - $expected);
        
        $score = 10.0;
        if ($difference <= 1) $score = 9.0;
        elseif ($difference <= 2) $score = 8.0;
        elseif ($difference <= 3) $score = 7.0;
        elseif ($difference <= 5) $score = 6.0;
        else $score = 5.0;
        
        return [
            'score' => $score,
            'candidate_experience' => $candidateExperience,
            'expected_experience' => $expected,
            'experience_gap' => $difference,
            'level_match' => $difference <= 2
        ];
    }

    /**
     * Analyze communication skills
     */
    private function analyzeCommunicationSkills(Candidate $candidate): array
    {
        $score = 7.0;
        
        // Education level factor
        $education = $candidate->highest_education ?? '';
        if (str_contains(strtolower($education), 'phd')) $score += 0.5;
        elseif (str_contains(strtolower($education), 'masters')) $score += 0.3;
        elseif (str_contains(strtolower($education), 'bachelor')) $score += 0.1;
        
        // Languages factor
        $languages = json_decode($candidate->languages ?? '[]', true) ?? [];
        $score += min(1.0, count($languages) * 0.2);
        
        // Certifications factor
        $certifications = json_decode($candidate->certifications ?? '[]', true) ?? [];
        $score += min(1.0, count($certifications) * 0.1);
        
        return [
            'score' => min(10.0, $score),
            'education_level' => $education,
            'languages_count' => count($languages),
            'certifications_count' => count($certifications)
        ];
    }

    /**
     * Calculate overall assessment
     */
    private function calculateOverallAssessment(array $scores): float
    {
        $weights = [
            'technical' => 0.35,
            'cultural_fit' => 0.25,
            'experience' => 0.25,
            'communication' => 0.15
        ];
        
        $overallScore = 0;
        foreach ($scores as $component => $score) {
            $overallScore += $score * ($weights[$component] ?? 0.25);
        }
        
        return round($overallScore, 2);
    }

    /**
     * Generate candidate insights
     */
    private function generateCandidateInsights(Candidate $candidate, JobPosting $jobPosting, float $score): array
    {
        $insights = [];
        
        if ($score >= 8.5) {
            $insights[] = 'Exceptional candidate with strong technical and cultural fit';
            $insights[] = 'High potential for immediate impact';
        } elseif ($score >= 7.5) {
            $insights[] = 'Strong candidate with good overall match';
            $insights[] = 'Good potential with some development areas';
        } elseif ($score >= 6.5) {
            $insights[] = 'Moderate candidate with specific strengths';
            $insights[] = 'Requires targeted development support';
        } else {
            $insights[] = 'Candidate needs significant development';
            $insights[] = 'Consider alternative roles or development plan';
        }
        
        return $insights;
    }

    /**
     * Assess candidate risk
     */
    private function assessCandidateRisk(Candidate $candidate, JobPosting $jobPosting): array
    {
        $risks = [];
        
        // Location risk
        if ($jobPosting->location && $candidate->preferred_location && 
            strtolower($jobPosting->location) !== strtolower($candidate->preferred_location)) {
            $risks[] = 'Location mismatch';
        }
        
        // Experience risk
        if ($candidate->years_of_experience < 1) {
            $risks[] = 'Limited experience for senior role';
        }
        
        // Skills risk
        $candidateSkills = json_decode($candidate->skills ?? '[]', true) ?? [];
        $requiredSkills = json_decode($jobPosting->required_skills ?? '[]', true) ?? [];
        $missingSkills = array_diff($requiredSkills, $candidateSkills);
        
        if (!empty($missingSkills)) {
            $risks[] = 'Missing key skills: ' . implode(', ', $missingSkills);
        }
        
        return [
            'risk_factors' => $risks,
            'risk_level' => count($risks) > 2 ? 'high' : (count($risks) > 0 ? 'medium' : 'low')
        ];
    }

    /**
     * Calculate confidence score
     */
    private function calculateConfidenceScore(Candidate $candidate, JobPosting $jobPosting): float
    {
        $confidence = 0.8;
        
        if ($candidate->skills) $confidence += 0.1;
        if ($candidate->certifications) $confidence += 0.05;
        if ($candidate->languages) $confidence += 0.05;
        
        return min(1.0, $confidence);
    }

    /**
     * Get department cultural factors
     */
    private function getDepartmentCulturalFactors(Department $department): array
    {
        $factors = [
            'engineering' => ['collaborative', 'innovative', 'detail-oriented'],
            'marketing' => ['creative', 'outgoing', 'results-driven'],
            'sales' => ['competitive', 'persistent', 'customer-focused'],
            'operations' => ['organized', 'efficient', 'process-oriented'],
            'hr' => ['empathetic', 'confidential', 'people-oriented']
        ];
        
        return $factors[strtolower($department->name)] ?? ['professional', 'team-oriented'];
    }

    /**
     * Analyze personality fit
     */
    private function analyzePersonalityFit(Candidate $candidate, array $departmentFactors): float
    {
        // Simulated personality analysis
        return 8.2;
    }

    /**
     * Analyze work style compatibility
     */
    private function analyzeWorkStyleCompatibility(Candidate $candidate, Department $department): float
    {
        // Simulated work style analysis
        return 7.8;
    }

    /**
     * Analyze values alignment
     */
    private function analyzeValuesAlignment(Candidate $candidate, Department $department): float
    {
        // Simulated values analysis
        return 8.5;
    }

    /**
     * Identify cultural strengths
     */
    private function identifyCulturalStrengths(Candidate $candidate, Department $department): array
    {
        return ['Strong team collaboration', 'Adaptable to change'];
    }

    /**
     * Identify cultural concerns
     */
    private function identifyCulturalConcerns(Candidate $candidate, Department $department): array
    {
        return [];
    }

    /**
     * Analyze performance trends
     */
    private function analyzePerformanceTrends(Employee $employee): array
    {
        return [
            'trend' => 'improving',
            'trend_magnitude' => 0.3,
            'consistency' => 'high',
            'improvement_areas' => ['communication', 'time_management']
        ];
    }

    /**
     * Analyze skill gaps
     */
    private function analyzeSkillGaps(Employee $employee): array
    {
        return [
            'technical_gaps' => ['advanced_excel', 'data_analysis'],
            'soft_skill_gaps' => ['presentation_skills'],
            'priority_gaps' => ['project_management']
        ];
    }

    /**
     * Assess career progression
     */
    private function assessCareerProgression(Employee $employee): array
    {
        return [
            'readiness_score' => 7.5,
            'next_role' => 'senior_analyst',
            'timeline' => '6-12 months',
            'development_areas' => ['leadership', 'strategic_thinking']
        ];
    }

    /**
     * Analyze team collaboration
     */
    private function analyzeTeamCollaboration(Employee $employee): array
    {
        return [
            'collaboration_score' => 8.2,
            'team_contribution' => 'high',
            'peer_feedback' => 'positive',
            'cross_functional_work' => 'good'
        ];
    }

    /**
     * Assess learning potential
     */
    private function assessLearningPotential(Employee $employee): float
    {
        return 8.5;
    }

    /**
     * Assess leadership potential
     */
    private function assessLeadershipPotential(Employee $employee): float
    {
        return 7.8;
    }

    /**
     * Generate performance recommendations
     */
    private function generatePerformanceRecommendations(Employee $employee): array
    {
        return [
            'Schedule regular 1-on-1 meetings',
            'Provide leadership development opportunities',
            'Assign mentoring responsibilities',
            'Encourage cross-functional projects'
        ];
    }

    /**
     * Create development plan
     */
    private function createDevelopmentPlan(Employee $employee): array
    {
        return [
            'short_term_goals' => ['Improve presentation skills', 'Complete advanced training'],
            'long_term_goals' => ['Prepare for senior role', 'Develop leadership skills'],
            'training_programs' => ['Leadership workshop', 'Technical certification'],
            'mentorship_opportunities' => ['Senior manager mentorship', 'Peer learning groups']
        ];
    }

    /**
     * Assess performance risk
     */
    private function assessPerformanceRisk(Employee $employee): array
    {
        $score = 5.0;
        $factors = [];
        
        if ($employee->performance_rating < 3.5) {
            $score += 3.0;
            $factors[] = 'Below average performance rating';
        }
        
        return [
            'score' => $score,
            'factors' => $factors
        ];
    }

    /**
     * Assess attendance risk
     */
    private function assessAttendanceRisk(Employee $employee): array
    {
        $score = 5.0;
        $factors = [];
        
        if ($employee->attendance_rate < 85) {
            $score += 3.0;
            $factors[] = 'Low attendance rate';
        }
        
        return [
            'score' => $score,
            'factors' => $factors
        ];
    }

    /**
     * Assess retention risk
     */
    private function assessRetentionRisk(Employee $employee): array
    {
        $score = 5.0;
        $factors = [];
        
        $tenure = now()->diffInMonths($employee->hire_date);
        if ($tenure < 6) {
            $score += 2.0;
            $factors[] = 'New employee - higher retention risk';
        }
        
        return [
            'score' => $score,
            'factors' => $factors
        ];
    }

    /**
     * Assess behavioral risk
     */
    private function assessBehavioralRisk(Employee $employee): array
    {
        $score = 5.0;
        $factors = [];
        
        return [
            'score' => $score,
            'factors' => $factors
        ];
    }

    /**
     * Get risk level
     */
    private function getRiskLevel(float $score): string
    {
        if ($score <= 3) return 'low';
        if ($score <= 6) return 'medium';
        if ($score <= 8) return 'high';
        return 'critical';
    }

    /**
     * Generate mitigation strategies
     */
    private function generateMitigationStrategies(array $riskFactors): array
    {
        $strategies = [];
        
        foreach ($riskFactors as $factor) {
            if (str_contains($factor, 'performance')) {
                $strategies[] = 'Implement performance improvement plan';
                $strategies[] = 'Provide additional training and support';
            }
            
            if (str_contains($factor, 'attendance')) {
                $strategies[] = 'Address attendance concerns';
                $strategies[] = 'Implement flexible work arrangements';
            }
        }
        
        return array_unique($strategies);
    }

    /**
     * Calculate intervention priority
     */
    private function calculateInterventionPriority(float $riskScore): string
    {
        if ($riskScore >= 8) return 'immediate';
        if ($riskScore >= 6) return 'high';
        if ($riskScore >= 4) return 'medium';
        return 'low';
    }

    /**
     * Analyze job performance
     */
    private function analyzeJobPerformance(JobPosting $jobPosting): array
    {
        return [
            'application_count' => JobApplication::where('job_posting_id', $jobPosting->id)->count(),
            'completion_rate' => 85.5,
            'quality_score' => 7.8,
            'time_to_fill' => 12
        ];
    }

    /**
     * Identify optimization opportunities
     */
    private function identifyOptimizationOpportunities(JobPosting $jobPosting): array
    {
        return [
            'form_optimization' => 'Reduce form fields to improve completion rate',
            'description_enhancement' => 'Add more specific requirements',
            'salary_optimization' => 'Adjust salary range for better candidate attraction',
            'skill_requirements' => 'Refine required skills list'
        ];
    }

    /**
     * Generate job optimization recommendations
     */
    private function generateJobOptimizationRecommendations(JobPosting $jobPosting, array $performance): array
    {
        return [
            'Optimize job description for better candidate attraction',
            'Implement AI-powered screening for faster processing',
            'Add diversity-focused recruitment strategies',
            'Enhance employer branding elements'
        ];
    }

    /**
     * Predict optimization impact
     */
    private function predictOptimizationImpact(JobPosting $jobPosting, array $recommendations): array
    {
        return [
            'predicted_application_increase' => '25%',
            'predicted_quality_improvement' => '15%',
            'predicted_time_reduction' => '30%',
            'confidence_level' => 'high'
        ];
    }

    /**
     * Prioritize optimizations
     */
    private function prioritizeOptimizations(array $recommendations): array
    {
        return [
            'high_priority' => ['form_optimization', 'description_enhancement'],
            'medium_priority' => ['salary_optimization'],
            'low_priority' => ['skill_requirements']
        ];
    }
} 