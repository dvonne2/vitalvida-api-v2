<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\AttendanceRecord;
use App\Models\TrainingProgress;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PerformanceIntelligenceService
{
    /**
     * Generate comprehensive weekly scorecard for employee
     */
    public function generateWeeklyScorecard(Employee $employee): array
    {
        try {
            $weekStart = now()->startOfWeek();
            $weekEnd = now()->endOfWeek();
            
            // Get weekly data
            $attendanceRecords = AttendanceRecord::where('employee_id', $employee->id)
                ->whereBetween('date', [$weekStart, $weekEnd])
                ->get();
            
            $performanceMetrics = $this->calculateWeeklyMetrics($employee, $attendanceRecords);
            $taskCompletion = $this->analyzeTaskCompletion($employee);
            $kpiScore = $this->calculateKPIScore($employee);
            $aiScore = $this->calculateAIScore($employee);
            
            $scorecard = [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->first_name . ' ' . $employee->last_name,
                    'department' => $employee->department->name ?? 'Unknown',
                    'position' => $employee->position->title ?? 'Unknown'
                ],
                'week_period' => $weekStart->format('M j') . ' - ' . $weekEnd->format('M j'),
                'attendance_summary' => [
                    'days_present' => $attendanceRecords->where('status', 'present')->count(),
                    'days_absent' => $attendanceRecords->where('status', 'absent')->count(),
                    'late_arrivals' => $attendanceRecords->where('status', 'late')->count(),
                    'wfh_days' => $attendanceRecords->where('work_mode', 'wfh')->count(),
                    'attendance_rate' => $this->calculateAttendanceRate($attendanceRecords)
                ],
                'performance_metrics' => $performanceMetrics,
                'task_completion' => $taskCompletion,
                'kpi_score' => $kpiScore,
                'ai_score' => $aiScore,
                'overall_score' => $this->calculateOverallScore($performanceMetrics, $taskCompletion, $kpiScore, $aiScore),
                'status' => $this->determinePerformanceStatus($performanceMetrics, $taskCompletion, $kpiScore, $aiScore),
                'trends' => $this->analyzeWeeklyTrends($employee),
                'insights' => $this->generateWeeklyInsights($employee, $performanceMetrics),
                'recommendations' => $this->generateWeeklyRecommendations($employee, $performanceMetrics)
            ];
            
            return $scorecard;
            
        } catch (\Exception $e) {
            Log::error('Performance Intelligence - Weekly Scorecard Error: ' . $e->getMessage());
            return ['error' => 'Scorecard generation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Identify performance risks for employee
     */
    public function identifyPerformanceRisks(Employee $employee): array
    {
        try {
            $risks = [];
            
            // Performance rating risk
            $performanceRisk = $this->assessPerformanceRatingRisk($employee);
            if ($performanceRisk['risk_level'] !== 'low') {
                $risks[] = $performanceRisk;
            }
            
            // Attendance risk
            $attendanceRisk = $this->assessAttendanceRisk($employee);
            if ($attendanceRisk['risk_level'] !== 'low') {
                $risks[] = $attendanceRisk;
            }
            
            // Task completion risk
            $taskRisk = $this->assessTaskCompletionRisk($employee);
            if ($taskRisk['risk_level'] !== 'low') {
                $risks[] = $taskRisk;
            }
            
            // Training risk
            $trainingRisk = $this->assessTrainingRisk($employee);
            if ($trainingRisk['risk_level'] !== 'low') {
                $risks[] = $trainingRisk;
            }
            
            // Behavioral risk
            $behavioralRisk = $this->assessBehavioralRisk($employee);
            if ($behavioralRisk['risk_level'] !== 'low') {
                $risks[] = $behavioralRisk;
            }
            
            return [
                'risks' => $risks,
                'total_risks' => count($risks),
                'high_priority_risks' => count(array_filter($risks, fn($r) => $r['risk_level'] === 'high')),
                'overall_risk_score' => $this->calculateOverallRiskScore($risks),
                'risk_level' => $this->determineOverallRiskLevel($risks),
                'intervention_priority' => $this->calculateInterventionPriority($risks)
            ];
            
        } catch (\Exception $e) {
            Log::error('Performance Intelligence - Risk Identification Error: ' . $e->getMessage());
            return ['error' => 'Risk identification failed: ' . $e->getMessage()];
        }
    }

    /**
     * Recommend training programs for employee
     */
    public function recommendTrainingPrograms(Employee $employee): array
    {
        try {
            $skillGaps = $this->identifySkillGaps($employee);
            $performanceAreas = $this->identifyPerformanceAreas($employee);
            $careerGoals = $this->assessCareerGoals($employee);
            
            $recommendations = [
                'skill_based_training' => $this->recommendSkillBasedTraining($employee, $skillGaps),
                'performance_improvement' => $this->recommendPerformanceTraining($employee, $performanceAreas),
                'leadership_development' => $this->recommendLeadershipTraining($employee, $careerGoals),
                'technical_certifications' => $this->recommendTechnicalCertifications($employee, $skillGaps),
                'soft_skills_training' => $this->recommendSoftSkillsTraining($employee, $performanceAreas)
            ];
            
            return [
                'recommendations' => $recommendations,
                'priority_training' => $this->prioritizeTraining($recommendations),
                'training_timeline' => $this->createTrainingTimeline($recommendations),
                'expected_outcomes' => $this->predictTrainingOutcomes($employee, $recommendations)
            ];
            
        } catch (\Exception $e) {
            Log::error('Performance Intelligence - Training Recommendations Error: ' . $e->getMessage());
            return ['error' => 'Training recommendations failed: ' . $e->getMessage()];
        }
    }

    /**
     * Calculate bonus eligibility for employee
     */
    public function calculateBonusEligibility(Employee $employee): array
    {
        try {
            $performanceCriteria = $this->assessPerformanceCriteria($employee);
            $attendanceCriteria = $this->assessAttendanceCriteria($employee);
            $projectCriteria = $this->assessProjectCriteria($employee);
            $teamworkCriteria = $this->assessTeamworkCriteria($employee);
            
            $eligibilityScore = $this->calculateEligibilityScore($performanceCriteria, $attendanceCriteria, $projectCriteria, $teamworkCriteria);
            $bonusAmount = $this->calculateBonusAmount($employee, $eligibilityScore);
            
            return [
                'eligibility_score' => $eligibilityScore,
                'bonus_amount' => $bonusAmount,
                'eligibility_status' => $this->determineEligibilityStatus($eligibilityScore),
                'criteria_breakdown' => [
                    'performance' => $performanceCriteria,
                    'attendance' => $attendanceCriteria,
                    'projects' => $projectCriteria,
                    'teamwork' => $teamworkCriteria
                ],
                'justification' => $this->generateBonusJustification($employee, $eligibilityScore),
                'next_period_targets' => $this->generateNextPeriodTargets($employee, $eligibilityScore)
            ];
            
        } catch (\Exception $e) {
            Log::error('Performance Intelligence - Bonus Calculation Error: ' . $e->getMessage());
            return ['error' => 'Bonus calculation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Calculate weekly performance metrics
     */
    private function calculateWeeklyMetrics(Employee $employee, $attendanceRecords): array
    {
        $metrics = [
            'productivity_score' => $this->calculateProductivityScore($employee),
            'quality_score' => $this->calculateQualityScore($employee),
            'efficiency_score' => $this->calculateEfficiencyScore($employee),
            'collaboration_score' => $this->calculateCollaborationScore($employee),
            'initiative_score' => $this->calculateInitiativeScore($employee)
        ];
        
        return $metrics;
    }

    /**
     * Analyze task completion
     */
    private function analyzeTaskCompletion(Employee $employee): array
    {
        // Simulated task completion analysis
        $totalTasks = rand(15, 25);
        $completedTasks = rand(12, $totalTasks);
        $onTimeTasks = rand(10, $completedTasks);
        $qualityScore = rand(70, 95);
        
        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'on_time_tasks' => $onTimeTasks,
            'completion_rate' => round(($completedTasks / $totalTasks) * 100, 1),
            'on_time_rate' => round(($onTimeTasks / $completedTasks) * 100, 1),
            'quality_score' => $qualityScore,
            'overdue_tasks' => $totalTasks - $completedTasks
        ];
    }

    /**
     * Calculate KPI score
     */
    private function calculateKPIScore(Employee $employee): float
    {
        $baseScore = $employee->performance_rating ?? 5.0;
        
        // Attendance factor
        $attendanceFactor = ($employee->attendance_rate ?? 100) / 100;
        
        // Task completion factor
        $taskCompletionFactor = 0.8; // Simulated
        
        // Quality factor
        $qualityFactor = 0.85; // Simulated
        
        $kpiScore = $baseScore * $attendanceFactor * $taskCompletionFactor * $qualityFactor;
        
        return min(10.0, $kpiScore);
    }

    /**
     * Calculate AI score
     */
    private function calculateAIScore(Employee $employee): float
    {
        $aiScore = $employee->ai_score ?? 7.0;
        
        // Adjust based on recent performance
        $recentPerformance = $this->getRecentPerformance($employee);
        $adjustment = ($recentPerformance - 7.0) * 0.1;
        
        return min(10.0, max(0.0, $aiScore + $adjustment));
    }

    /**
     * Calculate overall score
     */
    private function calculateOverallScore(array $metrics, array $taskCompletion, float $kpiScore, float $aiScore): float
    {
        $performanceScore = array_sum($metrics) / count($metrics);
        $taskScore = $taskCompletion['completion_rate'] / 10;
        
        $overallScore = ($performanceScore * 0.3) + ($taskScore * 0.2) + ($kpiScore * 0.3) + ($aiScore * 0.2);
        
        return round($overallScore, 2);
    }

    /**
     * Determine performance status
     */
    private function determinePerformanceStatus(array $metrics, array $taskCompletion, float $kpiScore, float $aiScore): string
    {
        $overallScore = $this->calculateOverallScore($metrics, $taskCompletion, $kpiScore, $aiScore);
        
        if ($overallScore >= 8.5) return 'excellent';
        if ($overallScore >= 7.5) return 'good';
        if ($overallScore >= 6.5) return 'satisfactory';
        if ($overallScore >= 5.5) return 'needs_improvement';
        return 'unsatisfactory';
    }

    /**
     * Calculate attendance rate
     */
    private function calculateAttendanceRate($attendanceRecords): float
    {
        $totalDays = $attendanceRecords->count();
        $presentDays = $attendanceRecords->where('status', 'present')->count();
        
        return $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 100;
    }

    /**
     * Analyze weekly trends
     */
    private function analyzeWeeklyTrends(Employee $employee): array
    {
        return [
            'productivity_trend' => 'improving',
            'quality_trend' => 'stable',
            'attendance_trend' => 'consistent',
            'collaboration_trend' => 'improving'
        ];
    }

    /**
     * Generate weekly insights
     */
    private function generateWeeklyInsights(Employee $employee, array $metrics): array
    {
        $insights = [];
        
        if ($metrics['productivity_score'] >= 8.0) {
            $insights[] = 'Excellent productivity this week';
        }
        
        if ($metrics['collaboration_score'] >= 8.0) {
            $insights[] = 'Strong team collaboration demonstrated';
        }
        
        if ($metrics['initiative_score'] >= 8.0) {
            $insights[] = 'Shows strong initiative and leadership';
        }
        
        return $insights;
    }

    /**
     * Generate weekly recommendations
     */
    private function generateWeeklyRecommendations(Employee $employee, array $metrics): array
    {
        $recommendations = [];
        
        if ($metrics['productivity_score'] < 7.0) {
            $recommendations[] = 'Focus on task prioritization';
        }
        
        if ($metrics['quality_score'] < 7.0) {
            $recommendations[] = 'Review quality standards and processes';
        }
        
        if ($metrics['collaboration_score'] < 7.0) {
            $recommendations[] = 'Enhance team communication';
        }
        
        return $recommendations;
    }

    /**
     * Assess performance rating risk
     */
    private function assessPerformanceRatingRisk(Employee $employee): array
    {
        $rating = $employee->performance_rating ?? 5.0;
        
        if ($rating < 3.0) {
            return [
                'type' => 'performance_rating',
                'risk_level' => 'critical',
                'description' => 'Performance rating below acceptable standards',
                'impact' => 'High risk of performance improvement plan',
                'recommendations' => ['Immediate performance improvement plan', 'Regular monitoring and support']
            ];
        } elseif ($rating < 4.0) {
            return [
                'type' => 'performance_rating',
                'risk_level' => 'high',
                'description' => 'Performance rating needs improvement',
                'impact' => 'Risk of performance issues',
                'recommendations' => ['Performance improvement plan', 'Additional training and support']
            ];
        }
        
        return [
            'type' => 'performance_rating',
            'risk_level' => 'low',
            'description' => 'Performance rating acceptable',
            'impact' => 'No immediate concerns',
            'recommendations' => ['Continue current performance level']
        ];
    }

    /**
     * Assess attendance risk
     */
    private function assessAttendanceRisk(Employee $employee): array
    {
        $attendanceRate = $employee->attendance_rate ?? 100;
        
        if ($attendanceRate < 80) {
            return [
                'type' => 'attendance',
                'risk_level' => 'high',
                'description' => 'Low attendance rate detected',
                'impact' => 'May affect productivity and team performance',
                'recommendations' => ['Address attendance concerns', 'Implement attendance improvement plan']
            ];
        } elseif ($attendanceRate < 90) {
            return [
                'type' => 'attendance',
                'risk_level' => 'medium',
                'description' => 'Attendance rate below optimal',
                'impact' => 'Minor impact on performance',
                'recommendations' => ['Monitor attendance patterns', 'Provide support if needed']
            ];
        }
        
        return [
            'type' => 'attendance',
            'risk_level' => 'low',
            'description' => 'Attendance rate acceptable',
            'impact' => 'No attendance concerns',
            'recommendations' => ['Maintain current attendance level']
        ];
    }

    /**
     * Assess task completion risk
     */
    private function assessTaskCompletionRisk(Employee $employee): array
    {
        // Simulated task completion analysis
        $completionRate = rand(70, 95);
        
        if ($completionRate < 75) {
            return [
                'type' => 'task_completion',
                'risk_level' => 'high',
                'description' => 'Low task completion rate',
                'impact' => 'May affect project timelines and team performance',
                'recommendations' => ['Review workload distribution', 'Provide task management support']
            ];
        }
        
        return [
            'type' => 'task_completion',
            'risk_level' => 'low',
            'description' => 'Task completion rate acceptable',
            'impact' => 'No task completion concerns',
            'recommendations' => ['Maintain current task completion level']
        ];
    }

    /**
     * Assess training risk
     */
    private function assessTrainingRisk(Employee $employee): array
    {
        // Simulated training analysis
        $trainingProgress = rand(60, 100);
        
        if ($trainingProgress < 70) {
            return [
                'type' => 'training',
                'risk_level' => 'medium',
                'description' => 'Training progress below expected',
                'impact' => 'May affect skill development and career progression',
                'recommendations' => ['Provide additional training support', 'Schedule regular check-ins']
            ];
        }
        
        return [
            'type' => 'training',
            'risk_level' => 'low',
            'description' => 'Training progress on track',
            'impact' => 'No training concerns',
            'recommendations' => ['Continue current training progress']
        ];
    }

    /**
     * Assess behavioral risk
     */
    private function assessBehavioralRisk(Employee $employee): array
    {
        return [
            'type' => 'behavioral',
            'risk_level' => 'low',
            'description' => 'No behavioral concerns detected',
            'impact' => 'No behavioral impact',
            'recommendations' => ['Maintain current behavioral standards']
        ];
    }

    /**
     * Calculate overall risk score
     */
    private function calculateOverallRiskScore(array $risks): float
    {
        if (empty($risks)) return 0.0;
        
        $riskScores = [
            'critical' => 10.0,
            'high' => 7.5,
            'medium' => 5.0,
            'low' => 2.5
        ];
        
        $totalScore = 0;
        foreach ($risks as $risk) {
            $totalScore += $riskScores[$risk['risk_level']] ?? 5.0;
        }
        
        return min(10.0, $totalScore / count($risks));
    }

    /**
     * Determine overall risk level
     */
    private function determineOverallRiskLevel(array $risks): string
    {
        $criticalRisks = count(array_filter($risks, fn($r) => $r['risk_level'] === 'critical'));
        $highRisks = count(array_filter($risks, fn($r) => $r['risk_level'] === 'high'));
        
        if ($criticalRisks > 0) return 'critical';
        if ($highRisks > 1) return 'high';
        if ($highRisks > 0) return 'medium';
        return 'low';
    }

    /**
     * Calculate intervention priority
     */
    private function calculateInterventionPriority(array $risks): string
    {
        $overallRiskLevel = $this->determineOverallRiskLevel($risks);
        
        if ($overallRiskLevel === 'critical') return 'immediate';
        if ($overallRiskLevel === 'high') return 'high';
        if ($overallRiskLevel === 'medium') return 'medium';
        return 'low';
    }

    /**
     * Identify skill gaps
     */
    private function identifySkillGaps(Employee $employee): array
    {
        return [
            'technical_skills' => ['advanced_excel', 'data_analysis'],
            'soft_skills' => ['presentation_skills', 'time_management'],
            'leadership_skills' => ['team_management', 'strategic_thinking']
        ];
    }

    /**
     * Identify performance areas
     */
    private function identifyPerformanceAreas(Employee $employee): array
    {
        return [
            'strengths' => ['collaboration', 'problem_solving'],
            'improvement_areas' => ['time_management', 'communication'],
            'development_opportunities' => ['leadership', 'technical_skills']
        ];
    }

    /**
     * Assess career goals
     */
    private function assessCareerGoals(Employee $employee): array
    {
        return [
            'short_term_goals' => ['Improve technical skills', 'Complete certification'],
            'long_term_goals' => ['Senior position', 'Leadership role'],
            'readiness_assessment' => 'moderate',
            'timeline' => '12-18 months'
        ];
    }

    /**
     * Recommend skill-based training
     */
    private function recommendSkillBasedTraining(Employee $employee, array $skillGaps): array
    {
        return [
            'technical_courses' => ['Advanced Excel Workshop', 'Data Analysis Certification'],
            'soft_skills_courses' => ['Presentation Skills Training', 'Time Management Workshop'],
            'online_learning' => ['LinkedIn Learning Courses', 'Coursera Specializations'],
            'internal_training' => ['Company-specific technical training', 'Mentorship programs']
        ];
    }

    /**
     * Recommend performance training
     */
    private function recommendPerformanceTraining(Employee $employee, array $performanceAreas): array
    {
        return [
            'performance_improvement' => ['Goal Setting Workshop', 'Performance Management Training'],
            'communication_skills' => ['Effective Communication Course', 'Presentation Skills Training'],
            'time_management' => ['Productivity Workshop', 'Project Management Basics'],
            'quality_improvement' => ['Quality Management Training', 'Process Improvement Workshop']
        ];
    }

    /**
     * Recommend leadership training
     */
    private function recommendLeadershipTraining(Employee $employee, array $careerGoals): array
    {
        return [
            'leadership_development' => ['Leadership Fundamentals', 'Team Management Training'],
            'strategic_thinking' => ['Strategic Planning Workshop', 'Business Acumen Training'],
            'mentoring_skills' => ['Mentoring and Coaching', 'Peer Leadership Program'],
            'executive_skills' => ['Executive Communication', 'Decision Making Workshop']
        ];
    }

    /**
     * Recommend technical certifications
     */
    private function recommendTechnicalCertifications(Employee $employee, array $skillGaps): array
    {
        return [
            'data_analysis' => ['Google Data Analytics Certificate', 'Microsoft Power BI Certification'],
            'project_management' => ['PMP Certification', 'PRINCE2 Foundation'],
            'technical_skills' => ['AWS Certification', 'Microsoft Azure Certification'],
            'industry_specific' => ['Industry-specific certifications based on role']
        ];
    }

    /**
     * Recommend soft skills training
     */
    private function recommendSoftSkillsTraining(Employee $employee, array $performanceAreas): array
    {
        return [
            'communication' => ['Effective Communication', 'Public Speaking Workshop'],
            'collaboration' => ['Team Building', 'Cross-functional Collaboration'],
            'emotional_intelligence' => ['EQ Training', 'Conflict Resolution'],
            'adaptability' => ['Change Management', 'Agile Mindset Training']
        ];
    }

    /**
     * Prioritize training recommendations
     */
    private function prioritizeTraining(array $recommendations): array
    {
        return [
            'high_priority' => ['Performance improvement training', 'Technical skill development'],
            'medium_priority' => ['Leadership development', 'Soft skills training'],
            'low_priority' => ['Advanced certifications', 'Specialized training']
        ];
    }

    /**
     * Create training timeline
     */
    private function createTrainingTimeline(array $recommendations): array
    {
        return [
            'immediate_0_3_months' => ['Performance improvement training', 'Technical skill development'],
            'short_term_3_6_months' => ['Leadership development', 'Soft skills training'],
            'long_term_6_12_months' => ['Advanced certifications', 'Specialized training']
        ];
    }

    /**
     * Predict training outcomes
     */
    private function predictTrainingOutcomes(Employee $employee, array $recommendations): array
    {
        return [
            'performance_improvement' => '15-20% improvement expected',
            'skill_development' => 'Enhanced technical and soft skills',
            'career_progression' => 'Increased promotion readiness',
            'productivity_gain' => '10-15% productivity improvement',
            'retention_impact' => 'Improved employee satisfaction and retention'
        ];
    }

    /**
     * Assess performance criteria for bonus
     */
    private function assessPerformanceCriteria(Employee $employee): array
    {
        $rating = $employee->performance_rating ?? 5.0;
        
        return [
            'score' => $rating,
            'meets_threshold' => $rating >= 4.0,
            'weight' => 0.4,
            'contribution' => $rating * 0.4
        ];
    }

    /**
     * Assess attendance criteria for bonus
     */
    private function assessAttendanceCriteria(Employee $employee): array
    {
        $attendanceRate = $employee->attendance_rate ?? 100;
        
        return [
            'score' => $attendanceRate / 10,
            'meets_threshold' => $attendanceRate >= 90,
            'weight' => 0.2,
            'contribution' => ($attendanceRate / 10) * 0.2
        ];
    }

    /**
     * Assess project criteria for bonus
     */
    private function assessProjectCriteria(Employee $employee): array
    {
        // Simulated project performance
        $projectScore = rand(70, 95) / 10;
        
        return [
            'score' => $projectScore,
            'meets_threshold' => $projectScore >= 7.0,
            'weight' => 0.25,
            'contribution' => $projectScore * 0.25
        ];
    }

    /**
     * Assess teamwork criteria for bonus
     */
    private function assessTeamworkCriteria(Employee $employee): array
    {
        // Simulated teamwork score
        $teamworkScore = rand(75, 95) / 10;
        
        return [
            'score' => $teamworkScore,
            'meets_threshold' => $teamworkScore >= 7.0,
            'weight' => 0.15,
            'contribution' => $teamworkScore * 0.15
        ];
    }

    /**
     * Calculate eligibility score
     */
    private function calculateEligibilityScore(array $performance, array $attendance, array $projects, array $teamwork): float
    {
        return $performance['contribution'] + $attendance['contribution'] + $projects['contribution'] + $teamwork['contribution'];
    }

    /**
     * Calculate bonus amount
     */
    private function calculateBonusAmount(Employee $employee, float $eligibilityScore): float
    {
        $baseSalary = $employee->base_salary ?? 500000;
        
        if ($eligibilityScore >= 8.5) {
            return $baseSalary * 0.15; // 15% bonus
        } elseif ($eligibilityScore >= 7.5) {
            return $baseSalary * 0.10; // 10% bonus
        } elseif ($eligibilityScore >= 6.5) {
            return $baseSalary * 0.05; // 5% bonus
        }
        
        return 0;
    }

    /**
     * Determine eligibility status
     */
    private function determineEligibilityStatus(float $eligibilityScore): string
    {
        if ($eligibilityScore >= 8.5) return 'highly_eligible';
        if ($eligibilityScore >= 7.5) return 'eligible';
        if ($eligibilityScore >= 6.5) return 'conditionally_eligible';
        return 'not_eligible';
    }

    /**
     * Generate bonus justification
     */
    private function generateBonusJustification(Employee $employee, float $eligibilityScore): array
    {
        $justifications = [];
        
        if ($eligibilityScore >= 8.5) {
            $justifications[] = 'Exceptional performance across all criteria';
            $justifications[] = 'Consistently exceeds expectations';
            $justifications[] = 'Strong contribution to team and company goals';
        } elseif ($eligibilityScore >= 7.5) {
            $justifications[] = 'Good performance meeting most criteria';
            $justifications[] = 'Reliable attendance and teamwork';
            $justifications[] = 'Positive contribution to projects';
        }
        
        return $justifications;
    }

    /**
     * Generate next period targets
     */
    private function generateNextPeriodTargets(Employee $employee, float $eligibilityScore): array
    {
        return [
            'performance_targets' => ['Maintain high performance standards', 'Improve in identified areas'],
            'attendance_targets' => ['Maintain 95%+ attendance rate', 'Improve punctuality'],
            'project_targets' => ['Complete projects on time', 'Enhance project quality'],
            'teamwork_targets' => ['Strengthen team collaboration', 'Mentor junior team members']
        ];
    }

    /**
     * Calculate productivity score
     */
    private function calculateProductivityScore(Employee $employee): float
    {
        return rand(70, 95) / 10;
    }

    /**
     * Calculate quality score
     */
    private function calculateQualityScore(Employee $employee): float
    {
        return rand(75, 95) / 10;
    }

    /**
     * Calculate efficiency score
     */
    private function calculateEfficiencyScore(Employee $employee): float
    {
        return rand(70, 90) / 10;
    }

    /**
     * Calculate collaboration score
     */
    private function calculateCollaborationScore(Employee $employee): float
    {
        return rand(75, 95) / 10;
    }

    /**
     * Calculate initiative score
     */
    private function calculateInitiativeScore(Employee $employee): float
    {
        return rand(70, 90) / 10;
    }

    /**
     * Get recent performance
     */
    private function getRecentPerformance(Employee $employee): float
    {
        return rand(65, 95) / 10;
    }
} 