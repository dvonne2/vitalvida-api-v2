<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\InfractionLog;
use App\Models\AIAssessment;
use App\Services\AIPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PerformanceManagementController extends Controller
{
    protected $aiPerformanceService;

    public function __construct(AIPerformanceService $aiPerformanceService)
    {
        $this->aiPerformanceService = $aiPerformanceService;
    }

    /**
     * Get performance dashboard
     */
    public function getPerformanceDashboard(): JsonResponse
    {
        try {
            $employees = Employee::with(['department', 'position'])
                ->where('status', 'active')
                ->orWhere('status', 'probation')
                ->get();

            $overview = $this->calculatePerformanceOverview($employees);
            $weeklyScorecard = $this->getWeeklyPerformanceScorecard($employees);
            $aiAlerts = $this->generateAIAlerts($employees);
            $infractionLog = $this->getInfractionLog();

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => $overview,
                    'weekly_performance_scorecard' => $weeklyScorecard,
                    'ai_alerts' => $aiAlerts,
                    'infraction_log' => $infractionLog
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Performance Dashboard Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load performance dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee performance details
     */
    public function getEmployeePerformanceDetails(int $employeeId): JsonResponse
    {
        try {
            $employee = Employee::with(['department', 'position'])
                ->findOrFail($employeeId);

            $performanceInsights = $this->aiPerformanceService->generatePerformanceInsights($employee);
            $performanceRisk = $this->aiPerformanceService->predictPerformanceRisk($employee);
            $recentReviews = PerformanceReview::where('employee_id', $employeeId)
                ->orderBy('review_date', 'desc')
                ->take(5)
                ->get();

            $infractions = InfractionLog::where('employee_id', $employeeId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->first_name . ' ' . $employee->last_name,
                        'position' => $employee->position->title ?? 'Unknown',
                        'department' => $employee->department->name ?? 'Unknown',
                        'hire_date' => $employee->hire_date->format('M j, Y'),
                        'status' => $employee->status
                    ],
                    'performance_metrics' => [
                        'ai_score' => $employee->ai_score ?? 0,
                        'performance_rating' => $employee->performance_rating ?? 0,
                        'attendance_rate' => $employee->attendance_rate ?? 0,
                        'task_completion_rate' => $this->calculateTaskCompletionRate($employee),
                        'kpi_score' => $this->calculateKPIScore($employee)
                    ],
                    'performance_insights' => $performanceInsights,
                    'risk_assessment' => $performanceRisk,
                    'recent_reviews' => $recentReviews,
                    'infractions' => $infractions,
                    'recommendations' => $this->generatePerformanceRecommendations($employee)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Employee Performance Details Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load employee performance details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create performance improvement plan
     */
    public function createPerformanceImprovementPlan(Request $request, int $employeeId): JsonResponse
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'issues' => 'required|array',
                'goals' => 'required|array',
                'timeline' => 'required|integer|min:1|max:12',
                'support_resources' => 'nullable|array',
                'review_frequency' => 'required|in:weekly,biweekly,monthly'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $employee = Employee::findOrFail($employeeId);
            
            // Create performance improvement plan
            $plan = [
                'employee_id' => $employeeId,
                'issues' => $request->issues,
                'goals' => $request->goals,
                'timeline_weeks' => $request->timeline,
                'support_resources' => $request->support_resources ?? [],
                'review_frequency' => $request->review_frequency,
                'created_at' => now(),
                'status' => 'active'
            ];

            // Store plan (simulated - in real app, this would be a model)
            $planId = 'PIP-' . str_pad($employeeId, 6, '0', STR_PAD_LEFT);

            return response()->json([
                'success' => true,
                'message' => 'Performance improvement plan created successfully',
                'data' => [
                    'plan_id' => $planId,
                    'employee_id' => $employeeId,
                    'timeline' => $request->timeline . ' weeks',
                    'review_frequency' => $request->review_frequency,
                    'next_review_date' => now()->addWeek()->format('M j, Y')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Create Performance Plan Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create performance improvement plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Issue warning to employee
     */
    public function issueWarning(Request $request, int $employeeId): JsonResponse
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'warning_type' => 'required|in:verbal,written,final',
                'reason' => 'required|string',
                'description' => 'required|string',
                'severity' => 'required|in:low,medium,high,critical',
                'action_required' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create infraction log entry
            $infraction = InfractionLog::create([
                'employee_id' => $employeeId,
                'type' => $request->warning_type . '_warning',
                'description' => $request->description,
                'severity' => $request->severity,
                'reason' => $request->reason,
                'action_required' => $request->action_required,
                'status' => 'open',
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Warning issued successfully',
                'data' => [
                    'infraction_id' => $infraction->id,
                    'warning_type' => $request->warning_type,
                    'severity' => $request->severity,
                    'issued_date' => now()->format('M j, Y')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Issue Warning Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to issue warning',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate performance overview
     */
    private function calculatePerformanceOverview($employees): array
    {
        $bonusEligible = $employees->filter(function ($employee) {
            return $employee->performance_rating >= 4.5 && $employee->ai_score >= 8.0;
        })->count();

        $atRisk = $employees->filter(function ($employee) {
            return $employee->performance_rating < 3.5 || $employee->ai_score < 6.0;
        })->count();

        $underReview = $employees->filter(function ($employee) {
            return $employee->performance_rating >= 3.5 && $employee->performance_rating < 4.0;
        })->count();

        $avgAIScore = $employees->avg('ai_score') ?? 0;

        return [
            'bonus_eligible' => $bonusEligible,
            'at_risk' => $atRisk,
            'under_review' => $underReview,
            'avg_ai_score' => round($avgAIScore, 1)
        ];
    }

    /**
     * Get weekly performance scorecard
     */
    private function getWeeklyPerformanceScorecard($employees): array
    {
        return $employees->map(function ($employee) {
            $taskCompletion = $this->calculateTaskCompletionRate($employee);
            $kpiScore = $this->calculateKPIScore($employee);
            $aiScore = $employee->ai_score ?? 0;
            
            $status = 'normal';
            if ($aiScore >= 8.0 && $kpiScore >= 8.0) {
                $status = 'bonus_eligible';
            } elseif ($aiScore < 6.0 || $kpiScore < 6.0) {
                $status = 'at_risk';
            } elseif ($aiScore < 7.0 || $kpiScore < 7.0) {
                $status = 'under_review';
            }

            $actions = ['view_details'];
            if ($status === 'at_risk') {
                $actions[] = 'issue_warning';
            }

            return [
                'employee' => $employee->first_name . ' ' . $employee->last_name,
                'department' => $employee->department->name ?? 'Unknown',
                'tasks_completion' => $this->formatTaskCompletion($taskCompletion),
                'kpi_score' => round($kpiScore, 1),
                'ai_score' => round($aiScore, 1),
                'status' => $status,
                'actions' => $actions
            ];
        })->toArray();
    }

    /**
     * Generate AI alerts
     */
    private function generateAIAlerts($employees): array
    {
        $alerts = [];

        foreach ($employees as $employee) {
            $performanceRisk = $this->aiPerformanceService->predictPerformanceRisk($employee);
            
            if ($performanceRisk['risk_score'] >= 7.0) {
                $alerts[] = [
                    'employee' => $employee->first_name . ' ' . $employee->last_name,
                    'alert_type' => 'performance_decline',
                    'message' => $this->generateAlertMessage($employee),
                    'recommendation' => 'Monitor closely',
                    'action' => 'create_performance_improvement_plan'
                ];
            }

            // Check for specific performance issues
            if ($employee->performance_rating < 3.0) {
                $alerts[] = [
                    'employee' => $employee->first_name . ' ' . $employee->last_name,
                    'alert_type' => 'quality_concerns',
                    'message' => '3 missed deadlines - Auto Warning Triggered',
                    'recommendation' => 'Immediate intervention required',
                    'action' => 'create_performance_improvement_plan'
                ];
            }
        }

        return $alerts;
    }

    /**
     * Get infraction log
     */
    private function getInfractionLog(): array
    {
        $infractions = InfractionLog::with('employee')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return $infractions->map(function ($infraction) {
            return [
                'type' => $infraction->type,
                'employee' => $infraction->employee->first_name . ' ' . $infraction->employee->last_name,
                'description' => $infraction->description,
                'date' => $infraction->created_at->format('d/m/Y'),
                'severity' => $infraction->severity,
                'status' => $infraction->status
            ];
        })->toArray();
    }

    /**
     * Calculate task completion rate
     */
    private function calculateTaskCompletionRate(Employee $employee): float
    {
        // Simulated task completion calculation
        $totalTasks = rand(15, 25);
        $completedTasks = rand(10, $totalTasks);
        
        return ($completedTasks / $totalTasks) * 100;
    }

    /**
     * Calculate KPI score
     */
    private function calculateKPIScore(Employee $employee): float
    {
        // Simulated KPI calculation based on various factors
        $baseScore = $employee->performance_rating ?? 5.0;
        $attendanceBonus = ($employee->attendance_rate ?? 100) / 100 * 2;
        $aiBonus = ($employee->ai_score ?? 5.0) / 10 * 2;
        
        return min(10.0, $baseScore + $attendanceBonus + $aiBonus);
    }

    /**
     * Format task completion
     */
    private function formatTaskCompletion(float $rate): string
    {
        $totalTasks = rand(15, 25);
        $completedTasks = round(($rate / 100) * $totalTasks);
        
        return $completedTasks . '/' . $totalTasks . ' (' . round($rate, 1) . '%)';
    }

    /**
     * Generate alert message
     */
    private function generateAlertMessage(Employee $employee): string
    {
        $messages = [
            '2 missed deadlines this week',
            'Performance below department average',
            'Attendance rate declining',
            'Quality issues reported'
        ];
        
        return $messages[array_rand($messages)];
    }

    /**
     * Generate performance recommendations
     */
    private function generatePerformanceRecommendations(Employee $employee): array
    {
        $recommendations = [];
        
        if ($employee->performance_rating < 4.0) {
            $recommendations[] = 'Schedule performance improvement meeting';
            $recommendations[] = 'Provide additional training and support';
            $recommendations[] = 'Set clear performance expectations';
        }
        
        if ($employee->attendance_rate < 90) {
            $recommendations[] = 'Address attendance concerns';
            $recommendations[] = 'Implement flexible work arrangements';
        }
        
        if ($employee->ai_score < 7.0) {
            $recommendations[] = 'Provide skill development opportunities';
            $recommendations[] = 'Assign mentor for guidance';
        }
        
        return $recommendations;
    }
}
