<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\JobPosting;
use App\Models\JobApplication;
use App\Models\AIAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AIScreeningService
{
    /**
     * Score candidate application based on job requirements
     */
    public function scoreCandidateApplication(JobApplication $application): array
    {
        try {
            $candidate = $application->candidate;
            $jobPosting = $application->jobPosting;
            
            // Get AI criteria from job posting
            $aiCriteria = $jobPosting->ai_criteria ?? [];
            $requiredSkills = $jobPosting->required_skills ?? [];
            $preferredSkills = $jobPosting->preferred_skills ?? [];
            
            // Calculate technical score
            $technicalScore = $this->calculateTechnicalScore($candidate, $requiredSkills, $preferredSkills);
            
            // Calculate cultural fit score
            $culturalFitScore = $this->calculateCulturalFit($candidate, $jobPosting);
            
            // Calculate experience match score
            $experienceScore = $this->calculateExperienceMatch($candidate, $jobPosting);
            
            // Calculate communication score
            $communicationScore = $this->calculateCommunicationScore($candidate);
            
            // Calculate overall score
            $overallScore = $this->calculateOverallScore([
                'technical' => $technicalScore,
                'cultural_fit' => $culturalFitScore,
                'experience' => $experienceScore,
                'communication' => $communicationScore
            ]);
            
            // Generate detailed assessment
            $assessment = [
                'overall_score' => $overallScore,
                'technical_score' => $technicalScore,
                'cultural_fit_score' => $culturalFitScore,
                'experience_score' => $experienceScore,
                'communication_score' => $communicationScore,
                'skill_matches' => $this->getSkillMatches($candidate, $requiredSkills),
                'strengths' => $this->identifyStrengths($candidate, $jobPosting),
                'weaknesses' => $this->identifyWeaknesses($candidate, $jobPosting),
                'recommendations' => $this->generateRecommendations($candidate, $jobPosting, $overallScore),
                'risk_factors' => $this->identifyRiskFactors($candidate, $jobPosting),
                'ai_confidence' => $this->calculateConfidence($candidate, $jobPosting)
            ];
            
            // Update application with AI scores
            $application->update([
                'ai_score' => $overallScore,
                'ai_assessment' => $assessment,
                'skill_matches' => $this->getSkillMatches($candidate, $requiredSkills),
                'cultural_fit_score' => $culturalFitScore,
                'technical_score' => $technicalScore,
                'ai_recommended' => $overallScore >= ($jobPosting->minimum_ai_score ?? 7.0)
            ]);
            
            // Create AI assessment record
            AIAssessment::create([
                'assessment_id' => 'AIA' . str_pad(AIAssessment::count() + 1, 6, '0', STR_PAD_LEFT),
                'assessment_type' => 'candidate_screening',
                'status' => 'completed',
                'candidate_id' => $candidate->id,
                'job_posting_id' => $jobPosting->id,
                'job_application_id' => $application->id,
                'input_data' => [
                    'candidate_skills' => $candidate->skills,
                    'job_requirements' => $requiredSkills,
                    'job_preferences' => $preferredSkills
                ],
                'assessment_criteria' => $aiCriteria,
                'overall_score' => $overallScore,
                'technical_score' => $technicalScore,
                'cultural_fit_score' => $culturalFitScore,
                'experience_score' => $experienceScore,
                'communication_score' => $communicationScore,
                'skill_analysis' => $this->getSkillMatches($candidate, $requiredSkills),
                'personality_analysis' => $this->analyzePersonality($candidate),
                'behavioral_analysis' => $this->analyzeBehavior($candidate),
                'risk_assessment' => $this->identifyRiskFactors($candidate, $jobPosting),
                'recommendations' => $this->generateRecommendations($candidate, $jobPosting, $overallScore),
                'confidence_score' => $this->calculateConfidence($candidate, $jobPosting),
                'completed_at' => now()
            ]);
            
            return $assessment;
            
        } catch (\Exception $e) {
            Log::error('AI Screening failed: ' . $e->getMessage());
            return [
                'overall_score' => 0,
                'error' => 'AI screening failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate candidate insights for detailed analysis
     */
    public function generateCandidateInsights(Candidate $candidate): array
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
     * Calculate cultural fit between candidate and department
     */
    public function calculateCulturalFit(Candidate $candidate, JobPosting $jobPosting): float
    {
        // Simulate cultural fit calculation based on various factors
        $baseScore = 7.0;
        
        // Factor in experience level match
        $experienceMatch = $this->calculateExperienceMatch($candidate, $jobPosting);
        $baseScore += ($experienceMatch - 7.0) * 0.2;
        
        // Factor in location preference
        $locationMatch = $this->assessLocationMatch($candidate, $jobPosting);
        $baseScore += $locationMatch * 0.3;
        
        // Factor in work style preferences
        $workStyleMatch = $this->assessWorkStyleMatch($candidate, $jobPosting);
        $baseScore += $workStyleMatch * 0.2;
        
        // Factor in company values alignment (simulated)
        $valuesAlignment = $this->assessValuesAlignment($candidate);
        $baseScore += $valuesAlignment * 0.3;
        
        return min(10.0, max(0.0, $baseScore));
    }
    
    /**
     * Calculate technical score based on skills match
     */
    private function calculateTechnicalScore(Candidate $candidate, array $requiredSkills, array $preferredSkills): float
    {
        $candidateSkills = json_decode($candidate->skills ?? '[]', true) ?? [];
        
        $requiredMatch = 0;
        $preferredMatch = 0;
        
        // Calculate required skills match
        foreach ($requiredSkills as $skill) {
            if (in_array(strtolower($skill), array_map('strtolower', $candidateSkills))) {
                $requiredMatch++;
            }
        }
        
        // Calculate preferred skills match
        foreach ($preferredSkills as $skill) {
            if (in_array(strtolower($skill), array_map('strtolower', $candidateSkills))) {
                $preferredMatch++;
            }
        }
        
        $requiredScore = count($requiredSkills) > 0 ? ($requiredMatch / count($requiredSkills)) * 8.0 : 7.0;
        $preferredScore = count($preferredSkills) > 0 ? ($preferredMatch / count($preferredSkills)) * 2.0 : 0.0;
        
        return min(10.0, $requiredScore + $preferredScore);
    }
    
    /**
     * Calculate experience match score
     */
    private function calculateExperienceMatch(Candidate $candidate, JobPosting $jobPosting): float
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
        
        if ($difference <= 1) return 9.0;
        if ($difference <= 2) return 8.0;
        if ($difference <= 3) return 7.0;
        if ($difference <= 5) return 6.0;
        
        return 5.0;
    }
    
    /**
     * Calculate communication score
     */
    private function calculateCommunicationScore(Candidate $candidate): float
    {
        // Simulate communication score based on various factors
        $baseScore = 7.0;
        
        // Factor in education level
        $education = $candidate->highest_education ?? '';
        if (str_contains(strtolower($education), 'phd')) $baseScore += 0.5;
        elseif (str_contains(strtolower($education), 'masters')) $baseScore += 0.3;
        elseif (str_contains(strtolower($education), 'bachelor')) $baseScore += 0.1;
        
        // Factor in languages
        $languages = json_decode($candidate->languages ?? '[]', true) ?? [];
        $baseScore += min(1.0, count($languages) * 0.2);
        
        // Factor in certifications
        $certifications = json_decode($candidate->certifications ?? '[]', true) ?? [];
        $baseScore += min(1.0, count($certifications) * 0.1);
        
        return min(10.0, $baseScore);
    }
    
    /**
     * Calculate overall score with weighted components
     */
    private function calculateOverallScore(array $scores): float
    {
        $weights = [
            'technical' => 0.35,
            'experience' => 0.25,
            'cultural_fit' => 0.25,
            'communication' => 0.15
        ];
        
        $overallScore = 0;
        foreach ($scores as $component => $score) {
            $overallScore += $score * ($weights[$component] ?? 0.25);
        }
        
        return round($overallScore, 2);
    }
    
    /**
     * Get skill matches between candidate and job requirements
     */
    private function getSkillMatches(Candidate $candidate, array $requiredSkills): array
    {
        $candidateSkills = json_decode($candidate->skills ?? '[]', true) ?? [];
        $matches = [];
        
        foreach ($requiredSkills as $skill) {
            $match = false;
            foreach ($candidateSkills as $candidateSkill) {
                if (stripos($candidateSkill, $skill) !== false || stripos($skill, $candidateSkill) !== false) {
                    $match = true;
                    break;
                }
            }
            $matches[$skill] = $match;
        }
        
        return $matches;
    }
    
    /**
     * Identify candidate strengths
     */
    private function identifyStrengths(Candidate $candidate, JobPosting $jobPosting): array
    {
        $strengths = [];
        
        // Technical strengths
        if ($candidate->years_of_experience >= 3) {
            $strengths[] = 'Strong technical experience';
        }
        
        // Education strengths
        if (str_contains(strtolower($candidate->highest_education ?? ''), 'computer science')) {
            $strengths[] = 'Relevant educational background';
        }
        
        // Skill strengths
        $candidateSkills = json_decode($candidate->skills ?? '[]', true) ?? [];
        if (count($candidateSkills) >= 5) {
            $strengths[] = 'Diverse skill set';
        }
        
        return $strengths;
    }
    
    /**
     * Identify candidate weaknesses
     */
    private function identifyWeaknesses(Candidate $candidate, JobPosting $jobPosting): array
    {
        $weaknesses = [];
        
        // Experience gaps
        if ($candidate->years_of_experience < 2) {
            $weaknesses[] = 'Limited professional experience';
        }
        
        // Missing skills
        $requiredSkills = $jobPosting->required_skills ?? [];
        $candidateSkills = json_decode($candidate->skills ?? '[]', true) ?? [];
        $missingSkills = array_diff($requiredSkills, $candidateSkills);
        
        if (!empty($missingSkills)) {
            $weaknesses[] = 'Missing key skills: ' . implode(', ', $missingSkills);
        }
        
        return $weaknesses;
    }
    
    /**
     * Generate AI recommendations
     */
    private function generateRecommendations(Candidate $candidate, JobPosting $jobPosting, float $score): array
    {
        $recommendations = [];
        
        if ($score >= 8.0) {
            $recommendations[] = 'Strong candidate - recommend for interview';
        } elseif ($score >= 7.0) {
            $recommendations[] = 'Good candidate - consider for interview';
        } elseif ($score >= 6.0) {
            $recommendations[] = 'Moderate candidate - review carefully';
        } else {
            $recommendations[] = 'Weak candidate - consider rejection';
        }
        
        return $recommendations;
    }
    
    /**
     * Identify risk factors
     */
    private function identifyRiskFactors(Candidate $candidate, JobPosting $jobPosting): array
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
        
        return $risks;
    }
    
    /**
     * Calculate AI confidence score
     */
    private function calculateConfidence(Candidate $candidate, JobPosting $jobPosting): float
    {
        $confidence = 0.8; // Base confidence
        
        // Increase confidence with more data
        if ($candidate->skills) $confidence += 0.1;
        if ($candidate->certifications) $confidence += 0.05;
        if ($candidate->languages) $confidence += 0.05;
        
        return min(1.0, $confidence);
    }
    
    // Additional helper methods for detailed analysis
    private function analyzeTechnicalProfile(Candidate $candidate): array
    {
        return [
            'skill_diversity' => count(json_decode($candidate->skills ?? '[]', true) ?? []),
            'experience_level' => $candidate->years_of_experience ?? 0,
            'education_relevance' => $this->assessEducationRelevance($candidate),
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
        return 8.5; // Simulated score
    }
    
    private function assessLeadershipPotential(Candidate $candidate): float
    {
        return 7.8; // Simulated score
    }
    
    private function assessTeamCollaboration(Candidate $candidate): float
    {
        return 8.2; // Simulated score
    }
    
    private function assessCulturalAlignment(Candidate $candidate): float
    {
        return 8.7; // Simulated score
    }
    
    private function assessLocationMatch(Candidate $candidate, JobPosting $jobPosting): float
    {
        return 0.9; // Simulated score
    }
    
    private function assessWorkStyleMatch(Candidate $candidate, JobPosting $jobPosting): float
    {
        return 0.8; // Simulated score
    }
    
    private function assessValuesAlignment(Candidate $candidate): float
    {
        return 0.85; // Simulated score
    }
    
    private function assessEducationRelevance(Candidate $candidate): float
    {
        return 8.0; // Simulated score
    }
    
    private function analyzeBehavior(Candidate $candidate): array
    {
        return [
            'problem_solving' => 'analytical',
            'decision_making' => 'data_driven',
            'communication' => 'clear_and_concise'
        ];
    }
} 