<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingProgress;
use App\Models\AIAssessment;
use App\Services\AIPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TrainingManagementController extends Controller
{
    protected $aiPerformanceService;

    public function __construct(AIPerformanceService $aiPerformanceService)
    {
        $this->aiPerformanceService = $aiPerformanceService;
    }

    /**
     * Get training dashboard with onboarding overview
     */
    public function getTrainingDashboard(): JsonResponse
    {
        try {
            // Get employees in probation/training
            $employeesInTraining = Employee::with(['department', 'position'])
                ->where('status', 'probation')
                ->orWhere('status', 'training')
                ->get();

            $overview = $this->calculateTrainingOverview($employeesInTraining);
            $trainingEmployees = $this->formatTrainingEmployees($employeesInTraining);

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => $overview,
                    'employees_in_training' => $trainingEmployees
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Training Dashboard Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load training dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee training details
     */
    public function getEmployeeTrainingDetails(int $employeeId): JsonResponse
    {
        try {
            $employee = Employee::with(['department', 'position', 'trainingProgress.training'])
                ->findOrFail($employeeId);

            $trainingProgress = $employee->trainingProgress;
            $aiInsights = $this->aiPerformanceService->generatePerformanceInsights($employee);
            $trainingModules = $this->getTrainingModules($employee);

            return response()->json([
                'success' => true,
                'data' => [
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->first_name . ' ' . $employee->last_name,
                        'position' => $employee->position->title ?? 'Unknown',
                        'department' => $employee->department->name ?? 'Unknown',
                        'hire_date' => $employee->hire_date->format('M j, Y'),
                        'probation_end_date' => $employee->probation_end_date?->format('M j, Y'),
                        'status' => $employee->status
                    ],
                    'training_progress' => $trainingProgress,
                    'training_modules' => $trainingModules,
                    'ai_insights' => $aiInsights,
                    'performance_metrics' => $this->getPerformanceMetrics($employee),
                    'recommendations' => $this->generateTrainingRecommendations($employee)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Employee Training Details Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load employee training details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update training progress
     */
    public function updateTrainingProgress(Request $request, int $employeeId): JsonResponse
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'training_id' => 'required|exists:trainings,id',
                'progress_percentage' => 'required|numeric|min:0|max:100',
                'status' => 'required|in:not_started,in_progress,completed,failed',
                'notes' => 'nullable|string',
                'completion_date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $trainingProgress = TrainingProgress::updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'training_id' => $request->training_id
                ],
                [
                    'progress_percentage' => $request->progress_percentage,
                    'status' => $request->status,
                    'notes' => $request->notes,
                    'completion_date' => $request->completion_date ? now() : null,
                    'updated_at' => now()
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Training progress updated successfully',
                'data' => [
                    'training_progress_id' => $trainingProgress->id,
                    'progress_percentage' => $trainingProgress->progress_percentage,
                    'status' => $trainingProgress->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Update Training Progress Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update training progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Grant system access
     */
    public function grantSystemAccess(int $employeeId): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($employeeId);
            
            // Update employee system access status
            $employee->update([
                'system_access_granted' => true,
                'system_access_date' => now()
            ]);

            // Create training progress record for system access
            TrainingProgress::create([
                'employee_id' => $employeeId,
                'training_id' => $this->getSystemAccessTrainingId(),
                'progress_percentage' => 100,
                'status' => 'completed',
                'completion_date' => now(),
                'notes' => 'System access granted automatically'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'System access granted successfully',
                'data' => [
                    'employee_id' => $employeeId,
                    'system_access_granted' => true,
                    'granted_date' => now()->format('M j, Y')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Grant System Access Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to grant system access',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Issue onboarding certificate
     */
    public function issueOnboardingCertificate(int $employeeId): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($employeeId);
            
            // Check if employee meets certification requirements
            $trainingProgress = TrainingProgress::where('employee_id', $employeeId)
                ->where('status', 'completed')
                ->count();
            
            $requiredTrainings = Training::where('required_for_onboarding', true)->count();
            
            if ($trainingProgress < $requiredTrainings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee has not completed all required training modules',
                    'data' => [
                        'completed_trainings' => $trainingProgress,
                        'required_trainings' => $requiredTrainings
                    ]
                ], 400);
            }

            // Issue certificate
            $employee->update([
                'onboarding_certificate_issued' => true,
                'onboarding_certificate_date' => now(),
                'status' => 'active'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding certificate issued successfully',
                'data' => [
                    'employee_id' => $employeeId,
                    'certificate_issued' => true,
                    'issue_date' => now()->format('M j, Y'),
                    'certificate_number' => 'ONB-' . str_pad($employeeId, 6, '0', STR_PAD_LEFT)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Issue Onboarding Certificate Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to issue onboarding certificate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate training overview statistics
     */
    private function calculateTrainingOverview($employeesInTraining): array
    {
        $totalEmployees = $employeesInTraining->count();
        $activeProbations = $employeesInTraining->where('status', 'probation')->count();
        
        $avgAIScore = $employeesInTraining->avg('ai_score') ?? 0;
        
        $completedTrainings = TrainingProgress::whereIn('employee_id', $employeesInTraining->pluck('id'))
            ->where('status', 'completed')
            ->count();
        $totalRequiredTrainings = Training::where('required_for_onboarding', true)->count() * $totalEmployees;
        $trainingComplete = $totalRequiredTrainings > 0 ? round(($completedTrainings / $totalRequiredTrainings) * 100, 1) : 0;
        
        $atRisk = $employeesInTraining->filter(function ($employee) {
            return $employee->ai_score < 6.0 || $employee->performance_rating < 3.0;
        })->count();

        return [
            'active_probations' => $activeProbations,
            'avg_ai_score' => round($avgAIScore, 1),
            'training_complete' => $trainingComplete . '%',
            'at_risk' => $atRisk
        ];
    }

    /**
     * Format training employees data
     */
    private function formatTrainingEmployees($employees): array
    {
        return $employees->map(function ($employee) {
            $trainingModules = $this->getTrainingModules($employee);
            $overallProgress = $this->calculateOverallProgress($employee);
            $aiInsights = $this->generateAIInsights($employee);
            $actions = $this->getAvailableActions($employee);

            return [
                'id' => $employee->id,
                'name' => $employee->first_name . ' ' . $employee->last_name,
                'position' => $employee->position->title ?? 'Unknown',
                'department' => $employee->department->name ?? 'Unknown',
                'ai_score' => $employee->ai_score ? $employee->ai_score . '/10' : 'Not assessed',
                'week' => $this->calculateTrainingWeek($employee),
                'training_modules' => $trainingModules,
                'overall_progress' => $overallProgress . '%',
                'ai_insights' => $aiInsights,
                'actions' => $actions
            ];
        })->toArray();
    }

    /**
     * Get training modules for employee
     */
    private function getTrainingModules(Employee $employee): array
    {
        $modules = [
            'orientation' => [
                'status' => $employee->orientation_completed ? 'complete' : 'pending',
                'progress' => $employee->orientation_completed ? 'completed' : 'not_started'
            ],
            'system_access' => [
                'status' => $employee->system_access_granted ? 'granted' : 'pending_setup',
                'progress' => $employee->system_access_granted ? 'completed' : 'pending'
            ],
            'zoho_learn' => [
                'status' => $this->getZohoLearnStatus($employee),
                'progress' => $this->getZohoLearnProgress($employee)
            ],
            'probation' => [
                'status' => $this->calculateTrainingWeek($employee),
                'progress' => 'ongoing'
            ]
        ];

        return $modules;
    }

    /**
     * Get Zoho Learn status
     */
    private function getZohoLearnStatus(Employee $employee): string
    {
        $completedModules = TrainingProgress::where('employee_id', $employee->id)
            ->where('training_type', 'zoho_learn')
            ->where('status', 'completed')
            ->count();
        
        $totalModules = 6; // Simulated total modules
        
        return $completedModules . '/' . $totalModules . ' modules';
    }

    /**
     * Get Zoho Learn progress
     */
    private function getZohoLearnProgress(Employee $employee): string
    {
        $completedModules = TrainingProgress::where('employee_id', $employee->id)
            ->where('training_type', 'zoho_learn')
            ->where('status', 'completed')
            ->count();
        
        $totalModules = 6;
        
        if ($completedModules === 0) return 'not_started';
        if ($completedModules === $totalModules) return 'completed';
        return 'in_progress';
    }

    /**
     * Calculate training week
     */
    private function calculateTrainingWeek(Employee $employee): string
    {
        if (!$employee->hire_date) return 'Week 1/12';
        
        $weeksSinceHire = now()->diffInWeeks($employee->hire_date);
        $probationWeeks = 12;
        
        $currentWeek = min($weeksSinceHire + 1, $probationWeeks);
        
        return 'Week ' . $currentWeek . '/' . $probationWeeks;
    }

    /**
     * Calculate overall progress
     */
    private function calculateOverallProgress(Employee $employee): float
    {
        $completedModules = 0;
        $totalModules = 4; // orientation, system_access, zoho_learn, probation
        
        if ($employee->orientation_completed) $completedModules++;
        if ($employee->system_access_granted) $completedModules++;
        
        $zohoProgress = TrainingProgress::where('employee_id', $employee->id)
            ->where('training_type', 'zoho_learn')
            ->where('status', 'completed')
            ->count();
        
        if ($zohoProgress >= 6) $completedModules++; // All Zoho modules completed
        
        // Probation progress (simplified)
        $weeksSinceHire = now()->diffInWeeks($employee->hire_date);
        $probationProgress = min(($weeksSinceHire / 12) * 100, 100);
        
        $overallProgress = (($completedModules / $totalModules) * 100 + $probationProgress) / 2;
        
        return round($overallProgress, 1);
    }

    /**
     * Generate AI insights for employee
     */
    private function generateAIInsights(Employee $employee): array
    {
        $insights = [];
        
        // Performance-based insights
        if ($employee->ai_score >= 8.5) {
            $insights[] = 'Excellent learning velocity - 15% above average';
            $insights[] = 'Strong team integration observed';
        } elseif ($employee->ai_score >= 7.0) {
            $insights[] = 'Good attention to detail in training exercises';
            $insights[] = 'Shows potential for customer-facing roles';
        } else {
            $insights[] = 'Needs additional support with technical systems';
            $insights[] = 'Requires mentoring for skill development';
        }
        
        // Attendance-based insights
        if ($employee->attendance_rate >= 95) {
            $insights[] = 'Proactive in asking clarifying questions';
        } elseif ($employee->attendance_rate < 85) {
            $insights[] = 'Inconsistent attendance may impact learning';
        }
        
        return $insights;
    }

    /**
     * Get available actions for employee
     */
    private function getAvailableActions(Employee $employee): array
    {
        $actions = ['view_details'];
        
        if (!$employee->system_access_granted) {
            $actions[] = 'grant_system_access';
        }
        
        if ($this->calculateOverallProgress($employee) >= 80) {
            $actions[] = 'issue_onboarding_certificate';
        }
        
        if ($employee->ai_score < 6.0) {
            $actions[] = 'create_improvement_plan';
        }
        
        return $actions;
    }

    /**
     * Get performance metrics for employee
     */
    private function getPerformanceMetrics(Employee $employee): array
    {
        return [
            'ai_score' => $employee->ai_score ?? 0,
            'performance_rating' => $employee->performance_rating ?? 0,
            'attendance_rate' => $employee->attendance_rate ?? 0,
            'training_completion_rate' => $this->calculateTrainingCompletionRate($employee),
            'weeks_in_training' => now()->diffInWeeks($employee->hire_date)
        ];
    }

    /**
     * Calculate training completion rate
     */
    private function calculateTrainingCompletionRate(Employee $employee): float
    {
        $completedTrainings = TrainingProgress::where('employee_id', $employee->id)
            ->where('status', 'completed')
            ->count();
        
        $totalTrainings = Training::where('required_for_onboarding', true)->count();
        
        return $totalTrainings > 0 ? round(($completedTrainings / $totalTrainings) * 100, 1) : 0;
    }

    /**
     * Generate training recommendations
     */
    private function generateTrainingRecommendations(Employee $employee): array
    {
        $recommendations = [];
        
        if ($employee->ai_score < 6.0) {
            $recommendations[] = 'Implement additional mentoring support';
            $recommendations[] = 'Schedule regular check-ins with supervisor';
        }
        
        if ($employee->attendance_rate < 85) {
            $recommendations[] = 'Address attendance concerns';
            $recommendations[] = 'Provide flexible learning options';
        }
        
        if (!$employee->system_access_granted) {
            $recommendations[] = 'Grant system access to enable full training';
        }
        
        return $recommendations;
    }

    /**
     * Get system access training ID
     */
    private function getSystemAccessTrainingId(): int
    {
        // Return the ID of the system access training
        return Training::where('name', 'System Access Setup')->first()->id ?? 1;
    }
}
