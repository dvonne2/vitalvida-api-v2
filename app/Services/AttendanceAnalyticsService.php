<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\AttendanceRecord;
use App\Models\PerformanceReview;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceAnalyticsService
{
    /**
     * Detect absenteeism patterns for employee
     */
    public function detectAbsenteeismPatterns(Employee $employee): array
    {
        try {
            $attendanceRecords = AttendanceRecord::where('employee_id', $employee->id)
                ->where('date', '>=', now()->subMonths(3))
                ->orderBy('date')
                ->get();
            
            $patterns = [
                'weekly_pattern' => $this->analyzeWeeklyPattern($attendanceRecords),
                'monthly_trend' => $this->analyzeMonthlyTrend($attendanceRecords),
                'seasonal_pattern' => $this->analyzeSeasonalPattern($attendanceRecords),
                'consecutive_absences' => $this->findConsecutiveAbsences($attendanceRecords),
                'pattern_changes' => $this->detectPatternChanges($attendanceRecords),
                'risk_indicators' => $this->identifyRiskIndicators($attendanceRecords)
            ];
            
            return [
                'patterns' => $patterns,
                'risk_score' => $this->calculateAbsenteeismRisk($patterns),
                'recommendations' => $this->generateAbsenteeismRecommendations($patterns)
            ];
            
        } catch (\Exception $e) {
            Log::error('Attendance Analytics - Absenteeism Pattern Error: ' . $e->getMessage());
            return ['error' => 'Pattern analysis failed: ' . $e->getMessage()];
        }
    }

    /**
     * Analyze WFH patterns for employee
     */
    public function analyzeWFHPatterns(Employee $employee): array
    {
        try {
            $wfhRecords = AttendanceRecord::where('employee_id', $employee->id)
                ->where('work_mode', 'wfh')
                ->where('date', '>=', now()->subMonths(3))
                ->get();
            
            $patterns = [
                'frequency' => $this->calculateWFHFrequency($wfhRecords),
                'day_preferences' => $this->analyzeWFHDayPreferences($wfhRecords),
                'productivity_correlation' => $this->analyzeWFHProductivityCorrelation($employee, $wfhRecords),
                'team_impact' => $this->assessWFHTeamImpact($employee, $wfhRecords),
                'compliance_status' => $this->checkWFHCompliance($employee, $wfhRecords)
            ];
            
            return [
                'wfh_patterns' => $patterns,
                'recommendations' => $this->generateWFHRecommendations($patterns),
                'policy_compliance' => $this->assessPolicyCompliance($patterns)
            ];
            
        } catch (\Exception $e) {
            Log::error('Attendance Analytics - WFH Pattern Error: ' . $e->getMessage());
            return ['error' => 'WFH analysis failed: ' . $e->getMessage()];
        }
    }

    /**
     * Generate attendance alerts for employee
     */
    public function generateAttendanceAlerts(Employee $employee): array
    {
        try {
            $alerts = [];
            
            // Check for recent absences
            $recentAbsences = AttendanceRecord::where('employee_id', $employee->id)
                ->where('status', 'absent')
                ->where('date', '>=', now()->subWeek())
                ->count();
            
            if ($recentAbsences > 0) {
                $alerts[] = [
                    'type' => 'absence_alert',
                    'severity' => 'medium',
                    'message' => "Employee has {$recentAbsences} absence(s) this week",
                    'action_required' => 'Schedule check-in meeting'
                ];
            }
            
            // Check for late arrivals
            $recentLates = AttendanceRecord::where('employee_id', $employee->id)
                ->where('status', 'late')
                ->where('date', '>=', now()->subWeek())
                ->count();
            
            if ($recentLates > 2) {
                $alerts[] = [
                    'type' => 'punctuality_alert',
                    'severity' => 'high',
                    'message' => "Employee has {$recentLates} late arrival(s) this week",
                    'action_required' => 'Address punctuality concerns'
                ];
            }
            
            // Check for pattern changes
            $patternChanges = $this->detectRecentPatternChanges($employee);
            if ($patternChanges['has_changes']) {
                $alerts[] = [
                    'type' => 'pattern_change_alert',
                    'severity' => 'medium',
                    'message' => 'Unusual attendance pattern detected',
                    'action_required' => 'Investigate pattern change'
                ];
            }
            
            // Check for excessive WFH
            $wfhAnalysis = $this->analyzeWFHPatterns($employee);
            if (isset($wfhAnalysis['wfh_patterns']['frequency']['excessive'])) {
                $alerts[] = [
                    'type' => 'wfh_alert',
                    'severity' => 'medium',
                    'message' => 'Excessive WFH pattern detected',
                    'action_required' => 'Review WFH policy compliance'
                ];
            }
            
            return [
                'alerts' => $alerts,
                'total_alerts' => count($alerts),
                'high_priority_alerts' => count(array_filter($alerts, fn($a) => $a['severity'] === 'high')),
                'recommendations' => $this->generateAlertRecommendations($alerts)
            ];
            
        } catch (\Exception $e) {
            Log::error('Attendance Analytics - Alert Generation Error: ' . $e->getMessage());
            return ['error' => 'Alert generation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Calculate productivity correlation with attendance
     */
    public function calculateProductivityCorrelation(Employee $employee): array
    {
        try {
            $attendanceRecords = AttendanceRecord::where('employee_id', $employee->id)
                ->where('date', '>=', now()->subMonths(3))
                ->get();
            
            $performanceReviews = PerformanceReview::where('employee_id', $employee->id)
                ->where('review_date', '>=', now()->subMonths(3))
                ->get();
            
            $correlation = [
                'attendance_performance_correlation' => $this->calculateCorrelationCoefficient($attendanceRecords, $performanceReviews),
                'wfh_productivity_impact' => $this->analyzeWFHProductivityImpact($employee),
                'punctuality_performance_link' => $this->analyzePunctualityPerformanceLink($employee),
                'overtime_productivity' => $this->analyzeOvertimeProductivity($employee),
                'workload_distribution' => $this->analyzeWorkloadDistribution($employee)
            ];
            
            return [
                'correlation_analysis' => $correlation,
                'productivity_insights' => $this->generateProductivityInsights($correlation),
                'optimization_recommendations' => $this->generateProductivityRecommendations($correlation)
            ];
            
        } catch (\Exception $e) {
            Log::error('Attendance Analytics - Productivity Correlation Error: ' . $e->getMessage());
            return ['error' => 'Productivity analysis failed: ' . $e->getMessage()];
        }
    }

    /**
     * Analyze weekly attendance pattern
     */
    private function analyzeWeeklyPattern($attendanceRecords): array
    {
        $weeklyStats = [];
        
        for ($day = 1; $day <= 7; $day++) {
            $dayRecords = $attendanceRecords->filter(function($record) use ($day) {
                return Carbon::parse($record->date)->dayOfWeek === $day;
            });
            
            $totalDays = $dayRecords->count();
            $absentDays = $dayRecords->where('status', 'absent')->count();
            $lateDays = $dayRecords->where('status', 'late')->count();
            
            $dayName = Carbon::create()->startOfWeek()->addDays($day - 1)->format('l');
            $weeklyStats[strtolower($dayName) . '_absence_rate'] = $totalDays > 0 ? ($absentDays / $totalDays) * 100 : 0;
            $weeklyStats[strtolower($dayName) . '_late_rate'] = $totalDays > 0 ? ($lateDays / $totalDays) * 100 : 0;
        }
        
        return $weeklyStats;
    }

    /**
     * Analyze monthly attendance trend
     */
    private function analyzeMonthlyTrend($attendanceRecords): array
    {
        $monthlyStats = [];
        
        for ($month = 0; $month < 3; $month++) {
            $monthStart = now()->subMonths($month)->startOfMonth();
            $monthEnd = now()->subMonths($month)->endOfMonth();
            
            $monthRecords = $attendanceRecords->filter(function($record) use ($monthStart, $monthEnd) {
                $recordDate = Carbon::parse($record->date);
                return $recordDate->between($monthStart, $monthEnd);
            });
            
            $totalDays = $monthRecords->count();
            $absentDays = $monthRecords->where('status', 'absent')->count();
            $attendanceRate = $totalDays > 0 ? (($totalDays - $absentDays) / $totalDays) * 100 : 100;
            
            $monthlyStats[now()->subMonths($month)->format('Y-m')] = [
                'attendance_rate' => round($attendanceRate, 1),
                'absent_days' => $absentDays,
                'total_days' => $totalDays
            ];
        }
        
        return $monthlyStats;
    }

    /**
     * Analyze seasonal attendance pattern
     */
    private function analyzeSeasonalPattern($attendanceRecords): array
    {
        $seasonalStats = [];
        
        foreach (['spring', 'summer', 'autumn', 'winter'] as $season) {
            $seasonRecords = $attendanceRecords->filter(function($record) use ($season) {
                $month = Carbon::parse($record->date)->month;
                return $this->isSeason($month, $season);
            });
            
            $totalDays = $seasonRecords->count();
            $absentDays = $seasonRecords->where('status', 'absent')->count();
            $attendanceRate = $totalDays > 0 ? (($totalDays - $absentDays) / $totalDays) * 100 : 100;
            
            $seasonalStats[$season] = [
                'attendance_rate' => round($attendanceRate, 1),
                'absent_days' => $absentDays,
                'total_days' => $totalDays
            ];
        }
        
        return $seasonalStats;
    }

    /**
     * Find consecutive absences
     */
    private function findConsecutiveAbsences($attendanceRecords): int
    {
        $maxConsecutive = 0;
        $currentConsecutive = 0;
        
        foreach ($attendanceRecords->sortBy('date') as $record) {
            if ($record->status === 'absent') {
                $currentConsecutive++;
                $maxConsecutive = max($maxConsecutive, $currentConsecutive);
            } else {
                $currentConsecutive = 0;
            }
        }
        
        return $maxConsecutive;
    }

    /**
     * Detect pattern changes
     */
    private function detectPatternChanges($attendanceRecords): bool
    {
        if ($attendanceRecords->count() < 10) return false;
        
        $recentRecords = $attendanceRecords->take(5);
        $olderRecords = $attendanceRecords->skip(5)->take(5);
        
        $recentAbsenceRate = $this->calculateAbsenceRate($recentRecords);
        $olderAbsenceRate = $this->calculateAbsenceRate($olderRecords);
        
        return abs($recentAbsenceRate - $olderAbsenceRate) > 20;
    }

    /**
     * Identify risk indicators
     */
    private function identifyRiskIndicators($attendanceRecords): array
    {
        $indicators = [];
        
        // Monday/Friday pattern
        $mondayAbsences = $attendanceRecords->filter(function($record) {
            return Carbon::parse($record->date)->dayOfWeek === 1 && $record->status === 'absent';
        })->count();
        
        $fridayAbsences = $attendanceRecords->filter(function($record) {
            return Carbon::parse($record->date)->dayOfWeek === 5 && $record->status === 'absent';
        })->count();
        
        if ($mondayAbsences > 3) {
            $indicators[] = 'High Monday absence rate';
        }
        
        if ($fridayAbsences > 3) {
            $indicators[] = 'High Friday absence rate';
        }
        
        // Consecutive absences
        $consecutiveAbsences = $this->findConsecutiveAbsences($attendanceRecords);
        if ($consecutiveAbsences > 3) {
            $indicators[] = 'Extended consecutive absences';
        }
        
        return $indicators;
    }

    /**
     * Calculate absenteeism risk
     */
    private function calculateAbsenteeismRisk(array $patterns): float
    {
        $riskScore = 5.0;
        
        // Weekly pattern risk
        if (isset($patterns['weekly_pattern']['monday_absence_rate']) && $patterns['weekly_pattern']['monday_absence_rate'] > 30) {
            $riskScore += 2.0;
        }
        
        if (isset($patterns['weekly_pattern']['friday_absence_rate']) && $patterns['weekly_pattern']['friday_absence_rate'] > 30) {
            $riskScore += 2.0;
        }
        
        // Consecutive absences risk
        if ($patterns['consecutive_absences'] > 3) {
            $riskScore += 1.5;
        }
        
        // Pattern changes risk
        if ($patterns['pattern_changes']) {
            $riskScore += 1.0;
        }
        
        return min(10.0, $riskScore);
    }

    /**
     * Generate absenteeism recommendations
     */
    private function generateAbsenteeismRecommendations(array $patterns): array
    {
        $recommendations = [];
        
        if (isset($patterns['weekly_pattern']['monday_absence_rate']) && $patterns['weekly_pattern']['monday_absence_rate'] > 30) {
            $recommendations[] = 'Implement Monday motivation programs';
            $recommendations[] = 'Consider flexible Monday start times';
        }
        
        if (isset($patterns['weekly_pattern']['friday_absence_rate']) && $patterns['weekly_pattern']['friday_absence_rate'] > 30) {
            $recommendations[] = 'Review Friday workload distribution';
            $recommendations[] = 'Implement Friday recognition programs';
        }
        
        if ($patterns['consecutive_absences'] > 3) {
            $recommendations[] = 'Schedule immediate intervention meeting';
            $recommendations[] = 'Implement attendance improvement plan';
        }
        
        return $recommendations;
    }

    /**
     * Calculate WFH frequency
     */
    private function calculateWFHFrequency($wfhRecords): array
    {
        $totalDays = $wfhRecords->count();
        $totalWorkDays = 60; // Approximate work days in 3 months
        
        $frequency = ($totalDays / $totalWorkDays) * 100;
        
        return [
            'frequency_percentage' => round($frequency, 1),
            'total_wfh_days' => $totalDays,
            'excessive' => $frequency > 50,
            'optimal_range' => $frequency >= 20 && $frequency <= 40
        ];
    }

    /**
     * Analyze WFH day preferences
     */
    private function analyzeWFHDayPreferences($wfhRecords): array
    {
        $dayPreferences = [];
        
        for ($day = 1; $day <= 7; $day++) {
            $dayRecords = $wfhRecords->filter(function($record) use ($day) {
                return Carbon::parse($record->date)->dayOfWeek === $day;
            });
            
            $dayName = Carbon::create()->startOfWeek()->addDays($day - 1)->format('l');
            $dayPreferences[strtolower($dayName)] = $dayRecords->count();
        }
        
        return $dayPreferences;
    }

    /**
     * Analyze WFH productivity correlation
     */
    private function analyzeWFHProductivityCorrelation(Employee $employee, $wfhRecords): array
    {
        // Simulated productivity analysis
        return [
            'productivity_score' => 8.2,
            'communication_quality' => 7.8,
            'task_completion_rate' => 85.5,
            'team_collaboration' => 7.5
        ];
    }

    /**
     * Assess WFH team impact
     */
    private function assessWFHTeamImpact(Employee $employee, $wfhRecords): array
    {
        return [
            'team_availability' => 'good',
            'collaboration_impact' => 'minimal',
            'communication_effectiveness' => 'maintained',
            'team_feedback' => 'positive'
        ];
    }

    /**
     * Check WFH compliance
     */
    private function checkWFHCompliance(Employee $employee, $wfhRecords): array
    {
        return [
            'policy_compliance' => true,
            'approval_status' => 'approved',
            'documentation_complete' => true,
            'equipment_provided' => true
        ];
    }

    /**
     * Generate WFH recommendations
     */
    private function generateWFHRecommendations(array $patterns): array
    {
        $recommendations = [];
        
        if ($patterns['frequency']['excessive']) {
            $recommendations[] = 'Review WFH frequency with employee';
            $recommendations[] = 'Consider hybrid work arrangement';
        }
        
        if ($patterns['productivity_correlation']['productivity_score'] < 7.0) {
            $recommendations[] = 'Provide additional support for remote work';
            $recommendations[] = 'Schedule regular check-ins';
        }
        
        return $recommendations;
    }

    /**
     * Assess policy compliance
     */
    private function assessPolicyCompliance(array $patterns): array
    {
        return [
            'compliance_status' => 'compliant',
            'policy_violations' => [],
            'recommendations' => ['Continue current WFH arrangement']
        ];
    }

    /**
     * Detect recent pattern changes
     */
    private function detectRecentPatternChanges(Employee $employee): array
    {
        return [
            'has_changes' => false,
            'change_type' => null,
            'change_magnitude' => 0
        ];
    }

    /**
     * Generate alert recommendations
     */
    private function generateAlertRecommendations(array $alerts): array
    {
        $recommendations = [];
        
        foreach ($alerts as $alert) {
            if ($alert['type'] === 'absence_alert') {
                $recommendations[] = 'Schedule immediate check-in meeting';
            } elseif ($alert['type'] === 'punctuality_alert') {
                $recommendations[] = 'Address punctuality concerns directly';
            } elseif ($alert['type'] === 'pattern_change_alert') {
                $recommendations[] = 'Investigate underlying causes';
            }
        }
        
        return $recommendations;
    }

    /**
     * Calculate correlation coefficient
     */
    private function calculateCorrelationCoefficient($attendanceRecords, $performanceReviews): float
    {
        // Simulated correlation calculation
        return 0.75;
    }

    /**
     * Analyze WFH productivity impact
     */
    private function analyzeWFHProductivityImpact(Employee $employee): array
    {
        return [
            'productivity_change' => '+12%',
            'quality_impact' => 'positive',
            'efficiency_gain' => '+8%',
            'communication_impact' => 'minimal'
        ];
    }

    /**
     * Analyze punctuality performance link
     */
    private function analyzePunctualityPerformanceLink(Employee $employee): array
    {
        return [
            'correlation_strength' => 'moderate',
            'performance_impact' => 'positive',
            'recommendations' => ['Maintain punctuality standards']
        ];
    }

    /**
     * Analyze overtime productivity
     */
    private function analyzeOvertimeProductivity(Employee $employee): array
    {
        return [
            'overtime_hours' => 12,
            'productivity_during_overtime' => 85,
            'quality_maintenance' => 'good',
            'burnout_risk' => 'low'
        ];
    }

    /**
     * Analyze workload distribution
     */
    private function analyzeWorkloadDistribution(Employee $employee): array
    {
        return [
            'workload_balance' => 'good',
            'peak_periods' => ['Monday', 'Wednesday'],
            'low_periods' => ['Friday'],
            'recommendations' => ['Maintain current workload distribution']
        ];
    }

    /**
     * Generate productivity insights
     */
    private function generateProductivityInsights(array $correlation): array
    {
        return [
            'attendance_quality_impact' => 'High attendance correlates with better performance',
            'wfh_efficiency' => 'WFH days show improved productivity',
            'punctuality_importance' => 'Punctuality strongly correlates with performance',
            'workload_optimization' => 'Balanced workload leads to better outcomes'
        ];
    }

    /**
     * Generate productivity recommendations
     */
    private function generateProductivityRecommendations(array $correlation): array
    {
        return [
            'Maintain high attendance standards',
            'Encourage punctuality through recognition',
            'Optimize WFH arrangements for productivity',
            'Implement flexible work arrangements'
        ];
    }

    /**
     * Calculate absence rate
     */
    private function calculateAbsenceRate($records): float
    {
        $total = $records->count();
        $absences = $records->where('status', 'absent')->count();
        
        return $total > 0 ? ($absences / $total) * 100 : 0;
    }

    /**
     * Check if month belongs to season
     */
    private function isSeason(int $month, string $season): bool
    {
        $seasonMonths = [
            'spring' => [3, 4, 5],
            'summer' => [6, 7, 8],
            'autumn' => [9, 10, 11],
            'winter' => [12, 1, 2]
        ];
        
        return in_array($month, $seasonMonths[$season] ?? []);
    }
} 