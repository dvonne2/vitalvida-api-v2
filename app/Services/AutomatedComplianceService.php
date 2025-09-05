<?php

namespace App\Services;

use App\Models\VitalVidaInventory\DeliveryAgent as VitalVidaDeliveryAgent;
use App\Models\DeliveryAgent as RoleDeliveryAgent;
use App\Models\ComplianceViolation;
use App\Models\AgentActivityLog;
use App\Services\RealTimeSyncService;
use App\Events\ComplianceActionEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AutomatedComplianceService
{
    private $realTimeSync;

    public function __construct(RealTimeSyncService $realTimeSync)
    {
        $this->realTimeSync = $realTimeSync;
    }

    /**
     * Run automated compliance monitoring and trigger actions
     */
    public function runAutomatedComplianceMonitoring()
    {
        $complianceResults = [
            'photo_compliance_check' => $this->checkPhotoCompliance(),
            'performance_compliance_check' => $this->checkPerformanceCompliance(),
            'inventory_compliance_check' => $this->checkInventoryCompliance(),
            'delivery_compliance_check' => $this->checkDeliveryCompliance(),
            'behavioral_compliance_check' => $this->checkBehavioralCompliance()
        ];

        $totalViolations = array_sum(array_column($complianceResults, 'violations_found'));
        $totalActions = array_sum(array_column($complianceResults, 'actions_taken'));

        // Generate compliance report
        $report = $this->generateComplianceReport($complianceResults);

        // Trigger system-wide actions if needed
        $systemActions = $this->triggerSystemWideActions($complianceResults);

        return [
            'monitoring_completed_at' => now(),
            'total_violations_found' => $totalViolations,
            'total_actions_taken' => $totalActions,
            'compliance_results' => $complianceResults,
            'system_actions' => $systemActions,
            'compliance_report' => $report,
            'next_monitoring_scheduled' => now()->addHours(6)
        ];
    }

    private function checkPhotoCompliance()
    {
        $violations = [];
        $actionsExecuted = [];

        // Get agents who haven't submitted photos in required timeframe
        $agents = RoleDeliveryAgent::where('status', 'active')->get();

        foreach ($agents as $agent) {
            $lastPhotoSubmission = AgentActivityLog::where('da_id', $agent->id)
                ->where('action_type', 'photo_submission')
                ->latest()
                ->first();

            $hoursWithoutPhoto = $lastPhotoSubmission 
                ? $lastPhotoSubmission->created_at->diffInHours(now())
                : 999; // Very high number if no photo ever

            // Define compliance rules
            $complianceRules = $this->getPhotoComplianceRules();
            
            foreach ($complianceRules as $rule) {
                if ($hoursWithoutPhoto >= $rule['hours_threshold']) {
                    $violation = $this->createViolationRecord($agent, 'photo_compliance', $rule);
                    $violations[] = $violation;

                    // Auto-execute enforcement action
                    $enforcementResult = $this->autoExecuteEnforcement(
                        $agent->external_id, 
                        $rule['enforcement_action'],
                        $rule['reason'],
                        $rule['severity']
                    );
                    
                    $actionsExecuted[] = $enforcementResult;
                    break; // Only trigger most severe applicable rule
                }
            }
        }

        return [
            'check_type' => 'photo_compliance',
            'agents_checked' => $agents->count(),
            'violations_found' => count($violations),
            'actions_taken' => count($actionsExecuted),
            'violations' => $violations,
            'actions' => $actionsExecuted
        ];
    }

    private function checkPerformanceCompliance()
    {
        $violations = [];
        $actionsExecuted = [];

        $performanceThresholds = [
            'critical' => ['rating' => 2.0, 'action' => 'suspend', 'severity' => 'critical'],
            'warning' => ['rating' => 3.0, 'action' => 'performance_review', 'severity' => 'high'],
            'training' => ['rating' => 3.5, 'action' => 'mandatory_training', 'severity' => 'medium']
        ];

        $underperformingAgents = VitalVidaDeliveryAgent::where('rating', '<', 3.5)->get();

        foreach ($underperformingAgents as $agent) {
            foreach ($performanceThresholds as $level => $threshold) {
                if ($agent->rating <= $threshold['rating']) {
                    // Check if this agent already has recent violation for this level
                    $recentViolation = ComplianceViolation::where('da_id', $agent->id)
                        ->where('violation_type', 'performance_' . $level)
                        ->where('created_at', '>', now()->subDays(30))
                        ->exists();

                    if (!$recentViolation) {
                        $violation = $this->createPerformanceViolation($agent, $level, $threshold);
                        $violations[] = $violation;

                        $enforcementResult = $this->autoExecuteEnforcement(
                            $agent->id,
                            $threshold['action'],
                            "Performance rating ({$agent->rating}) below acceptable threshold ({$threshold['rating']})",
                            $threshold['severity']
                        );

                        $actionsExecuted[] = $enforcementResult;
                    }
                    break; // Only trigger most severe applicable threshold
                }
            }
        }

        return [
            'check_type' => 'performance_compliance',
            'agents_checked' => VitalVidaDeliveryAgent::count(),
            'violations_found' => count($violations),
            'actions_taken' => count($actionsExecuted),
            'violations' => $violations,
            'actions' => $actionsExecuted
        ];
    }

    private function checkInventoryCompliance()
    {
        $violations = [];
        $actionsExecuted = [];

        $agents = VitalVidaDeliveryAgent::with('roleAgent')->get();

        foreach ($agents as $agent) {
            $inventoryIssues = $this->analyzeAgentInventory($agent);

            foreach ($inventoryIssues as $issue) {
                $violation = $this->createInventoryViolation($agent, $issue);
                $violations[] = $violation;

                $enforcementResult = $this->autoExecuteEnforcement(
                    $agent->id,
                    $issue['recommended_action'],
                    $issue['reason'],
                    $issue['severity']
                );

                $actionsExecuted[] = $enforcementResult;
            }
        }

        return [
            'check_type' => 'inventory_compliance',
            'agents_checked' => $agents->count(),
            'violations_found' => count($violations),
            'actions_taken' => count($actionsExecuted),
            'violations' => $violations,
            'actions' => $actionsExecuted
        ];
    }

    private function checkDeliveryCompliance()
    {
        $violations = [];
        $actionsExecuted = [];

        // Check for delivery time violations
        $deliveryViolations = AgentActivityLog::where('action_type', 'delivery_delayed')
            ->where('created_at', '>', now()->subDays(7))
            ->with('deliveryAgent')
            ->get()
            ->groupBy('da_id');

        foreach ($deliveryViolations as $agentId => $agentViolations) {
            $violationCount = $agentViolations->count();
            
            if ($violationCount >= 3) { // 3 or more delays in a week
                $roleAgent = RoleDeliveryAgent::find($agentId);
                $vitalAgent = $roleAgent ? VitalVidaDeliveryAgent::find($roleAgent->external_id) : null;
                
                if ($vitalAgent) {
                    $violation = $this->createDeliveryViolation($vitalAgent, $violationCount);
                    $violations[] = $violation;

                    $severity = $violationCount >= 5 ? 'critical' : 'high';
                    $action = $violationCount >= 5 ? 'suspend' : 'mandatory_training';

                    $enforcementResult = $this->autoExecuteEnforcement(
                        $vitalAgent->id,
                        $action,
                        "Multiple delivery delays ({$violationCount}) in the past week",
                        $severity
                    );

                    $actionsExecuted[] = $enforcementResult;
                }
            }
        }

        return [
            'check_type' => 'delivery_compliance',
            'agents_checked' => VitalVidaDeliveryAgent::count(),
            'violations_found' => count($violations),
            'actions_taken' => count($actionsExecuted),
            'violations' => $violations,
            'actions' => $actionsExecuted
        ];
    }

    private function checkBehavioralCompliance()
    {
        $violations = [];
        $actionsExecuted = [];

        // Check for behavioral patterns that indicate compliance issues
        $agents = VitalVidaDeliveryAgent::all();

        foreach ($agents as $agent) {
            $behavioralIssues = $this->analyzeBehavioralPatterns($agent);

            foreach ($behavioralIssues as $issue) {
                $violation = $this->createBehavioralViolation($agent, $issue);
                $violations[] = $violation;

                $enforcementResult = $this->autoExecuteEnforcement(
                    $agent->id,
                    $issue['recommended_action'],
                    $issue['reason'],
                    $issue['severity']
                );

                $actionsExecuted[] = $enforcementResult;
            }
        }

        return [
            'check_type' => 'behavioral_compliance',
            'agents_checked' => $agents->count(),
            'violations_found' => count($violations),
            'actions_taken' => count($actionsExecuted),
            'violations' => $violations,
            'actions' => $actionsExecuted
        ];
    }

    /**
     * Smart compliance rules engine
     */
    private function getPhotoComplianceRules()
    {
        return [
            [
                'hours_threshold' => 72,
                'enforcement_action' => 'suspend',
                'reason' => 'No photo submission for 72+ hours - Critical compliance violation',
                'severity' => 'critical'
            ],
            [
                'hours_threshold' => 48,
                'enforcement_action' => 'reduce_allocation',
                'reason' => 'No photo submission for 48+ hours - Serious compliance issue',
                'severity' => 'high'
            ],
            [
                'hours_threshold' => 24,
                'enforcement_action' => 'warning',
                'reason' => 'No photo submission for 24+ hours - Compliance reminder required',
                'severity' => 'medium'
            ]
        ];
    }

    private function analyzeAgentInventory($agent)
    {
        $issues = [];
        
        if (!$agent->roleAgent) {
            return $issues;
        }

        // Check for over-allocation
        $totalAllocated = DB::table('bins')
            ->where('da_id', $agent->roleAgent->id)
            ->sum('current_stock');
        
        $maxCapacity = $agent->max_capacity ?? 1000;
        
        if ($totalAllocated > $maxCapacity * 1.1) { // 10% tolerance
            $issues[] = [
                'type' => 'over_allocation',
                'severity' => 'high',
                'reason' => "Agent over-allocated: {$totalAllocated} units exceeds capacity of {$maxCapacity}",
                'recommended_action' => 'reduce_allocation'
            ];
        }

        // Check for stale inventory (products allocated but not moved)
        $staleBins = DB::table('bins')
            ->where('da_id', $agent->roleAgent->id)
            ->where('last_allocation_at', '<', now()->subDays(14))
            ->where('bin_status', 'active')
            ->count();

        if ($staleBins > 0) {
            $issues[] = [
                'type' => 'stale_inventory',
                'severity' => 'medium',
                'reason' => "Agent has {$staleBins} bins with stale inventory (14+ days without movement)",
                'recommended_action' => 'performance_review'
            ];
        }

        // Check for inventory discrepancies
        $discrepancies = $this->checkInventoryDiscrepancies($agent);
        if ($discrepancies['has_discrepancies']) {
            $issues[] = [
                'type' => 'inventory_discrepancy',
                'severity' => 'high',
                'reason' => "Inventory discrepancies found: {$discrepancies['description']}",
                'recommended_action' => 'mandatory_training'
            ];
        }

        return $issues;
    }

    private function analyzeBehavioralPatterns($agent)
    {
        $issues = [];

        // Analyze activity patterns
        $recentActivities = AgentActivityLog::where('da_id', $agent->id)
            ->where('created_at', '>', now()->subDays(30))
            ->get();

        // Check for suspicious patterns
        $suspiciousPatterns = $this->detectSuspiciousPatterns($recentActivities);
        
        foreach ($suspiciousPatterns as $pattern) {
            $issues[] = [
                'type' => 'suspicious_behavior',
                'pattern' => $pattern['type'],
                'severity' => $pattern['severity'],
                'reason' => $pattern['description'],
                'recommended_action' => $pattern['action']
            ];
        }

        // Check communication responsiveness
        $responsiveness = $this->analyzeResponseTime($agent);
        if ($responsiveness['is_unresponsive']) {
            $issues[] = [
                'type' => 'poor_communication',
                'severity' => 'medium',
                'reason' => "Poor communication responsiveness: {$responsiveness['description']}",
                'recommended_action' => 'warning'
            ];
        }

        return $issues;
    }

    private function detectSuspiciousPatterns($activities)
    {
        $patterns = [];

        if ($activities->isEmpty()) {
            return $patterns;
        }

        // Pattern 1: Rapid consecutive actions (possible automation/fraud)
        $rapidActions = $activities->filter(function($activity) use ($activities) {
            $nextActivity = $activities->where('created_at', '>', $activity->created_at)
                ->sortBy('created_at')
                ->first();
            
            return $nextActivity && $activity->created_at->diffInSeconds($nextActivity->created_at) < 10;
        });

        if ($rapidActions->count() > 5) {
            $patterns[] = [
                'type' => 'rapid_actions',
                'severity' => 'high',
                'description' => "Detected {$rapidActions->count()} rapid consecutive actions suggesting automated behavior",
                'action' => 'mandatory_training'
            ];
        }

        // Pattern 2: Off-hours activity (activity outside normal business hours)
        $offHoursActivities = $activities->filter(function($activity) {
            $hour = $activity->created_at->hour;
            return $hour < 6 || $hour > 22; // Outside 6 AM - 10 PM
        });

        if ($offHoursActivities->count() > 10) {
            $patterns[] = [
                'type' => 'off_hours_activity',
                'severity' => 'medium',
                'description' => "Unusual activity pattern: {$offHoursActivities->count()} actions outside normal hours",
                'action' => 'performance_review'
            ];
        }

        return $patterns;
    }

    /**
     * Automated violation record creation
     */
    private function createViolationRecord($agent, $type, $details)
    {
        return ComplianceViolation::create([
            'da_id' => $agent->id,
            'violation_type' => $type,
            'description' => $details['reason'],
            'severity' => $details['severity'],
            'auto_detected' => true,
            'detection_algorithm' => 'automated_compliance_v2',
            'evidence' => json_encode($details),
            'status' => 'pending_action',
            'detected_at' => now()
        ]);
    }

    private function createPerformanceViolation($agent, $level, $threshold)
    {
        return ComplianceViolation::create([
            'da_id' => $agent->id,
            'violation_type' => 'performance_' . $level,
            'description' => "Performance rating ({$agent->rating}) below {$level} threshold ({$threshold['rating']})",
            'severity' => $threshold['severity'],
            'auto_detected' => true,
            'detection_algorithm' => 'performance_monitoring_v1',
            'evidence' => json_encode([
                'current_rating' => $agent->rating,
                'threshold' => $threshold['rating'],
                'level' => $level
            ]),
            'status' => 'pending_action',
            'detected_at' => now()
        ]);
    }

    private function createInventoryViolation($agent, $issue)
    {
        return ComplianceViolation::create([
            'da_id' => $agent->id,
            'violation_type' => 'inventory_' . $issue['type'],
            'description' => $issue['reason'],
            'severity' => $issue['severity'],
            'auto_detected' => true,
            'detection_algorithm' => 'inventory_compliance_v1',
            'evidence' => json_encode($issue),
            'status' => 'pending_action',
            'detected_at' => now()
        ]);
    }

    private function createDeliveryViolation($agent, $violationCount)
    {
        return ComplianceViolation::create([
            'da_id' => $agent->id,
            'violation_type' => 'delivery_delays',
            'description' => "Multiple delivery delays ({$violationCount}) in the past week",
            'severity' => $violationCount >= 5 ? 'critical' : 'high',
            'auto_detected' => true,
            'detection_algorithm' => 'delivery_monitoring_v1',
            'evidence' => json_encode([
                'violation_count' => $violationCount,
                'period' => '7_days'
            ]),
            'status' => 'pending_action',
            'detected_at' => now()
        ]);
    }

    private function createBehavioralViolation($agent, $issue)
    {
        return ComplianceViolation::create([
            'da_id' => $agent->id,
            'violation_type' => 'behavioral_' . $issue['type'],
            'description' => $issue['reason'],
            'severity' => $issue['severity'],
            'auto_detected' => true,
            'detection_algorithm' => 'behavioral_analysis_v1',
            'evidence' => json_encode($issue),
            'status' => 'pending_action',
            'detected_at' => now()
        ]);
    }

    private function autoExecuteEnforcement($agentId, $action, $reason, $severity)
    {
        try {
            $result = $this->realTimeSync->syncComplianceAction([
                'agent_id' => $agentId,
                'action_type' => $action,
                'severity' => $severity,
                'reason' => $reason
            ]);

            // Log automated enforcement
            Log::info("Automated enforcement action executed", [
                'agent_id' => $agentId,
                'action' => $action,
                'reason' => $reason,
                'severity' => $severity,
                'result' => $result
            ]);

            return array_merge($result, ['executed_by' => 'automated_system']);

        } catch (\Exception $e) {
            Log::error("Automated enforcement failed", [
                'agent_id' => $agentId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'agent_id' => $agentId,
                'action' => $action,
                'error' => $e->getMessage(),
                'executed_by' => 'automated_system'
            ];
        }
    }

    /**
     * Generate comprehensive compliance report
     */
    private function generateComplianceReport($complianceResults)
    {
        $totalViolations = array_sum(array_column($complianceResults, 'violations_found'));
        $totalActions = array_sum(array_column($complianceResults, 'actions_taken'));
        $totalAgents = VitalVidaDeliveryAgent::count();

        // Calculate compliance rate
        $complianceRate = $totalAgents > 0 ? (($totalAgents - $totalViolations) / $totalAgents) * 100 : 100;

        // Identify trends
        $previousReport = Cache::get('last_compliance_report');
        $trends = $this->calculateComplianceTrends($complianceResults, $previousReport);

        $report = [
            'report_id' => uniqid('COMP_'),
            'generated_at' => now(),
            'period' => 'automated_monitoring',
            'summary' => [
                'total_agents' => $totalAgents,
                'total_violations' => $totalViolations,
                'total_actions' => $totalActions,
                'compliance_rate' => round($complianceRate, 2),
                'violation_rate' => round(($totalViolations / max(1, $totalAgents)) * 100, 2)
            ],
            'breakdown_by_type' => $complianceResults,
            'trends' => $trends,
            'recommendations' => $this->generateComplianceRecommendations($complianceResults),
            'next_actions' => $this->suggestNextActions($complianceResults)
        ];

        // Cache report for trend analysis
        Cache::put('last_compliance_report', $report, now()->addDays(1));

        return $report;
    }

    private function triggerSystemWideActions($complianceResults)
    {
        $systemActions = [];
        $totalViolations = array_sum(array_column($complianceResults, 'violations_found'));
        $totalAgents = VitalVidaDeliveryAgent::count();
        $violationRate = $totalAgents > 0 ? ($totalViolations / $totalAgents) * 100 : 0;

        // Trigger system-wide actions based on violation rates
        if ($violationRate > 30) { // High violation rate
            $systemActions[] = [
                'action' => 'escalate_to_management',
                'reason' => "High violation rate ({$violationRate}%) requires management attention",
                'executed' => $this->escalateToManagement($complianceResults)
            ];

            $systemActions[] = [
                'action' => 'increase_monitoring_frequency',
                'reason' => "Increasing monitoring frequency due to high violation rate",
                'executed' => $this->increaseMonitoringFrequency()
            ];
        }

        if ($violationRate > 50) { // Critical violation rate
            $systemActions[] = [
                'action' => 'system_wide_training',
                'reason' => "Critical violation rate requires immediate system-wide intervention",
                'executed' => $this->triggerSystemWideTraining()
            ];
        }

        return $systemActions;
    }

    // Helper methods
    private function checkInventoryDiscrepancies($agent)
    {
        if (!$agent->roleAgent) {
            return ['has_discrepancies' => false];
        }

        // Simple discrepancy check
        $binCount = DB::table('bins')->where('da_id', $agent->roleAgent->id)->count();
        $expectedBins = 5; // Assume 5 bins per agent

        return [
            'has_discrepancies' => abs($binCount - $expectedBins) > 2,
            'description' => "Expected {$expectedBins} bins, found {$binCount}"
        ];
    }

    private function analyzeResponseTime($agent)
    {
        // Simplified response time analysis
        return [
            'is_unresponsive' => false,
            'description' => 'Response time within acceptable limits'
        ];
    }

    private function calculateComplianceTrends($current, $previous)
    {
        if (!$previous) {
            return ['trend' => 'baseline', 'change' => 0];
        }

        $currentViolations = array_sum(array_column($current, 'violations_found'));
        $previousViolations = $previous['summary']['total_violations'] ?? 0;

        $change = $currentViolations - $previousViolations;
        $trend = $change > 0 ? 'worsening' : ($change < 0 ? 'improving' : 'stable');

        return ['trend' => $trend, 'change' => $change];
    }

    private function generateComplianceRecommendations($complianceResults)
    {
        $recommendations = [];
        
        foreach ($complianceResults as $checkType => $result) {
            if ($result['violations_found'] > 0) {
                $recommendations[] = $this->getRecommendationForCheckType($checkType, $result);
            }
        }
        
        return $recommendations;
    }

    private function getRecommendationForCheckType($checkType, $result)
    {
        $recommendations = [
            'photo_compliance_check' => 'Implement automated photo reminder system and increase training on photo submission requirements',
            'performance_compliance_check' => 'Introduce performance improvement plans and mentor assignment for underperforming agents',
            'inventory_compliance_check' => 'Enhance inventory tracking systems and provide additional training on stock management',
            'delivery_compliance_check' => 'Review delivery routes and provide time management training',
            'behavioral_compliance_check' => 'Implement behavioral monitoring alerts and counseling programs'
        ];
        
        return [
            'check_type' => $checkType,
            'violations_count' => $result['violations_found'],
            'recommendation' => $recommendations[$checkType] ?? 'Review and address identified compliance issues',
            'priority' => $result['violations_found'] > 5 ? 'high' : 'medium'
        ];
    }

    private function suggestNextActions($complianceResults)
    {
        $actions = [];
        
        $totalViolations = array_sum(array_column($complianceResults, 'violations_found'));
        
        if ($totalViolations > 10) {
            $actions[] = 'Schedule immediate management review';
            $actions[] = 'Implement enhanced monitoring protocols';
        }
        
        if ($totalViolations > 20) {
            $actions[] = 'Consider system-wide training program';
            $actions[] = 'Review and update compliance policies';
        }
        
        return $actions;
    }

    private function escalateToManagement($complianceResults)
    {
        Log::warning('Compliance violations escalated to management', $complianceResults);
        return true;
    }

    private function increaseMonitoringFrequency()
    {
        Cache::put('compliance_monitoring_frequency', 'high', now()->addDays(7));
        return true;
    }

    private function triggerSystemWideTraining()
    {
        Log::info('System-wide compliance training triggered');
        return true;
    }
}
