<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\AttendanceRecord;
use App\Models\TrainingProgress;
use Illuminate\Support\Facades\Log;

class AIPerformanceService
{
    /**
     * Generate performance insights for an employee
     */
    public function generatePerformanceInsights(Employee $employee): array
    {
        try {
            $performanceData = $this->gatherPerformanceData($employee);
            
            return [
                'overall_performance' => $this->calculateOverallPerformance($performanceData),
                'trend_analysis' => $this->analyzePerformanceTrends($performanceData),
                'strength_areas' => $this->identifyStrengthAreas($performanceData),
                'improvement_areas' => $this->identifyImprovementAreas($performanceData),
                'career_progression' => $this->assessCareerProgression($employee),
                'retention_risk' => $this->assessRetentionRisk($employee),
                'recommendations' => $this->generatePerformanceRecommendations($employee, $performanceData),
                'predictions' => $this->generatePerformancePredictions($employee, $performanceData)
            ];
        } catch (\Exception $e) {
            Log::error('AI Performance Analysis failed: ' . $e->getMessage());
            return ['error' => 'Performance analysis failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Predict performance risk for an employee
     */
    public function predictPerformanceRisk(Employee $employee): array
    {
        $riskFactors = [];
        $riskScore = 0;
        
        // Attendance risk
        $attendanceRate = $employee->attendance_rate ?? 100;
        if ($attendanceRate < 90) {
            $riskFactors[] = 'Low attendance rate (' . $attendanceRate . '%)';
            $riskScore += (100 - $attendanceRate) * 0.5;
        }
        
        // Performance rating risk
        $performanceRating = $employee->performance_rating ?? 5.0;
        if ($performanceRating < 3.5) {
            $riskFactors[] = 'Below average performance rating (' . $performanceRating . '/5.0)';
            $riskScore += (5.0 - $performanceRating) * 2;
        }
        
        // Training completion risk
        $trainingCompletion = $employee->training_completion_rate ?? 100;
        if ($trainingCompletion < 80) {
            $riskFactors[] = 'Incomplete training requirements (' . $trainingCompletion . '%)';
            $riskScore += (100 - $trainingCompletion) * 0.3;
        }
        
        // Tenure risk (new employees)
        $tenure = now()->diffInMonths($employee->hire_date);
        if ($tenure < 3) {
            $riskFactors[] = 'New employee (less than 3 months)';
            $riskScore += 1;
        }
        
        return [
            'risk_score' => min(10, $riskScore),
            'risk_level' => $this->getRiskLevel($riskScore),
            'risk_factors' => $riskFactors,
            'mitigation_strategies' => $this->generateMitigationStrategies($riskFactors)
        ];
    }
    
    /**
     * Recommend training based on performance gaps
     */
    public function recommendTraining(Employee $employee): array
    {
        $recommendations = [];
        
        // Performance-based recommendations
        $performanceRating = $employee->performance_rating ?? 5.0;
        if ($performanceRating < 4.0) {
            $recommendations[] = [
                'type' => 'performance_improvement',
                'title' => 'Performance Enhancement Training',
                'description' => 'Focus on improving core job skills and productivity',
                'priority' => 'high',
                'estimated_duration' => '2-4 weeks'
            ];
        }
        
        // Skill gap recommendations
        $skillGaps = $this->identifySkillGaps($employee);
        foreach ($skillGaps as $skill) {
            $recommendations[] = [
                'type' => 'skill_development',
                'title' => $skill . ' Training',
                'description' => 'Develop proficiency in ' . $skill,
                'priority' => 'medium',
                'estimated_duration' => '1-2 weeks'
            ];
        }
        
        // Leadership development
        if ($employee->position && in_array($employee->position->level, ['senior', 'lead', 'manager'])) {
            $recommendations[] = [
                'type' => 'leadership',
                'title' => 'Leadership Development Program',
                'description' => 'Enhance leadership and management skills',
                'priority' => 'medium',
                'estimated_duration' => '4-6 weeks'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Gather comprehensive performance data
     */
    private function gatherPerformanceData(Employee $employee): array
    {
        $reviews = PerformanceReview::where('employee_id', $employee->id)
            ->orderBy('review_date', 'desc')
            ->take(6)
            ->get();
            
        $attendance = AttendanceRecord::where('employee_id', $employee->id)
            ->where('date', '>=', now()->subMonths(3))
            ->get();
            
        $training = TrainingProgress::where('employee_id', $employee->id)
            ->with('training')
            ->get();
            
        return [
            'reviews' => $reviews,
            'attendance' => $attendance,
            'training' => $training,
            'current_rating' => $employee->performance_rating,
            'attendance_rate' => $employee->attendance_rate,
            'training_completion' => $employee->training_completion_rate
        ];
    }
    
    /**
     * Calculate overall performance score
     */
    private function calculateOverallPerformance(array $data): float
    {
        $score = 0;
        $weights = 0;
        
        // Performance rating (40% weight)
        if ($data['current_rating']) {
            $score += $data['current_rating'] * 0.4;
            $weights += 0.4;
        }
        
        // Attendance rate (30% weight)
        if ($data['attendance_rate']) {
            $score += ($data['attendance_rate'] / 100) * 5 * 0.3;
            $weights += 0.3;
        }
        
        // Training completion (20% weight)
        if ($data['training_completion']) {
            $score += ($data['training_completion'] / 100) * 5 * 0.2;
            $weights += 0.2;
        }
        
        // Recent reviews (10% weight)
        if ($data['reviews']->isNotEmpty()) {
            $avgReviewScore = $data['reviews']->avg('overall_rating') ?? 5.0;
            $score += $avgReviewScore * 0.1;
            $weights += 0.1;
        }
        
        return $weights > 0 ? round($score / $weights, 2) : 5.0;
    }
    
    /**
     * Analyze performance trends
     */
    private function analyzePerformanceTrends(array $data): array
    {
        $trends = [];
        
        if ($data['reviews']->count() >= 2) {
            $recentReviews = $data['reviews']->take(3);
            $olderReviews = $data['reviews']->skip(3)->take(3);
            
            $recentAvg = $recentReviews->avg('overall_rating') ?? 5.0;
            $olderAvg = $olderReviews->avg('overall_rating') ?? 5.0;
            
            $trends['performance_trend'] = $recentAvg > $olderAvg ? 'improving' : 
                ($recentAvg < $olderAvg ? 'declining' : 'stable');
            $trends['trend_magnitude'] = abs($recentAvg - $olderAvg);
        }
        
        return $trends;
    }
    
    /**
     * Identify strength areas
     */
    private function identifyStrengthAreas(array $data): array
    {
        $strengths = [];
        
        if ($data['current_rating'] >= 4.0) {
            $strengths[] = 'Strong overall performance';
        }
        
        if ($data['attendance_rate'] >= 95) {
            $strengths[] = 'Excellent attendance record';
        }
        
        if ($data['training_completion'] >= 90) {
            $strengths[] = 'High training completion rate';
        }
        
        return $strengths;
    }
    
    /**
     * Identify improvement areas
     */
    private function identifyImprovementAreas(array $data): array
    {
        $improvements = [];
        
        if ($data['current_rating'] < 4.0) {
            $improvements[] = 'Performance rating needs improvement';
        }
        
        if ($data['attendance_rate'] < 90) {
            $improvements[] = 'Attendance rate below target';
        }
        
        if ($data['training_completion'] < 80) {
            $improvements[] = 'Training completion rate needs attention';
        }
        
        return $improvements;
    }
    
    /**
     * Assess career progression potential
     */
    private function assessCareerProgression(Employee $employee): array
    {
        $potential = 7.0; // Base potential score
        
        // Factor in performance
        if ($employee->performance_rating >= 4.5) $potential += 1.0;
        elseif ($employee->performance_rating >= 4.0) $potential += 0.5;
        
        // Factor in tenure
        $tenure = now()->diffInMonths($employee->hire_date);
        if ($tenure >= 12) $potential += 0.5;
        if ($tenure >= 24) $potential += 0.5;
        
        // Factor in training completion
        if ($employee->training_completion_rate >= 90) $potential += 0.5;
        
        return [
            'potential_score' => min(10.0, $potential),
            'readiness_for_promotion' => $potential >= 8.0,
            'recommended_next_role' => $this->suggestNextRole($employee),
            'development_timeline' => $this->estimateDevelopmentTimeline($employee)
        ];
    }
    
    /**
     * Assess retention risk
     */
    private function assessRetentionRisk(Employee $employee): array
    {
        $riskScore = 5.0; // Base risk score
        
        // Factor in performance
        if ($employee->performance_rating < 3.0) $riskScore += 2.0;
        elseif ($employee->performance_rating < 4.0) $riskScore += 1.0;
        
        // Factor in attendance
        if ($employee->attendance_rate < 85) $riskScore += 1.5;
        elseif ($employee->attendance_rate < 90) $riskScore += 0.5;
        
        // Factor in tenure
        $tenure = now()->diffInMonths($employee->hire_date);
        if ($tenure < 6) $riskScore += 1.0; // New employees
        elseif ($tenure > 60) $riskScore += 0.5; // Long-tenured employees
        
        return [
            'risk_score' => min(10.0, $riskScore),
            'risk_level' => $this->getRiskLevel($riskScore),
            'retention_probability' => max(0, 10 - $riskScore) * 10,
            'intervention_needed' => $riskScore >= 7.0
        ];
    }
    
    /**
     * Generate performance recommendations
     */
    private function generatePerformanceRecommendations(Employee $employee, array $data): array
    {
        $recommendations = [];
        
        if ($employee->performance_rating < 4.0) {
            $recommendations[] = 'Schedule performance improvement meeting';
            $recommendations[] = 'Develop specific improvement goals';
            $recommendations[] = 'Provide additional training and support';
        }
        
        if ($employee->attendance_rate < 90) {
            $recommendations[] = 'Address attendance concerns';
            $recommendations[] = 'Identify and resolve attendance barriers';
        }
        
        if ($employee->training_completion_rate < 80) {
            $recommendations[] = 'Encourage training completion';
            $recommendations[] = 'Provide training time and resources';
        }
        
        return $recommendations;
    }
    
    /**
     * Generate performance predictions
     */
    private function generatePerformancePredictions(Employee $employee, array $data): array
    {
        $predictions = [];
        
        // Predict next performance rating
        $currentRating = $employee->performance_rating ?? 5.0;
        $trend = $this->analyzePerformanceTrends($data);
        
        if (isset($trend['performance_trend'])) {
            if ($trend['performance_trend'] === 'improving') {
                $predictions['next_rating'] = min(5.0, $currentRating + 0.2);
            } elseif ($trend['performance_trend'] === 'declining') {
                $predictions['next_rating'] = max(1.0, $currentRating - 0.2);
            } else {
                $predictions['next_rating'] = $currentRating;
            }
        } else {
            $predictions['next_rating'] = $currentRating;
        }
        
        // Predict retention probability
        $retentionRisk = $this->assessRetentionRisk($employee);
        $predictions['retention_probability'] = $retentionRisk['retention_probability'];
        
        return $predictions;
    }
    
    /**
     * Get risk level based on score
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
            if (str_contains($factor, 'attendance')) {
                $strategies[] = 'Implement flexible work arrangements';
                $strategies[] = 'Provide wellness and support programs';
            }
            
            if (str_contains($factor, 'performance')) {
                $strategies[] = 'Provide targeted training and development';
                $strategies[] = 'Set clear performance expectations';
                $strategies[] = 'Implement regular feedback sessions';
            }
            
            if (str_contains($factor, 'training')) {
                $strategies[] = 'Allocate dedicated training time';
                $strategies[] = 'Provide training incentives';
            }
        }
        
        return array_unique($strategies);
    }
    
    /**
     * Identify skill gaps
     */
    private function identifySkillGaps(Employee $employee): array
    {
        // Simulate skill gap analysis
        $gaps = [];
        
        if ($employee->position) {
            $positionSkills = json_decode($employee->position->skills ?? '[]', true) ?? [];
            $employeeSkills = json_decode($employee->skills ?? '[]', true) ?? [];
            
            foreach ($positionSkills as $skill) {
                if (!in_array($skill, $employeeSkills)) {
                    $gaps[] = $skill;
                }
            }
        }
        
        return $gaps;
    }
    
    /**
     * Suggest next role for employee
     */
    private function suggestNextRole(Employee $employee): ?string
    {
        if (!$employee->position) return null;
        
        $currentLevel = $employee->position->level ?? 'entry';
        $nextLevels = [
            'entry' => 'junior',
            'junior' => 'mid',
            'mid' => 'senior',
            'senior' => 'lead',
            'lead' => 'manager',
            'manager' => 'director'
        ];
        
        return $nextLevels[$currentLevel] ?? null;
    }
    
    /**
     * Estimate development timeline
     */
    private function estimateDevelopmentTimeline(Employee $employee): string
    {
        $tenure = now()->diffInMonths($employee->hire_date);
        $performance = $employee->performance_rating ?? 5.0;
        
        if ($performance >= 4.5 && $tenure >= 12) {
            return '6-12 months';
        } elseif ($performance >= 4.0 && $tenure >= 6) {
            return '12-18 months';
        } else {
            return '18-24 months';
        }
    }
} 