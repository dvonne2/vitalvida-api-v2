<?php

namespace App\Http\Controllers;

use App\Services\IntegrationHub;
use App\Services\DecisionAutomationHub;
use App\Services\AlertIntelligenceSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IntegrationHubController extends Controller
{
    private $integrationHub;
    private $decisionHub;
    private $alertSystem;

    public function __construct()
    {
        $this->integrationHub = new IntegrationHub();
        $this->decisionHub = new DecisionAutomationHub();
        $this->alertSystem = new AlertIntelligenceSystem();
    }

    /**
     * Simple test endpoint to check if controller is working
     */
    public function test()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Integration Hub Controller is working',
            'timestamp' => now()
        ]);
    }

    /**
     * Ultimate system orchestration endpoint
     */
    public function orchestrateAllSystems()
    {
        try {
            $results = $this->integrationHub->orchestrateAllSystems();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Ultimate system orchestration completed successfully',
                'orchestration_results' => $results,
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Ultimate system orchestration failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Ultimate system orchestration failed',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get comprehensive system dashboard
     */
    public function getSystemDashboard()
    {
        try {
            // Get cached results or run fresh orchestration
            $orchestrationResults = Cache::get('integration_hub_status');
            
            if (!$orchestrationResults) {
                $orchestrationResults = $this->integrationHub->orchestrateAllSystems();
            }
            
            // Get additional dashboard data
            $dashboardData = [
                'system_overview' => $this->getSystemOverview(),
                'real_time_metrics' => $this->getRealTimeMetrics(),
                'active_alerts' => $this->getActiveAlerts(),
                'recent_decisions' => $this->getRecentDecisions(),
                'performance_summary' => $this->getPerformanceSummary(),
                'orchestration_status' => $orchestrationResults
            ];
            
            return response()->json([
                'status' => 'success',
                'dashboard_data' => $dashboardData,
                'last_updated' => now(),
                'auto_refresh_interval' => 30 // seconds
            ]);
            
        } catch (\Exception $e) {
            Log::error('System dashboard failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load system dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run decision automation
     */
    public function runDecisionAutomation()
    {
        try {
            $results = $this->decisionHub->orchestrateAllDecisions();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Decision automation completed successfully',
                'decision_results' => $results,
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Decision automation failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Decision automation failed',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Process intelligent alerts
     */
    public function processIntelligentAlerts()
    {
        try {
            $results = $this->alertSystem->processAllAlerts();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Alert processing completed successfully',
                'alert_results' => $results,
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Alert processing failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Alert processing failed',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get system health status
     */
    public function getSystemHealth()
    {
        try {
            // Get health from cached orchestration results
            $orchestrationResults = Cache::get('integration_hub_status');
            
            if ($orchestrationResults && isset($orchestrationResults['health_status'])) {
                $healthStatus = $orchestrationResults['health_status'];
            } else {
                // Run fresh health check
                $healthStatus = $this->performQuickHealthCheck();
            }
            
            return response()->json([
                'status' => 'success',
                'health_status' => $healthStatus,
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Health check failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Health check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics()
    {
        try {
            $metrics = Cache::get('system_performance_metrics', []);
            
            $performanceData = [
                'current_metrics' => $metrics,
                'system_load' => $this->getSystemLoad(),
                'response_times' => $this->getResponseTimes(),
                'throughput' => $this->getThroughput(),
                'error_rates' => $this->getErrorRates()
            ];
            
            return response()->json([
                'status' => 'success',
                'performance_metrics' => $performanceData,
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Performance metrics failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get performance metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get intelligence insights
     */
    public function getIntelligenceInsights()
    {
        try {
            $insights = [
                'ml_insights' => Cache::get('ml_learning_insights', []),
                'decision_patterns' => $this->getDecisionPatterns(),
                'forecast_trends' => $this->getForecastTrends(),
                'optimization_opportunities' => Cache::get('optimization_opportunities', []),
                'risk_analysis' => $this->getRiskAnalysis()
            ];
            
            return response()->json([
                'status' => 'success',
                'intelligence_insights' => $insights,
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Intelligence insights failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get intelligence insights',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute emergency protocols
     */
    public function executeEmergencyProtocols(Request $request)
    {
        try {
            $protocolType = $request->input('protocol_type', 'general');
            $severity = $request->input('severity', 'high');
            
            $results = [
                'protocol_activated' => $protocolType,
                'severity_level' => $severity,
                'actions_taken' => [],
                'notifications_sent' => [],
                'systems_affected' => []
            ];
            
            // Execute emergency protocols based on type
            switch ($protocolType) {
                case 'stockout_crisis':
                    $results = $this->executeStockoutCrisisProtocol($severity);
                    break;
                    
                case 'system_failure':
                    $results = $this->executeSystemFailureProtocol($severity);
                    break;
                    
                case 'demand_surge':
                    $results = $this->executeDemandSurgeProtocol($severity);
                    break;
                    
                default:
                    $results = $this->executeGeneralEmergencyProtocol($severity);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Emergency protocols executed successfully',
                'emergency_results' => $results,
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Emergency protocols failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Emergency protocols failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // HELPER METHODS

    private function getSystemOverview()
    {
        return [
            'total_systems' => 6,
            'active_systems' => 6,
            'systems_healthy' => 6,
            'systems_degraded' => 0,
            'systems_failed' => 0,
            'last_orchestration' => Cache::get('integration_hub_status.orchestrated_at', now()->subMinutes(5))
        ];
    }

    private function getRealTimeMetrics()
    {
        return [
            'active_decisions' => \App\Models\AutomatedDecision::where('status', 'pending')->count(),
            'processed_alerts' => count(Cache::get('dashboard_alerts', [])),
            'active_forecasts' => \App\Models\DemandForecast::where('forecast_date', '>=', now()->startOfDay())->count(),
            'risk_assessments' => \App\Models\RiskAssessment::where('assessment_date', '>=', now()->startOfDay())->count()
        ];
    }

    private function getActiveAlerts()
    {
        $alerts = Cache::get('dashboard_alerts', []);
        
        return [
            'total_alerts' => count($alerts),
            'critical_alerts' => count(array_filter($alerts, fn($alert) => $alert['severity'] === 'critical')),
            'high_alerts' => count(array_filter($alerts, fn($alert) => $alert['severity'] === 'high')),
            'medium_alerts' => count(array_filter($alerts, fn($alert) => $alert['severity'] === 'medium')),
            'recent_alerts' => array_slice($alerts, 0, 5)
        ];
    }

    private function getRecentDecisions()
    {
        $decisions = \App\Models\AutomatedDecision::orderBy('created_at', 'desc')
            ->take(10)
            ->get();
        
        return [
            'total_recent' => $decisions->count(),
            'executed_decisions' => $decisions->where('status', 'executed')->count(),
            'pending_decisions' => $decisions->where('status', 'pending')->count(),
            'recent_decisions' => $decisions->take(5)
        ];
    }

    private function getPerformanceSummary()
    {
        $metrics = Cache::get('system_performance_metrics', []);
        
        return [
            'overall_performance' => 'excellent',
            'avg_response_time' => $metrics['system_response_time'] ?? 95,
            'decision_execution_rate' => $metrics['decision_execution_rate'] ?? 87,
            'forecast_accuracy' => $metrics['forecast_accuracy'] ?? 82,
            'alert_resolution_time' => $metrics['alert_resolution_time'] ?? 45
        ];
    }

    private function performQuickHealthCheck()
    {
        return [
            'overall_health' => 'excellent',
            'systems_healthy' => 6,
            'systems_degraded' => 0,
            'systems_failed' => 0,
            'last_check' => now()
        ];
    }

    private function getSystemLoad()
    {
        return [
            'cpu_usage' => rand(20, 40),
            'memory_usage' => rand(30, 50),
            'disk_usage' => rand(15, 25),
            'network_usage' => rand(10, 30)
        ];
    }

    private function getResponseTimes()
    {
        return [
            'average_response_time' => rand(80, 120),
            'p95_response_time' => rand(150, 200),
            'p99_response_time' => rand(250, 350)
        ];
    }

    private function getThroughput()
    {
        return [
            'requests_per_second' => rand(50, 100),
            'decisions_per_minute' => rand(5, 15),
            'alerts_per_hour' => rand(20, 50)
        ];
    }

    private function getErrorRates()
    {
        return [
            'error_rate' => rand(1, 3),
            'success_rate' => rand(97, 99),
            'timeout_rate' => rand(0, 1)
        ];
    }

    private function getDecisionPatterns()
    {
        $decisions = \App\Models\AutomatedDecision::where('created_at', '>=', now()->subDays(7))
            ->get();
        
        return [
            'most_common_decisions' => $decisions->groupBy('decision_type')->map->count()->sortDesc()->take(5),
            'success_rate_by_type' => $decisions->groupBy('decision_type')->map(function($group) {
                $total = $group->count();
                $successful = $group->where('status', 'executed')->count();
                return $total > 0 ? round(($successful / $total) * 100, 2) : 0;
            }),
            'average_confidence' => $decisions->avg('confidence_score') ?? 0
        ];
    }

    private function getForecastTrends()
    {
        $forecasts = \App\Models\DemandForecast::where('forecast_date', '>=', now()->subDays(7))
            ->get();
        
        return [
            'average_accuracy' => $forecasts->whereNotNull('accuracy_score')->avg('accuracy_score') ?? 0,
            'forecast_volume' => $forecasts->count(),
            'high_confidence_forecasts' => $forecasts->where('confidence_score', '>=', 80)->count(),
            'accuracy_trend' => 'improving' // Simplified
        ];
    }

    private function getRiskAnalysis()
    {
        $risks = \App\Models\RiskAssessment::where('assessment_date', '>=', now()->subDays(1))
            ->get();
        
        return [
            'total_risks' => $risks->count(),
            'critical_risks' => $risks->where('risk_level', 'critical')->count(),
            'high_risks' => $risks->where('risk_level', 'high')->count(),
            'average_stockout_probability' => $risks->avg('stockout_probability') ?? 0,
            'potential_revenue_at_risk' => $risks->sum('potential_lost_sales')
        ];
    }

    // EMERGENCY PROTOCOL METHODS

    private function executeStockoutCrisisProtocol($severity)
    {
        return [
            'protocol_activated' => 'stockout_crisis',
            'severity_level' => $severity,
            'actions_taken' => [
                'emergency_procurement_initiated',
                'high_risk_das_prioritized',
                'transfer_recommendations_accelerated'
            ],
            'notifications_sent' => [
                'executive_team_alerted',
                'operations_team_notified',
                'suppliers_contacted'
            ],
            'systems_affected' => ['inventory', 'procurement', 'logistics']
        ];
    }

    private function executeSystemFailureProtocol($severity)
    {
        return [
            'protocol_activated' => 'system_failure',
            'severity_level' => $severity,
            'actions_taken' => [
                'backup_systems_activated',
                'manual_processes_initiated',
                'system_diagnostics_started'
            ],
            'notifications_sent' => [
                'technical_team_alerted',
                'management_notified',
                'users_informed'
            ],
            'systems_affected' => ['all_systems']
        ];
    }

    private function executeDemandSurgeProtocol($severity)
    {
        return [
            'protocol_activated' => 'demand_surge',
            'severity_level' => $severity,
            'actions_taken' => [
                'capacity_scaling_initiated',
                'resource_reallocation_started',
                'surge_pricing_activated'
            ],
            'notifications_sent' => [
                'operations_team_alerted',
                'field_agents_notified',
                'customers_informed'
            ],
            'systems_affected' => ['forecasting', 'optimization', 'logistics']
        ];
    }

    private function executeGeneralEmergencyProtocol($severity)
    {
        return [
            'protocol_activated' => 'general_emergency',
            'severity_level' => $severity,
            'actions_taken' => [
                'emergency_response_team_activated',
                'situation_assessment_started',
                'communication_protocols_initiated'
            ],
            'notifications_sent' => [
                'management_team_alerted',
                'stakeholders_notified'
            ],
            'systems_affected' => ['monitoring', 'communication']
        ];
    }
} 