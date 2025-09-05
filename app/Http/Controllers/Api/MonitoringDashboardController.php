<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ThresholdViolation;
use App\Models\EscalationRequest;
use App\Models\ApprovalDecision;
use App\Models\SalaryDeduction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\ApprovalWorkflow;

class MonitoringDashboardController extends Controller
{
    /**
     * Get comprehensive dashboard data
     */
    public function getDashboard(Request $request)
    {
        $user = $request->user();

        // Check if user has permission to view monitoring dashboard
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'period' => 'nullable|in:today,week,month,quarter,year',
            'refresh' => 'nullable|boolean' // Force refresh cached data
        ]);

        $period = $validated['period'] ?? 'month';
        $refresh = $validated['refresh'] ?? false;

        // Cache key for dashboard data
        $cacheKey = "dashboard_data_{$period}_{$user->id}";

        // Check cache if not forcing refresh
        if (!$refresh && Cache::has($cacheKey)) {
            $dashboardData = Cache::get($cacheKey);
            $dashboardData['from_cache'] = true;
        } else {
            // Generate fresh dashboard data
            $dashboardData = $this->generateDashboardData($period);
            $dashboardData['from_cache'] = false;
            
            // Cache for 5 minutes
            Cache::put($cacheKey, $dashboardData, 300);
        }

        return response()->json([
            'success' => true,
            'dashboard' => $dashboardData,
            'generated_at' => now(),
            'period' => $period
        ]);
    }

    /**
     * Get real-time system alerts
     */
    public function getAlerts(Request $request)
    {
        $user = $request->user();

        // Check permissions
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $alerts = [];

        // Critical escalations (expiring within 2 hours)
        $criticalEscalations = EscalationRequest::where('status', 'pending_approval')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addHours(2))
            ->count();

        if ($criticalEscalations > 0) {
            $alerts[] = [
                'type' => 'critical',
                'category' => 'escalations',
                'title' => 'Critical Escalations Expiring Soon',
                'message' => "{$criticalEscalations} escalation(s) will expire within 2 hours without approval",
                'count' => $criticalEscalations,
                'action_url' => '/admin/escalations?filter=expires_soon',
                'created_at' => now()
            ];
        }

        // High pending deductions (processing today)
        $todayDeductions = SalaryDeduction::where('status', 'pending')
            ->whereDate('deduction_date', today())
            ->count();

        if ($todayDeductions > 0) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'deductions',
                'title' => 'Salary Deductions Processing Today',
                'message' => "{$todayDeductions} salary deduction(s) are scheduled for processing today",
                'count' => $todayDeductions,
                'action_url' => '/admin/deductions?filter=today',
                'created_at' => now()
            ];
        }

        // High violation rate (more than 10 violations in last 24 hours)
        $recentViolations = ThresholdViolation::where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($recentViolations > 10) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'violations',
                'title' => 'High Violation Rate Detected',
                'message' => "{$recentViolations} threshold violations in the last 24 hours",
                'count' => $recentViolations,
                'action_url' => '/admin/violations?filter=recent',
                'created_at' => now()
            ];
        }

        // Expired escalations (auto-rejected)
        $expiredEscalations = EscalationRequest::where('status', 'expired')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($expiredEscalations > 0) {
            $alerts[] = [
                'type' => 'error',
                'category' => 'escalations',
                'title' => 'Escalations Expired Without Approval',
                'message' => "{$expiredEscalations} escalation(s) expired in the last 24 hours",
                'count' => $expiredEscalations,
                'action_url' => '/admin/escalations?filter=expired',
                'created_at' => now()
            ];
        }

        // Large pending deductions (over ₦100,000)
        $largeDeductions = SalaryDeduction::where('status', 'pending')
            ->where('amount', '>', 100000)
            ->count();

        if ($largeDeductions > 0) {
            $alerts[] = [
                'type' => 'info',
                'category' => 'deductions',
                'title' => 'Large Pending Deductions',
                'message' => "{$largeDeductions} salary deduction(s) over ₦100,000 pending",
                'count' => $largeDeductions,
                'action_url' => '/admin/deductions?filter=large',
                'created_at' => now()
            ];
        }

        // Sort alerts by severity
        $severityOrder = ['critical' => 1, 'error' => 2, 'warning' => 3, 'info' => 4];
        usort($alerts, function($a, $b) use ($severityOrder) {
            return $severityOrder[$a['type']] <=> $severityOrder[$b['type']];
        });

        return response()->json([
            'success' => true,
            'alerts' => $alerts,
            'alert_counts' => [
                'critical' => count(array_filter($alerts, fn($a) => $a['type'] === 'critical')),
                'error' => count(array_filter($alerts, fn($a) => $a['type'] === 'error')),
                'warning' => count(array_filter($alerts, fn($a) => $a['type'] === 'warning')),
                'info' => count(array_filter($alerts, fn($a) => $a['type'] === 'info'))
            ],
            'total_alerts' => count($alerts),
            'generated_at' => now()
        ]);
    }

    /**
     * Get system health metrics
     */
    public function getSystemHealth(Request $request)
    {
        $user = $request->user();

        // Check permissions
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        // Calculate health metrics
        $now = now();
        $last24Hours = $now->copy()->subHours(24);
        $lastWeek = $now->copy()->subWeek();

        // Escalation response times
        $averageResponseTime = EscalationRequest::whereNotNull('final_decision_at')
            ->where('created_at', '>=', $lastWeek)
            ->get()
            ->avg(function($escalation) {
                return $escalation->created_at->diffInHours($escalation->final_decision_at);
            });

        // Approval rates
        $totalEscalations = EscalationRequest::where('created_at', '>=', $lastWeek)->count();
        $approvedEscalations = EscalationRequest::where('status', 'approved')
            ->where('created_at', '>=', $lastWeek)
            ->count();
        $approvalRate = $totalEscalations > 0 ? ($approvedEscalations / $totalEscalations) * 100 : 0;

        // Violation prevention rate
        $totalRequests = $totalEscalations + ThresholdViolation::where('created_at', '>=', $lastWeek)->count();
        $blockedRequests = ThresholdViolation::where('status', 'blocked')
            ->where('created_at', '>=', $lastWeek)
            ->count();
        $preventionRate = $totalRequests > 0 ? ($blockedRequests / $totalRequests) * 100 : 0;

        // System performance metrics
        $metrics = [
            'response_time' => [
                'value' => round($averageResponseTime, 1),
                'unit' => 'hours',
                'status' => $averageResponseTime <= 12 ? 'good' : ($averageResponseTime <= 24 ? 'warning' : 'critical'),
                'target' => 12,
                'description' => 'Average escalation response time'
            ],
            'approval_rate' => [
                'value' => round($approvalRate, 1),
                'unit' => 'percent',
                'status' => $approvalRate >= 70 ? 'good' : ($approvalRate >= 50 ? 'warning' : 'critical'),
                'target' => 70,
                'description' => 'Percentage of escalations approved'
            ],
            'prevention_rate' => [
                'value' => round($preventionRate, 1),
                'unit' => 'percent',
                'status' => $preventionRate >= 80 ? 'good' : ($preventionRate >= 60 ? 'warning' : 'critical'),
                'target' => 80,
                'description' => 'Percentage of violations prevented'
            ],
            'escalation_backlog' => [
                'value' => EscalationRequest::where('status', 'pending_approval')->count(),
                'unit' => 'count',
                'status' => EscalationRequest::where('status', 'pending_approval')->count() <= 10 ? 'good' : 'warning',
                'target' => 10,
                'description' => 'Pending escalations requiring approval'
            ]
        ];

        // Overall health score
        $healthScore = collect($metrics)->map(function($metric) {
            return match($metric['status']) {
                'good' => 100,
                'warning' => 70,
                'critical' => 30
            };
        })->avg();

        $overallStatus = match(true) {
            $healthScore >= 90 => 'excellent',
            $healthScore >= 80 => 'good',
            $healthScore >= 60 => 'warning',
            default => 'critical'
        };

        return response()->json([
            'success' => true,
            'health' => [
                'overall_score' => round($healthScore, 1),
                'overall_status' => $overallStatus,
                'metrics' => $metrics,
                'last_updated' => now(),
                'period' => 'last_week'
            ],
            'recommendations' => $this->generateHealthRecommendations($metrics)
        ]);
    }

    /**
     * Get compliance report
     */
    public function getComplianceReport(Request $request)
    {
        $user = $request->user();

        // Check permissions
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'period' => 'nullable|in:week,month,quarter,year',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date'
        ]);

        $period = $validated['period'] ?? 'month';
        $dateFrom = $validated['date_from'] ?? now()->startOf($period);
        $dateTo = $validated['date_to'] ?? now()->endOf($period);

        // Compliance metrics
        $totalTransactions = ThresholdViolation::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $compliantTransactions = ThresholdViolation::where('status', 'approved')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();
        $complianceRate = $totalTransactions > 0 ? ($compliantTransactions / $totalTransactions) * 100 : 0;

        // Policy adherence
        $policyViolations = ThresholdViolation::where('status', 'blocked')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();
        $adherenceRate = $totalTransactions > 0 ? (($totalTransactions - $policyViolations) / $totalTransactions) * 100 : 0;

        // Approval compliance
        $requiresApproval = EscalationRequest::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $properlyApproved = EscalationRequest::where('status', 'approved')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();
        $approvalCompliance = $requiresApproval > 0 ? ($properlyApproved / $requiresApproval) * 100 : 0;

        // Salary deduction compliance
        $totalDeductions = SalaryDeduction::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $processedDeductions = SalaryDeduction::where('status', 'processed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();
        $deductionCompliance = $totalDeductions > 0 ? ($processedDeductions / $totalDeductions) * 100 : 0;

        // Violation breakdown by category
        $violationsByCategory = ThresholdViolation::selectRaw('cost_category, COUNT(*) as count, SUM(overage_amount) as total_overage')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('cost_category')
            ->orderBy('count', 'desc')
            ->get();

        // Top violators
        $topViolators = ThresholdViolation::selectRaw('created_by, COUNT(*) as violation_count, SUM(overage_amount) as total_overage')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->with('creator:id,name,email,role')
            ->groupBy('created_by')
            ->orderBy('violation_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function($item) {
                return [
                    'user' => [
                        'id' => $item->creator->id,
                        'name' => $item->creator->name,
                        'email' => $item->creator->email,
                        'role' => $item->creator->role
                    ],
                    'violation_count' => $item->violation_count,
                    'total_overage' => $item->total_overage,
                    'average_overage' => $item->violation_count > 0 ? round($item->total_overage / $item->violation_count, 2) : 0
                ];
            });

        return response()->json([
            'success' => true,
            'compliance' => [
                'period' => $period,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'overall_metrics' => [
                    'compliance_rate' => round($complianceRate, 1),
                    'adherence_rate' => round($adherenceRate, 1),
                    'approval_compliance' => round($approvalCompliance, 1),
                    'deduction_compliance' => round($deductionCompliance, 1)
                ],
                'transaction_summary' => [
                    'total_transactions' => $totalTransactions,
                    'compliant_transactions' => $compliantTransactions,
                    'policy_violations' => $policyViolations,
                    'requires_approval' => $requiresApproval,
                    'properly_approved' => $properlyApproved
                ],
                'violations_by_category' => $violationsByCategory,
                'top_violators' => $topViolators
            ],
            'generated_at' => now()
        ]);
    }

    /**
     * Get violations trend analysis
     */
    public function getViolationsTrend(Request $request)
    {
        $user = $request->user();

        // Check permissions
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'period' => 'nullable|in:week,month,quarter,year',
            'granularity' => 'nullable|in:day,week,month'
        ]);

        $period = $validated['period'] ?? 'month';
        $granularity = $validated['granularity'] ?? 'day';

        $dateFrom = now()->startOf($period);
        $dateTo = now()->endOf($period);

        // Generate trend data based on granularity
        $trendData = $this->generateTrendData($dateFrom, $dateTo, $granularity);

        return response()->json([
            'success' => true,
            'trend' => [
                'period' => $period,
                'granularity' => $granularity,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'data' => $trendData,
                'summary' => [
                    'total_violations' => array_sum(array_column($trendData, 'violations')),
                    'total_amount' => array_sum(array_column($trendData, 'total_amount')),
                    'average_daily' => round(array_sum(array_column($trendData, 'violations')) / count($trendData), 1),
                    'peak_day' => collect($trendData)->sortByDesc('violations')->first()
                ]
            ]
        ]);
    }

    /**
     * Get approval metrics analysis
     */
    public function getApprovalMetrics(Request $request)
    {
        $user = $request->user();

        // Check permissions
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'period' => 'nullable|in:week,month,quarter,year'
        ]);

        $period = $validated['period'] ?? 'month';
        $dateFrom = now()->startOf($period);
        $dateTo = now()->endOf($period);

        // Get approval decisions with workflows from the actual database structure
        $decisions = ApprovalDecision::with(['approvalWorkflow', 'approver'])
            ->whereHas('approvalWorkflow', function($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo]);
            })
            ->get();

        // Calculate metrics by approver (not role, since roles aren't stored consistently)
        $approvalMetrics = $decisions->groupBy('approver_id')->map(function($userDecisions, $approverId) {
            $approver = $userDecisions->first()->approver;
            $totalDecisions = $userDecisions->count();
            $approvedCount = $userDecisions->where('decision', 'approve')->count();
            $rejectedCount = $userDecisions->where('decision', 'reject')->count();
            
            // Calculate average response time using Carbon
            $responseTimes = $userDecisions->map(function($decision) {
                $workflow = $decision->approvalWorkflow;
                if ($workflow && $workflow->created_at && $decision->decision_at) {
                    return $workflow->created_at->diffInHours($decision->decision_at);
                }
                return null;
            })->filter();
            
            $avgResponseTime = $responseTimes->count() > 0 ? $responseTimes->avg() : 0;

            return [
                'approver_name' => $approver->name ?? 'Unknown',
                'approver_role' => $approver->role ?? 'Unknown',
                'total_decisions' => $totalDecisions,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'approval_rate' => $totalDecisions > 0 ? round(($approvedCount / $totalDecisions) * 100, 1) : 0,
                'avg_response_time' => round($avgResponseTime, 1)
            ];
        })->values();

        // Workflow outcomes (using ApprovalWorkflow status)
        $workflowOutcomes = ApprovalWorkflow::selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        // Response time distribution using actual database data
        $responseTimeDistribution = $decisions->map(function($decision) {
            $workflow = $decision->approvalWorkflow;
            if ($workflow && $workflow->created_at && $decision->decision_at) {
                return $workflow->created_at->diffInHours($decision->decision_at);
            }
            return null;
        })->filter()->groupBy(function($hours) {
            return match(true) {
                $hours <= 2 => 'immediate',
                $hours <= 12 => 'fast',  
                $hours <= 24 => 'normal',
                $hours <= 48 => 'slow',
                default => 'very_slow'
            };
        })->map(function($group) {
            return $group->count();
        });

        return response()->json([
            'success' => true,
            'approval_metrics' => [
                'period' => $period,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'by_approver' => $approvalMetrics,
                'workflow_outcomes' => $workflowOutcomes,
                'response_time_distribution' => $responseTimeDistribution,
                'summary' => [
                    'total_workflows' => ApprovalWorkflow::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
                    'total_decisions' => $decisions->count(),
                    'average_approval_rate' => $approvalMetrics->avg('approval_rate') ?? 0,
                    'average_response_time' => $approvalMetrics->avg('avg_response_time') ?? 0,
                    'fastest_approver' => $approvalMetrics->sortBy('avg_response_time')->first(),
                    'most_active_approver' => $approvalMetrics->sortByDesc('total_decisions')->first()
                ]
            ]
        ]);
    }

    /**
     * Generate comprehensive dashboard data
     */
    private function generateDashboardData(string $period): array
    {
        $dateFrom = now()->startOf($period);
        $dateTo = now()->endOf($period);

        // Quick stats
        $quickStats = [
            'total_violations' => ThresholdViolation::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'pending_escalations' => EscalationRequest::where('status', 'pending_approval')->count(),
            'pending_deductions' => SalaryDeduction::where('status', 'pending')->count(),
            'total_blocked_amount' => ThresholdViolation::where('status', 'blocked')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->sum('overage_amount')
        ];

        // Recent activity
        $recentActivity = collect()
            ->merge(
                ThresholdViolation::with('creator:id,name')
                    ->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function($violation) {
                        return [
                            'type' => 'violation',
                            'title' => 'Threshold Violation',
                            'description' => "₦" . number_format($violation->amount, 2) . " {$violation->cost_type} blocked",
                            'user' => $violation->creator->name ?? 'Unknown',
                            'timestamp' => $violation->created_at,
                            'severity' => $violation->overage_amount > 50000 ? 'high' : 'medium'
                        ];
                    })
            )
            ->merge(
                EscalationRequest::with('creator:id,name')
                    ->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function($escalation) {
                        return [
                            'type' => 'escalation',
                            'title' => 'Escalation Request',
                            'description' => "₦" . number_format($escalation->amount_requested, 2) . " awaiting approval",
                            'user' => $escalation->creator->name ?? 'Unknown',
                            'timestamp' => $escalation->created_at,
                            'severity' => $escalation->priority === 'critical' ? 'high' : 'medium'
                        ];
                    })
            )
            ->sortByDesc('timestamp')
            ->take(10)
            ->values();

        // Top categories by violations
        $topCategories = ThresholdViolation::selectRaw('cost_category, COUNT(*) as count, SUM(overage_amount) as total_overage')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('cost_category')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        // System performance indicators
        $performance = [
            'avg_response_time' => EscalationRequest::whereNotNull('final_decision_at')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->get()
                ->avg(function($escalation) {
                    return $escalation->created_at->diffInHours($escalation->final_decision_at);
                }),
            'approval_rate' => $this->calculateApprovalRate($dateFrom, $dateTo),
            'prevention_effectiveness' => $this->calculatePreventionRate($dateFrom, $dateTo)
        ];

        return [
            'quick_stats' => $quickStats,
            'recent_activity' => $recentActivity,
            'top_categories' => $topCategories,
            'performance' => $performance,
            'period' => $period,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }

    /**
     * Generate trend data for violations
     */
    private function generateTrendData(Carbon $dateFrom, Carbon $dateTo, string $granularity): array
    {
        $trendData = [];
        $current = $dateFrom->copy();

        while ($current <= $dateTo) {
            $endDate = match($granularity) {
                'day' => $current->copy()->endOfDay(),
                'week' => $current->copy()->endOfWeek(),
                'month' => $current->copy()->endOfMonth(),
                default => $current->copy()->endOfDay()
            };

            $violations = ThresholdViolation::whereBetween('created_at', [$current, $endDate])->count();
            $totalAmount = ThresholdViolation::whereBetween('created_at', [$current, $endDate])->sum('overage_amount');
            $escalations = EscalationRequest::whereBetween('created_at', [$current, $endDate])->count();

            $trendData[] = [
                'date' => $current->format('Y-m-d'),
                'violations' => $violations,
                'total_amount' => $totalAmount,
                'escalations' => $escalations,
                'avg_amount' => $violations > 0 ? round($totalAmount / $violations, 2) : 0
            ];

            $current = match($granularity) {
                'day' => $current->addDay(),
                'week' => $current->addWeek(),
                'month' => $current->addMonth(),
                default => $current->addDay()
            };
        }

        return $trendData;
    }

    /**
     * Calculate approval rate
     */
    private function calculateApprovalRate(Carbon $dateFrom, Carbon $dateTo): float
    {
        $totalEscalations = EscalationRequest::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $approvedEscalations = EscalationRequest::where('status', 'approved')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        return $totalEscalations > 0 ? round(($approvedEscalations / $totalEscalations) * 100, 1) : 0;
    }

    /**
     * Calculate prevention rate
     */
    private function calculatePreventionRate(Carbon $dateFrom, Carbon $dateTo): float
    {
        $totalViolations = ThresholdViolation::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $blockedViolations = ThresholdViolation::where('status', 'blocked')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        return $totalViolations > 0 ? round(($blockedViolations / $totalViolations) * 100, 1) : 0;
    }

    /**
     * Generate health recommendations
     */
    private function generateHealthRecommendations(array $metrics): array
    {
        $recommendations = [];

        // Response time recommendations
        if ($metrics['response_time']['status'] !== 'good') {
            $recommendations[] = [
                'category' => 'response_time',
                'priority' => 'high',
                'title' => 'Improve Approval Response Time',
                'description' => 'Consider implementing automated reminders and escalation procedures',
                'action' => 'Set up automated email reminders for pending approvals'
            ];
        }

        // Approval rate recommendations
        if ($metrics['approval_rate']['status'] !== 'good') {
            $recommendations[] = [
                'category' => 'approval_rate',
                'priority' => 'medium',
                'title' => 'Review Approval Processes',
                'description' => 'Low approval rates may indicate issues with threshold settings or approval criteria',
                'action' => 'Review and potentially adjust threshold limits and approval requirements'
            ];
        }

        // Prevention rate recommendations
        if ($metrics['prevention_rate']['status'] !== 'good') {
            $recommendations[] = [
                'category' => 'prevention_rate',
                'priority' => 'high',
                'title' => 'Strengthen Prevention Measures',
                'description' => 'Consider additional controls or training to prevent threshold violations',
                'action' => 'Implement additional validation checks and user training programs'
            ];
        }

        // Escalation backlog recommendations
        if ($metrics['escalation_backlog']['status'] !== 'good') {
            $recommendations[] = [
                'category' => 'escalation_backlog',
                'priority' => 'critical',
                'title' => 'Address Escalation Backlog',
                'description' => 'High number of pending escalations may impact business operations',
                'action' => 'Prioritize pending escalations and consider additional approver resources'
            ];
        }

        return $recommendations;
    }
} 