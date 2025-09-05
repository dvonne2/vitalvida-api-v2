<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\HRIntelligenceService;
use App\Services\AttendanceAnalyticsService;
use App\Services\PerformanceIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReactHRController extends Controller
{
    protected $hrIntelligenceService;
    protected $attendanceAnalyticsService;
    protected $performanceIntelligenceService;

    public function __construct(
        HRIntelligenceService $hrIntelligenceService,
        AttendanceAnalyticsService $attendanceAnalyticsService,
        PerformanceIntelligenceService $performanceIntelligenceService
    ) {
        $this->hrIntelligenceService = $hrIntelligenceService;
        $this->attendanceAnalyticsService = $attendanceAnalyticsService;
        $this->performanceIntelligenceService = $performanceIntelligenceService;
    }

    /**
     * Get HR module initial load data
     */
    public function getModuleInitialLoad(Request $request, string $module): JsonResponse
    {
        try {
            $cacheKey = "hr_module_{$module}_initial_load";
            
            // Check cache first
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                if (now()->diffInMinutes($cachedData['cached_at']) < 10) {
                    return response()->json($cachedData['data']);
                }
            }
            
            $moduleData = $this->loadModuleData($module);
            $meta = $this->generateMetaData($module);
            $aiInsights = $this->generateAIInsights($module);
            $userPermissions = $this->getUserPermissions($request->user());
            
            $response = [
                'module_data' => $moduleData,
                'meta' => [
                    'last_updated' => now()->toISOString(),
                    'websocket_channels' => $this->getWebSocketChannels($module),
                    'user_permissions' => $userPermissions,
                    'cache_duration' => 600 // 10 minutes
                ],
                'ai_insights' => $aiInsights
            ];
            
            // Cache the response
            Cache::put($cacheKey, [
                'data' => $response,
                'cached_at' => now()
            ], 600);
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            Log::error('React HR - Module Initial Load Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to load module data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get component-specific data
     */
    public function getComponentData(Request $request, string $component): JsonResponse
    {
        try {
            $cacheKey = "hr_component_{$component}_" . md5(json_encode($request->all()));
            
            // Check cache first
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                if (now()->diffInMinutes($cachedData['cached_at']) < 5) {
                    return response()->json($cachedData['data']);
                }
            }
            
            $componentData = $this->loadComponentData($component, $request);
            
            // Cache the response
            Cache::put($cacheKey, [
                'data' => $componentData,
                'cached_at' => now()
            ], 300);
            
            return response()->json($componentData);
            
        } catch (\Exception $e) {
            Log::error('React HR - Component Data Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to load component data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get real-time updates for HR modules
     */
    public function getRealTimeUpdates(Request $request): JsonResponse
    {
        try {
            $updates = [];
            $modules = $request->get('modules', []);
            
            foreach ($modules as $module) {
                $updates[$module] = $this->getModuleUpdates($module);
            }
            
            return response()->json([
                'updates' => $updates,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('React HR - Real-time Updates Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to get real-time updates',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get mobile-optimized HR data
     */
    public function getMobileHRData(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $dataType = $request->get('type', 'self_service');
            
            if ($dataType === 'self_service') {
                $data = $this->getEmployeeSelfServiceData($user);
            } else {
                $data = $this->getManagerTeamOverview($user);
            }
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'optimized_for' => 'mobile',
                'last_updated' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('React HR - Mobile Data Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to load mobile data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Load module-specific data
     */
    private function loadModuleData(string $module): array
    {
        switch ($module) {
            case 'dashboard':
                return $this->loadDashboardData();
            case 'talent_pipeline':
                return $this->loadTalentPipelineData();
            case 'performance':
                return $this->loadPerformanceData();
            case 'training':
                return $this->loadTrainingData();
            case 'payroll':
                return $this->loadPayrollData();
            case 'exit':
                return $this->loadExitData();
            default:
                return [];
        }
    }

    /**
     * Load dashboard data
     */
    private function loadDashboardData(): array
    {
        return [
            'overview' => [
                'total_employees' => 150,
                'active_recruitments' => 8,
                'training_progress' => '75%',
                'performance_alerts' => 3
            ],
            'talent_pipeline' => [
                'applications_count' => 45,
                'screening_count' => 12,
                'interview_count' => 8,
                'offer_count' => 3
            ],
            'performance_summary' => [
                'excellent' => 30,
                'good' => 50,
                'needs_improvement' => 20
            ],
            'recent_activities' => [
                [
                    'type' => 'application',
                    'description' => 'New application received for Senior Developer',
                    'time' => '2 minutes ago'
                ],
                [
                    'type' => 'training',
                    'description' => 'Training completed by John Doe',
                    'time' => '15 minutes ago'
                ]
            ]
        ];
    }

    /**
     * Load talent pipeline data
     */
    private function loadTalentPipelineData(): array
    {
        return [
            'candidates' => [
                [
                    'id' => 1,
                    'name' => 'John Smith',
                    'position' => 'Senior Developer',
                    'department' => 'Engineering',
                    'status' => 'screening',
                    'ai_score' => 8.5,
                    'applied_date' => '2024-01-15'
                ],
                [
                    'id' => 2,
                    'name' => 'Jane Doe',
                    'position' => 'Marketing Manager',
                    'department' => 'Marketing',
                    'status' => 'interview',
                    'ai_score' => 7.8,
                    'applied_date' => '2024-01-14'
                ]
            ],
            'statistics' => [
                'total_applications' => 45,
                'in_screening' => 12,
                'in_interview' => 8,
                'offers_pending' => 3
            ]
        ];
    }

    /**
     * Load performance data
     */
    private function loadPerformanceData(): array
    {
        return [
            'weekly_scorecards' => [
                [
                    'employee' => 'David Okonkwo',
                    'department' => 'Marketing',
                    'tasks_completion' => '18/20 (90%)',
                    'kpi_score' => 9.2,
                    'ai_score' => 9.5,
                    'status' => 'bonus_eligible'
                ]
            ],
            'alerts' => [
                [
                    'employee' => 'Grace Effiong',
                    'alert_type' => 'performance_decline',
                    'message' => '2 missed deadlines this week',
                    'severity' => 'medium'
                ]
            ]
        ];
    }

    /**
     * Load training data
     */
    private function loadTrainingData(): array
    {
        return [
            'employees_in_training' => [
                [
                    'id' => 1,
                    'name' => 'David Okonkwo',
                    'position' => 'Digital Marketing Specialist',
                    'department' => 'Marketing',
                    'ai_score' => '8.7/10',
                    'week' => 'Week 2/12',
                    'overall_progress' => '65%'
                ]
            ],
            'overview' => [
                'active_probations' => 3,
                'avg_ai_score' => 7.4,
                'training_complete' => '62%',
                'at_risk' => 1
            ]
        ];
    }

    /**
     * Load payroll data
     */
    private function loadPayrollData(): array
    {
        return [
            'overview' => [
                'total_payroll' => 1735000,
                'avg_attendance' => '87%',
                'pending_leaves' => 1,
                'ai_flags' => 2
            ],
            'monthly_payroll_summary' => [
                'period' => 'July 2024',
                'employees' => [
                    [
                        'name' => 'David Okonkwo',
                        'department' => 'Marketing',
                        'base_salary' => 850000,
                        'performance_bonus' => 150000,
                        'net_pay' => 1000000,
                        'status' => 'pending'
                    ]
                ]
            ]
        ];
    }

    /**
     * Load exit data
     */
    private function loadExitData(): array
    {
        return [
            'overview' => [
                'total_exits_ytd' => 8,
                'avg_tenure' => '18.4m',
                'cost_impact' => 3420000,
                'active_exits' => 2
            ],
            'active_exit_processes' => [
                [
                    'employee' => 'Sarah Okafor',
                    'position' => 'Senior Developer',
                    'department' => 'Engineering',
                    'exit_date' => '15/08/2024',
                    'reason' => 'resignation',
                    'progress' => '80%'
                ]
            ]
        ];
    }

    /**
     * Generate meta data
     */
    private function generateMetaData(string $module): array
    {
        return [
            'module' => $module,
            'version' => '1.0.0',
            'last_updated' => now()->toISOString(),
            'cache_enabled' => true,
            'real_time_updates' => true
        ];
    }

    /**
     * Generate AI insights
     */
    private function generateAIInsights(string $module): array
    {
        $insights = [
            'urgent_alerts' => rand(0, 5),
            'pending_reviews' => rand(0, 10),
            'system_recommendations' => [
                'Consider reviewing performance metrics for team A',
                'Training completion rate is below target',
                'High number of applications in screening phase'
            ]
        ];
        
        return $insights;
    }

    /**
     * Get user permissions
     */
    private function getUserPermissions($user): array
    {
        if (!$user) {
            return [
                'can_hire' => false,
                'can_manage_payroll' => false,
                'can_access_performance' => false,
                'can_manage_training' => false,
                'can_view_exit_processes' => false
            ];
        }
        
        // Simulated permissions based on user role
        return [
            'can_hire' => true,
            'can_manage_payroll' => $user->role === 'hr_manager',
            'can_access_performance' => true,
            'can_manage_training' => true,
            'can_view_exit_processes' => $user->role === 'hr_manager'
        ];
    }

    /**
     * Get WebSocket channels
     */
    private function getWebSocketChannels(string $module): array
    {
        $channels = ['hr-dashboard'];
        
        switch ($module) {
            case 'talent_pipeline':
                $channels[] = 'talent-pipeline';
                break;
            case 'performance':
                $channels[] = 'performance-alerts';
                break;
            case 'training':
                $channels[] = 'training-updates';
                break;
            case 'payroll':
                $channels[] = 'payroll-updates';
                break;
            case 'exit':
                $channels[] = 'exit-notifications';
                break;
        }
        
        return $channels;
    }

    /**
     * Load component-specific data
     */
    private function loadComponentData(string $component, Request $request): array
    {
        switch ($component) {
            case 'talent-widget':
                return $this->loadTalentWidgetData($request);
            case 'performance-scorecard':
                return $this->loadPerformanceScorecardData($request);
            case 'attendance-summary':
                return $this->loadAttendanceSummaryData($request);
            case 'ai-insights-panel':
                return $this->loadAIInsightsPanelData($request);
            default:
                return [];
        }
    }

    /**
     * Load talent widget data
     */
    private function loadTalentWidgetData(Request $request): array
    {
        return [
            'recent_applications' => 5,
            'pending_reviews' => 3,
            'interviews_scheduled' => 2,
            'offers_pending' => 1
        ];
    }

    /**
     * Load performance scorecard data
     */
    private function loadPerformanceScorecardData(Request $request): array
    {
        return [
            'weekly_performance' => [
                'average_score' => 8.2,
                'improvement_rate' => 0.15,
                'top_performers' => 3,
                'needs_improvement' => 1
            ]
        ];
    }

    /**
     * Load attendance summary data
     */
    private function loadAttendanceSummaryData(Request $request): array
    {
        return [
            'attendance_rate' => 87.5,
            'late_arrivals' => 2,
            'absent_days' => 1,
            'wfh_days' => 8
        ];
    }

    /**
     * Load AI insights panel data
     */
    private function loadAIInsightsPanelData(Request $request): array
    {
        return [
            'insights' => [
                [
                    'type' => 'recommendation',
                    'title' => 'Performance Improvement',
                    'description' => 'Consider additional training for team members',
                    'priority' => 'medium'
                ],
                [
                    'type' => 'alert',
                    'title' => 'Attendance Concern',
                    'description' => 'Unusual attendance pattern detected',
                    'priority' => 'high'
                ]
            ]
        ];
    }

    /**
     * Get module updates
     */
    private function getModuleUpdates(string $module): array
    {
        return [
            'last_update' => now()->toISOString(),
            'changes' => [
                'new_items' => rand(0, 5),
                'updated_items' => rand(0, 3),
                'deleted_items' => rand(0, 1)
            ]
        ];
    }

    /**
     * Get employee self-service data
     */
    private function getEmployeeSelfServiceData($user): array
    {
        return [
            'profile' => [
                'name' => $user->name,
                'position' => 'Software Developer',
                'department' => 'Engineering',
                'employee_id' => 'EMP001'
            ],
            'attendance_summary' => [
                'this_month' => 85.5,
                'last_month' => 87.2,
                'total_days' => 22,
                'present_days' => 19
            ],
            'leave_balance' => [
                'annual_leave' => 15,
                'sick_leave' => 5,
                'personal_leave' => 3
            ],
            'performance_snapshot' => [
                'current_rating' => 8.5,
                'last_review' => '2024-01-15',
                'next_review' => '2024-04-15'
            ],
            'training_progress' => [
                'completed_courses' => 8,
                'in_progress' => 2,
                'certifications' => 3
            ]
        ];
    }

    /**
     * Get manager team overview
     */
    private function getManagerTeamOverview($user): array
    {
        return [
            'team_performance' => [
                'average_score' => 8.2,
                'top_performers' => 3,
                'needs_improvement' => 1
            ],
            'pending_approvals' => [
                'leave_requests' => 2,
                'expense_reports' => 1,
                'performance_reviews' => 3
            ],
            'attendance_alerts' => [
                'late_arrivals' => 1,
                'absent_employees' => 0
            ],
            'quick_actions' => [
                'schedule_meeting',
                'approve_leave',
                'review_performance'
            ]
        ];
    }
} 