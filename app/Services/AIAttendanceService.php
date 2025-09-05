<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\AttendanceRecord;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AIAttendanceService
{
    /**
     * Detect attendance patterns for an employee
     */
    public function detectAttendancePatterns(Employee $employee): array
    {
        try {
            $attendanceRecords = AttendanceRecord::where('employee_id', $employee->id)
                ->where('date', '>=', now()->subMonths(3))
                ->orderBy('date')
                ->get();
            
            return [
                'punctuality_pattern' => $this->analyzePunctualityPattern($attendanceRecords),
                'absenteeism_pattern' => $this->analyzeAbsenteeismPattern($attendanceRecords),
                'overtime_pattern' => $this->analyzeOvertimePattern($attendanceRecords),
                'weekly_pattern' => $this->analyzeWeeklyPattern($attendanceRecords),
                'monthly_trend' => $this->analyzeMonthlyTrend($attendanceRecords),
                'seasonal_pattern' => $this->analyzeSeasonalPattern($attendanceRecords),
                'anomalies' => $this->detectAnomalies($attendanceRecords)
            ];
        } catch (\Exception $e) {
            Log::error('AI Attendance Analysis failed: ' . $e->getMessage());
            return ['error' => 'Attendance analysis failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Flag absenteeism risk for an employee
     */
    public function flagAbsenteeismRisk(Employee $employee): array
    {
        $riskFactors = [];
        $riskScore = 0;
        
        // Calculate recent attendance metrics
        $recentAttendance = AttendanceRecord::where('employee_id', $employee->id)
            ->where('date', '>=', now()->subMonth())
            ->get();
        
        $totalDays = $recentAttendance->count();
        $absentDays = $recentAttendance->where('status', 'absent')->count();
        $lateDays = $recentAttendance->where('status', 'late')->count();
        $earlyDepartures = $recentAttendance->where('status', 'early_departure')->count();
        
        // Absence rate risk
        $absenceRate = $totalDays > 0 ? ($absentDays / $totalDays) * 100 : 0;
        if ($absenceRate > 10) {
            $riskFactors[] = 'High absence rate (' . round($absenceRate, 1) . '%)';
            $riskScore += min(3, $absenceRate / 5);
        }
        
        // Late arrival risk
        $lateRate = $totalDays > 0 ? ($lateDays / $totalDays) * 100 : 0;
        if ($lateRate > 20) {
            $riskFactors[] = 'Frequent late arrivals (' . round($lateRate, 1) . '%)';
            $riskScore += min(2, $lateRate / 10);
        }
        
        // Early departure risk
        $earlyDepartureRate = $totalDays > 0 ? ($earlyDepartures / $totalDays) * 100 : 0;
        if ($earlyDepartureRate > 15) {
            $riskFactors[] = 'Frequent early departures (' . round($earlyDepartureRate, 1) . '%)';
            $riskScore += min(2, $earlyDepartureRate / 10);
        }
        
        // Pattern analysis
        $patterns = $this->detectAttendancePatterns($employee);
        if (isset($patterns['absenteeism_pattern']['trend']) && $patterns['absenteeism_pattern']['trend'] === 'increasing') {
            $riskFactors[] = 'Increasing absenteeism trend';
            $riskScore += 1.5;
        }
        
        // Monday/Friday pattern
        if (isset($patterns['weekly_pattern']['monday_absence_rate']) && $patterns['weekly_pattern']['monday_absence_rate'] > 30) {
            $riskFactors[] = 'High Monday absence rate';
            $riskScore += 1;
        }
        
        if (isset($patterns['weekly_pattern']['friday_absence_rate']) && $patterns['weekly_pattern']['friday_absence_rate'] > 30) {
            $riskFactors[] = 'High Friday absence rate';
            $riskScore += 1;
        }
        
        return [
            'risk_score' => min(10, $riskScore),
            'risk_level' => $this->getRiskLevel($riskScore),
            'risk_factors' => $riskFactors,
            'metrics' => [
                'absence_rate' => $absenceRate,
                'late_rate' => $lateRate,
                'early_departure_rate' => $earlyDepartureRate,
                'total_working_days' => $totalDays,
                'absent_days' => $absentDays,
                'late_days' => $lateDays,
                'early_departures' => $earlyDepartures
            ],
            'recommendations' => $this->generateAttendanceRecommendations($riskFactors)
        ];
    }
    
    /**
     * Generate comprehensive attendance reports
     */
    public function generateAttendanceReports(): array
    {
        $employees = Employee::with(['attendanceRecords' => function($query) {
            $query->where('date', '>=', now()->subMonth());
        }])->get();
        
        $departmentStats = [];
        $overallStats = [
            'total_employees' => $employees->count(),
            'average_attendance_rate' => 0,
            'employees_at_risk' => 0,
            'departments_analyzed' => 0
        ];
        
        foreach ($employees as $employee) {
            $attendanceRate = $employee->attendance_rate ?? 100;
            $overallStats['average_attendance_rate'] += $attendanceRate;
            
            if ($attendanceRate < 90) {
                $overallStats['employees_at_risk']++;
            }
            
            $department = $employee->department;
            if ($department) {
                if (!isset($departmentStats[$department->name])) {
                    $departmentStats[$department->name] = [
                        'total_employees' => 0,
                        'average_attendance' => 0,
                        'employees_at_risk' => 0,
                        'total_attendance_rate' => 0
                    ];
                }
                
                $departmentStats[$department->name]['total_employees']++;
                $departmentStats[$department->name]['total_attendance_rate'] += $attendanceRate;
                
                if ($attendanceRate < 90) {
                    $departmentStats[$department->name]['employees_at_risk']++;
                }
            }
        }
        
        // Calculate averages
        if ($overallStats['total_employees'] > 0) {
            $overallStats['average_attendance_rate'] /= $overallStats['total_employees'];
        }
        
        foreach ($departmentStats as $dept => $stats) {
            if ($stats['total_employees'] > 0) {
                $departmentStats[$dept]['average_attendance'] = $stats['total_attendance_rate'] / $stats['total_employees'];
            }
        }
        
        $overallStats['departments_analyzed'] = count($departmentStats);
        
        return [
            'overall_statistics' => $overallStats,
            'department_statistics' => $departmentStats,
            'risk_analysis' => $this->generateRiskAnalysis($employees),
            'trends' => $this->generateAttendanceTrends($employees),
            'recommendations' => $this->generateOrganizationalRecommendations($overallStats, $departmentStats)
        ];
    }
    
    /**
     * Analyze punctuality pattern
     */
    private function analyzePunctualityPattern($attendanceRecords): array
    {
        $totalDays = $attendanceRecords->count();
        $onTimeDays = $attendanceRecords->where('candidate_on_time', true)->count();
        $lateDays = $attendanceRecords->where('status', 'late')->count();
        
        $onTimeRate = $totalDays > 0 ? ($onTimeDays / $totalDays) * 100 : 100;
        $lateRate = $totalDays > 0 ? ($lateDays / $totalDays) * 100 : 0;
        
        return [
            'on_time_rate' => round($onTimeRate, 1),
            'late_rate' => round($lateRate, 1),
            'average_late_minutes' => $attendanceRecords->where('late_minutes', '>', 0)->avg('late_minutes') ?? 0,
            'punctuality_trend' => $this->calculateTrend($attendanceRecords, 'candidate_on_time')
        ];
    }
    
    /**
     * Analyze absenteeism pattern
     */
    private function analyzeAbsenteeismPattern($attendanceRecords): array
    {
        $totalDays = $attendanceRecords->count();
        $absentDays = $attendanceRecords->where('status', 'absent')->count();
        $absentRate = $totalDays > 0 ? ($absentDays / $totalDays) * 100 : 0;
        
        return [
            'absent_rate' => round($absentRate, 1),
            'absent_days' => $absentDays,
            'total_days' => $totalDays,
            'trend' => $this->calculateTrend($attendanceRecords, 'status', 'absent'),
            'pattern' => $this->identifyAbsencePattern($attendanceRecords)
        ];
    }
    
    /**
     * Analyze overtime pattern
     */
    private function analyzeOvertimePattern($attendanceRecords): array
    {
        $overtimeDays = $attendanceRecords->where('overtime_hours', '>', 0);
        
        return [
            'overtime_days' => $overtimeDays->count(),
            'average_overtime_hours' => $overtimeDays->avg('overtime_hours') ?? 0,
            'total_overtime_hours' => $overtimeDays->sum('overtime_hours'),
            'overtime_frequency' => $attendanceRecords->count() > 0 ? ($overtimeDays->count() / $attendanceRecords->count()) * 100 : 0
        ];
    }
    
    /**
     * Analyze weekly pattern
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
     * Analyze monthly trend
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
     * Analyze seasonal pattern
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
     * Detect attendance anomalies
     */
    private function detectAnomalies($attendanceRecords): array
    {
        $anomalies = [];
        
        // Unusual absence patterns
        $consecutiveAbsences = $this->findConsecutiveAbsences($attendanceRecords);
        if ($consecutiveAbsences > 3) {
            $anomalies[] = "Extended consecutive absence ({$consecutiveAbsences} days)";
        }
        
        // Unusual late patterns
        $consecutiveLates = $this->findConsecutiveLates($attendanceRecords);
        if ($consecutiveLates > 5) {
            $anomalies[] = "Extended consecutive late arrivals ({$consecutiveLates} days)";
        }
        
        // Sudden pattern changes
        $patternChange = $this->detectPatternChange($attendanceRecords);
        if ($patternChange) {
            $anomalies[] = "Sudden change in attendance pattern";
        }
        
        return $anomalies;
    }
    
    /**
     * Calculate trend for a specific field
     */
    private function calculateTrend($records, $field, $value = null): string
    {
        if ($records->count() < 2) return 'insufficient_data';
        
        $recentRecords = $records->take(ceil($records->count() / 2));
        $olderRecords = $records->skip(ceil($records->count() / 2));
        
        $recentRate = $this->calculateFieldRate($recentRecords, $field, $value);
        $olderRate = $this->calculateFieldRate($olderRecords, $field, $value);
        
        if ($recentRate > $olderRate + 5) return 'improving';
        if ($recentRate < $olderRate - 5) return 'declining';
        return 'stable';
    }
    
    /**
     * Calculate rate for a specific field
     */
    private function calculateFieldRate($records, $field, $value = null): float
    {
        $total = $records->count();
        if ($total === 0) return 0;
        
        if ($value) {
            $matching = $records->where($field, $value)->count();
        } else {
            $matching = $records->where($field, true)->count();
        }
        
        return ($matching / $total) * 100;
    }
    
    /**
     * Identify absence pattern
     */
    private function identifyAbsencePattern($attendanceRecords): string
    {
        $absentRecords = $attendanceRecords->where('status', 'absent');
        
        if ($absentRecords->count() === 0) return 'no_absences';
        
        // Check for Monday/Friday pattern
        $mondayAbsences = $absentRecords->filter(function($record) {
            return Carbon::parse($record->date)->dayOfWeek === 1;
        })->count();
        
        $fridayAbsences = $absentRecords->filter(function($record) {
            return Carbon::parse($record->date)->dayOfWeek === 5;
        })->count();
        
        if ($mondayAbsences > $absentRecords->count() * 0.4) return 'monday_pattern';
        if ($fridayAbsences > $absentRecords->count() * 0.4) return 'friday_pattern';
        
        return 'random';
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
     * Find consecutive late arrivals
     */
    private function findConsecutiveLates($attendanceRecords): int
    {
        $maxConsecutive = 0;
        $currentConsecutive = 0;
        
        foreach ($attendanceRecords->sortBy('date') as $record) {
            if ($record->status === 'late') {
                $currentConsecutive++;
                $maxConsecutive = max($maxConsecutive, $currentConsecutive);
            } else {
                $currentConsecutive = 0;
            }
        }
        
        return $maxConsecutive;
    }
    
    /**
     * Detect pattern change
     */
    private function detectPatternChange($attendanceRecords): bool
    {
        if ($attendanceRecords->count() < 10) return false;
        
        $recentRecords = $attendanceRecords->take(5);
        $olderRecords = $attendanceRecords->skip(5)->take(5);
        
        $recentAbsenceRate = $this->calculateFieldRate($recentRecords, 'status', 'absent');
        $olderAbsenceRate = $this->calculateFieldRate($olderRecords, 'status', 'absent');
        
        return abs($recentAbsenceRate - $olderAbsenceRate) > 20;
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
     * Generate attendance recommendations
     */
    private function generateAttendanceRecommendations(array $riskFactors): array
    {
        $recommendations = [];
        
        foreach ($riskFactors as $factor) {
            if (str_contains($factor, 'absence')) {
                $recommendations[] = 'Schedule one-on-one meeting to discuss attendance';
                $recommendations[] = 'Identify and address underlying causes';
                $recommendations[] = 'Consider flexible work arrangements';
            }
            
            if (str_contains($factor, 'late')) {
                $recommendations[] = 'Review and adjust work schedule if needed';
                $recommendations[] = 'Provide punctuality training';
                $recommendations[] = 'Set clear arrival time expectations';
            }
            
            if (str_contains($factor, 'Monday')) {
                $recommendations[] = 'Implement Monday motivation programs';
                $recommendations[] = 'Consider Monday team meetings';
            }
            
            if (str_contains($factor, 'Friday')) {
                $recommendations[] = 'Review Friday workload distribution';
                $recommendations[] = 'Implement Friday recognition programs';
            }
        }
        
        return array_unique($recommendations);
    }
    
    /**
     * Generate risk analysis
     */
    private function generateRiskAnalysis($employees): array
    {
        $riskLevels = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
        
        foreach ($employees as $employee) {
            $riskAnalysis = $this->flagAbsenteeismRisk($employee);
            $riskLevel = $riskAnalysis['risk_level'];
            $riskLevels[$riskLevel]++;
        }
        
        return $riskLevels;
    }
    
    /**
     * Generate attendance trends
     */
    private function generateAttendanceTrends($employees): array
    {
        return [
            'overall_trend' => 'stable',
            'department_trends' => [],
            'seasonal_patterns' => []
        ];
    }
    
    /**
     * Generate organizational recommendations
     */
    private function generateOrganizationalRecommendations(array $overallStats, array $departmentStats): array
    {
        $recommendations = [];
        
        if ($overallStats['average_attendance_rate'] < 90) {
            $recommendations[] = 'Implement organization-wide attendance improvement program';
        }
        
        if ($overallStats['employees_at_risk'] > $overallStats['total_employees'] * 0.1) {
            $recommendations[] = 'Conduct department-level attendance reviews';
        }
        
        foreach ($departmentStats as $dept => $stats) {
            if ($stats['average_attendance'] < 85) {
                $recommendations[] = "Focus on attendance improvement in {$dept} department";
            }
        }
        
        return $recommendations;
    }
} 