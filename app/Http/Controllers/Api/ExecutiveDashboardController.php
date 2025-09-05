<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AnalyticsEngineService;
use App\Services\ReportGeneratorService;
use Illuminate\Support\Facades\Cache;

class ExecutiveDashboardController extends Controller
{
    public function __construct(
        private AnalyticsEngineService $analyticsService,
        private ReportGeneratorService $reportService
    ) {}

    /**
     * Get executive dashboard with real-time KPIs
     */
    public function getExecutiveDashboard(Request $request)
    {
        $user = $request->user();
        
        // Verify executive access
        if (!in_array($user->role, ['ceo', 'gm', 'fc'])) {
            return response()->json(['error' => 'Executive access required'], 403);
        }

        // Get cached dashboard data or generate fresh
        $dashboardData = Cache::remember('dashboard:executive', 300, function() {
            return $this->analyticsService->processRealTimeAnalytics();
        });

        return response()->json([
            'dashboard_type' => 'executive',
            'last_updated' => now(),
            'time_period' => [
                'current_date' => now()->toDateString(),
                'current_month' => now()->format('Y-m'),
                'current_year' => now()->year
            ],
            'kpi_summary' => $this->generateKpiSummary($dashboardData),
            'financial_overview' => $this->generateFinancialOverview($dashboardData['financial']),
            'operational_overview' => $this->generateOperationalOverview($dashboardData['operational']),
            'performance_indicators' => $this->generatePerformanceIndicators($dashboardData['performance']),
            'risk_alerts' => $this->generateRiskAlerts($dashboardData['risk']),
            'trend_analysis' => $this->generateTrendAnalysis(),
            'quick_actions' => $this->getQuickActions($user->role)
        ]);
    }

    /**
     * Get financial dashboard with detailed metrics
     */
    public function getFinancialDashboard(Request $request)
    {
        $user = $request->user();
        
        if (!in_array($user->role, ['ceo', 'gm', 'fc', 'accountant'])) {
            return response()->json(['error' => 'Financial access required'], 403);
        }

        $period = $request->get('period', 'month');
        $startDate = $this->getStartDate($period);
        $endDate = now();

        return response()->json([
            'dashboard_type' => 'financial',
            'period' => $period,
            'date_range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString()
            ],
            'revenue_analysis' => $this->getRevenueAnalysis($startDate, $endDate),
            'cost_analysis' => $this->getCostAnalysis($startDate, $endDate),
            'profitability_analysis' => $this->getProfitabilityAnalysis($startDate, $endDate),
            'cash_flow_analysis' => $this->getCashFlowAnalysis($startDate, $endDate),
            'budget_analysis' => $this->getBudgetAnalysis($startDate, $endDate),
            'financial_ratios' => $this->getFinancialRatios($startDate, $endDate),
            'forecast_vs_actual' => $this->getForecastVsActual($startDate, $endDate)
        ]);
    }

    /**
     * Get operational dashboard with performance metrics
     */
    public function getOperationalDashboard(Request $request)
    {
        $user = $request->user();
        
        if (!in_array($user->role, ['ceo', 'gm', 'im', 'accountant'])) {
            return response()->json(['error' => 'Operational access required'], 403);
        }

        $period = $request->get('period', 'month');
        $startDate = $this->getStartDate($period);
        $endDate = now();

        return response()->json([
            'dashboard_type' => 'operational',
            'period' => $period,
            'date_range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString()
            ],
            'logistics_performance' => $this->getLogisticsPerformance($startDate, $endDate),
            'inventory_analytics' => $this->getInventoryAnalytics($startDate, $endDate),
            'employee_performance' => $this->getEmployeePerformanceAnalytics($startDate, $endDate),
            'threshold_compliance' => $this->getThresholdComplianceAnalytics($startDate, $endDate),
            'efficiency_metrics' => $this->getEfficiencyMetrics($startDate, $endDate),
            'cost_optimization' => $this->getCostOptimizationOpportunities($startDate, $endDate)
        ]);
    }

    /**
     * Get predictive analytics dashboard
     */
    public function getPredictiveAnalytics(Request $request)
    {
        $validated = $request->validate([
            'analysis_type' => 'required|in:cost_forecast,revenue_forecast,inventory_demand,employee_performance,risk_assessment',
            'forecast_period' => 'nullable|integer|min:1|max:24',
            'categories' => 'nullable|array',
            'confidence_level' => 'nullable|numeric|min:0.8|max:0.99'
        ]);

        $parameters = [
            'period' => $validated['forecast_period'] ?? 6,
            'categories' => $validated['categories'] ?? [],
            'confidence_level' => $validated['confidence_level'] ?? 0.95
        ];

        $analytics = $this->analyticsService->generatePredictiveAnalytics(
            $validated['analysis_type'],
            $parameters
        );

        return response()->json([
            'analysis_type' => $validated['analysis_type'],
            'parameters' => $parameters,
            'generated_at' => now(),
            'analytics' => $analytics,
            'interpretation' => $this->interpretPredictiveAnalytics($validated['analysis_type'], $analytics),
            'recommendations' => $this->generateRecommendations($validated['analysis_type'], $analytics)
        ]);
    }

    /**
     * Generate comprehensive report
     */
    public function generateReport(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|in:financial,operational,compliance,custom',
            'report_subtype' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'format' => 'nullable|in:json,pdf,excel,csv',
            'template_id' => 'nullable|integer|exists:report_templates,id'
        ]);

        $parameters = [
            'start_date' => $validated['start_date'] ?? now()->startOfMonth(),
            'end_date' => $validated['end_date'] ?? now(),
            'type' => $validated['report_subtype'] ?? 'comprehensive',
            'format' => $validated['format'] ?? 'json',
            'template_id' => $validated['template_id'] ?? null
        ];

        try {
            $report = match($validated['report_type']) {
                'financial' => $this->reportService->generateFinancialReport($parameters),
                'operational' => $this->reportService->generateOperationalReport($parameters),
                'compliance' => $this->reportService->generateComplianceReport($parameters),
                'custom' => $this->reportService->generateCustomReport($parameters),
                default => throw new \InvalidArgumentException('Invalid report type')
            };

            return response()->json([
                'status' => 'success',
                'report' => $report,
                'generated_at' => now()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard configuration
     */
    public function getDashboardConfig(Request $request)
    {
        $user = $request->user();
        $dashboardType = $request->get('type', 'executive');

        $config = [
            'dashboard_type' => $dashboardType,
            'user_role' => $user->role,
            'refresh_interval' => 300, // 5 minutes
            'available_widgets' => $this->getAvailableWidgets($user->role, $dashboardType),
            'default_layout' => $this->getDefaultLayout($dashboardType),
            'customization_options' => $this->getCustomizationOptions($user->role)
        ];

        return response()->json($config);
    }

    /**
     * Save dashboard preferences
     */
    public function saveDashboardPreferences(Request $request)
    {
        $validated = $request->validate([
            'dashboard_type' => 'required|string',
            'layout' => 'required|array',
            'widgets' => 'required|array',
            'refresh_interval' => 'nullable|integer|min:60|max:3600'
        ]);

        $user = $request->user();
        
        // Save user preferences (this would typically go to a user_preferences table)
        Cache::put("dashboard_preferences:{$user->id}:{$validated['dashboard_type']}", $validated, now()->addDays(30));

        return response()->json([
            'status' => 'success',
            'message' => 'Dashboard preferences saved successfully'
        ]);
    }

    private function generateKpiSummary(array $dashboardData): array
    {
        return [
            'revenue' => [
                'current_month' => $dashboardData['financial']['revenue_metrics']['monthly_revenue'] ?? 0,
                'growth_rate' => $dashboardData['financial']['revenue_metrics']['revenue_growth_rate'] ?? 0,
                'trend' => $this->determineTrend($dashboardData['financial']['revenue_metrics']['revenue_growth_rate'] ?? 0)
            ],
            'costs' => [
                'current_month' => $dashboardData['financial']['cost_metrics']['monthly_costs'] ?? 0,
                'vs_budget' => $this->calculateCostVsBudget(),
                'optimization_potential' => $this->calculateOptimizationPotential()
            ],
            'profitability' => [
                'current_month' => $dashboardData['financial']['profitability']['monthly_profit'] ?? 0,
                'margin' => $dashboardData['financial']['profitability']['profit_margin'] ?? 0,
                'trend' => $this->determineProfitTrend()
            ],
            'operational_efficiency' => [
                'delivery_efficiency' => $dashboardData['operational']['logistics_performance']['delivery_efficiency'] ?? 0,
                'threshold_compliance' => $dashboardData['operational']['threshold_compliance']['compliance_rate'] ?? 0,
                'employee_productivity' => $dashboardData['operational']['employee_productivity']['productivity_index'] ?? 0
            ]
        ];
    }

    private function generateFinancialOverview(array $financialData): array
    {
        return [
            'revenue_overview' => [
                'total_revenue' => $financialData['revenue_metrics']['monthly_revenue'] ?? 0,
                'revenue_sources' => $this->getRevenueSourceBreakdown(),
                'growth_analysis' => [
                    'mom_growth' => $financialData['revenue_metrics']['revenue_growth_rate'] ?? 0,
                    'ytd_growth' => $this->calculateYtdGrowth('revenue'),
                    'trend_direction' => $this->determineTrend($financialData['revenue_metrics']['revenue_growth_rate'] ?? 0)
                ]
            ],
            'cost_overview' => [
                'total_costs' => $financialData['cost_metrics']['monthly_costs'] ?? 0,
                'cost_breakdown' => $financialData['cost_metrics']['cost_breakdown'] ?? [],
                'cost_trends' => $this->getCostTrends(),
                'optimization_opportunities' => $this->identifyCostOptimizationOpportunities()
            ],
            'profitability_overview' => [
                'gross_profit' => $financialData['profitability']['monthly_profit'] ?? 0,
                'profit_margin' => $financialData['profitability']['profit_margin'] ?? 0,
                'margin_analysis' => $this->analyzeProfitMargins(),
                'profitability_trends' => $this->getProfitabilityTrends()
            ],
            'cash_flow_overview' => [
                'current_balance' => $financialData['cash_flow']['current_balance'] ?? 0,
                'monthly_inflow' => $financialData['cash_flow']['monthly_inflow'] ?? 0,
                'monthly_outflow' => $financialData['cash_flow']['monthly_outflow'] ?? 0,
                'projected_balance' => $financialData['cash_flow']['projected_balance'] ?? 0,
                'burn_rate' => $this->calculateBurnRate(),
                'runway' => $this->calculateCashRunway()
            ]
        ];
    }

    private function generateOperationalOverview(array $operationalData): array
    {
        return [
            'logistics_overview' => [
                'delivery_metrics' => $operationalData['logistics_performance'] ?? [],
                'cost_efficiency' => $this->calculateLogisticsCostEfficiency(),
                'performance_trends' => $this->getLogisticsPerformanceTrends(),
                'optimization_areas' => $this->identifyLogisticsOptimizationAreas()
            ],
            'inventory_overview' => [
                'current_metrics' => $operationalData['inventory_metrics'] ?? [],
                'turnover_analysis' => $this->analyzeInventoryTurnover(),
                'stock_optimization' => $this->getStockOptimizationInsights(),
                'value_analysis' => $this->analyzeInventoryValue()
            ],
            'compliance_overview' => [
                'threshold_metrics' => $operationalData['threshold_compliance'] ?? [],
                'violation_analysis' => $this->analyzeViolationPatterns(),
                'compliance_trends' => $this->getComplianceTrends(),
                'control_effectiveness' => $this->assessControlEffectiveness()
            ],
            'employee_overview' => [
                'productivity_metrics' => $operationalData['employee_productivity'] ?? [],
                'performance_analysis' => $this->analyzeEmployeePerformance(),
                'bonus_analytics' => $this->getBonusAnalytics(),
                'retention_metrics' => $this->getEmployeeRetentionMetrics()
            ]
        ];
    }

    private function generateRiskAlerts(array $riskData): array
    {
        $alerts = [];

        // Financial risk alerts
        if (($riskData['financial_risk']['cash_flow_risk'] ?? 0) > 0.7) {
            $alerts[] = [
                'type' => 'critical',
                'category' => 'financial',
                'title' => 'High Cash Flow Risk',
                'description' => 'Cash flow risk has exceeded acceptable threshold',
                'severity' => 'high',
                'recommended_action' => 'Review cash flow projections and implement cost controls'
            ];
        }

        // Operational risk alerts
        if (($riskData['operational_risk']['threshold_violation_risk'] ?? 0) > 0.6) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'operational',
                'title' => 'Threshold Violation Risk',
                'description' => 'Increased risk of threshold violations detected',
                'severity' => 'medium',
                'recommended_action' => 'Review threshold settings and approval workflows'
            ];
        }

        // Compliance risk alerts
        if (($riskData['compliance_risk']['policy_compliance_score'] ?? 0) < 0.8) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'compliance',
                'title' => 'Policy Compliance Alert',
                'description' => 'Policy compliance score below target threshold',
                'severity' => 'medium',
                'recommended_action' => 'Conduct policy review and employee training'
            ];
        }

        return $alerts;
    }

    // Helper methods
    private function getStartDate(string $period): \Carbon\Carbon
    {
        return match($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            'year' => now()->subYear(),
            default => now()->subMonth()
        };
    }

    private function determineTrend(float $value): string
    {
        if ($value > 0.05) return 'strong_positive';
        if ($value > 0.01) return 'positive';
        if ($value < -0.05) return 'strong_negative';
        if ($value < -0.01) return 'negative';
        return 'stable';
    }

    private function getQuickActions(string $role): array
    {
        $actions = [
            'view_reports' => ['label' => 'View Reports', 'url' => '/reports'],
            'generate_report' => ['label' => 'Generate Report', 'url' => '/reports/generate'],
            'view_analytics' => ['label' => 'View Analytics', 'url' => '/analytics']
        ];

        if (in_array($role, ['ceo', 'gm'])) {
            $actions['approve_items'] = ['label' => 'Approve Items', 'url' => '/approvals'];
            $actions['view_escalations'] = ['label' => 'View Escalations', 'url' => '/escalations'];
        }

        if ($role === 'fc') {
            $actions['financial_reports'] = ['label' => 'Financial Reports', 'url' => '/reports/financial'];
            $actions['budget_analysis'] = ['label' => 'Budget Analysis', 'url' => '/analytics/budget'];
        }

        return $actions;
    }

    // Placeholder methods for additional functionality
    private function generatePerformanceIndicators(array $performance): array { return []; }
    private function generateTrendAnalysis(): array { return []; }
    private function getRevenueAnalysis($startDate, $endDate): array { return []; }
    private function getCostAnalysis($startDate, $endDate): array { return []; }
    private function getProfitabilityAnalysis($startDate, $endDate): array { return []; }
    private function getCashFlowAnalysis($startDate, $endDate): array { return []; }
    private function getBudgetAnalysis($startDate, $endDate): array { return []; }
    private function getFinancialRatios($startDate, $endDate): array { return []; }
    private function getForecastVsActual($startDate, $endDate): array { return []; }
    private function getLogisticsPerformance($startDate, $endDate): array { return []; }
    private function getInventoryAnalytics($startDate, $endDate): array { return []; }
    private function getEmployeePerformanceAnalytics($startDate, $endDate): array { return []; }
    private function getThresholdComplianceAnalytics($startDate, $endDate): array { return []; }
    private function getEfficiencyMetrics($startDate, $endDate): array { return []; }
    private function getCostOptimizationOpportunities($startDate, $endDate): array { return []; }
    private function interpretPredictiveAnalytics(string $type, array $analytics): array { return []; }
    private function generateRecommendations(string $type, array $analytics): array { return []; }
    private function getAvailableWidgets(string $role, string $dashboardType): array { return []; }
    private function getDefaultLayout(string $dashboardType): array { return []; }
    private function getCustomizationOptions(string $role): array { return []; }
    private function calculateCostVsBudget(): float { return 0; }
    private function calculateOptimizationPotential(): float { return 0; }
    private function determineProfitTrend(): string { return 'stable'; }
    private function getRevenueSourceBreakdown(): array { return []; }
    private function calculateYtdGrowth(string $metric): float { return 0; }
    private function getCostTrends(): array { return []; }
    private function identifyCostOptimizationOpportunities(): array { return []; }
    private function analyzeProfitMargins(): array { return []; }
    private function getProfitabilityTrends(): array { return []; }
    private function calculateBurnRate(): float { return 0; }
    private function calculateCashRunway(): int { return 0; }
    private function calculateLogisticsCostEfficiency(): float { return 0; }
    private function getLogisticsPerformanceTrends(): array { return []; }
    private function identifyLogisticsOptimizationAreas(): array { return []; }
    private function analyzeInventoryTurnover(): array { return []; }
    private function getStockOptimizationInsights(): array { return []; }
    private function analyzeInventoryValue(): array { return []; }
    private function analyzeViolationPatterns(): array { return []; }
    private function getComplianceTrends(): array { return []; }
    private function assessControlEffectiveness(): float { return 0; }
    private function analyzeEmployeePerformance(): array { return []; }
    private function getBonusAnalytics(): array { return []; }
    private function getEmployeeRetentionMetrics(): array { return []; }
} 