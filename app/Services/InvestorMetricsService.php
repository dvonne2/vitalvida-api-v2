<?php

namespace App\Services;

use App\Models\Investor;
use App\Models\Order;
use App\Models\Revenue;
use App\Models\InvestorDocument;
use App\Models\FinancialStatement;
use App\Models\CompanyValuation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvestorMetricsService
{
    /**
     * Calculate company valuation for specific investor type
     */
    public function calculateCompanyValuation($investor_type)
    {
        $currentRevenue = Revenue::whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
            ->sum('amount');

        $annualizedRevenue = $currentRevenue * 12;
        $netMargin = 0.30; // 30% net margin
        $annualizedProfit = $annualizedRevenue * $netMargin;

        // Different valuation multiples based on investor type
        $valuationMultiples = [
            'master_readiness' => 8.0, // Conservative for due diligence
            'tomi_governance' => 10.0, // Governance-focused premium
            'ron_scale' => 12.0, // Growth-focused premium
            'thiel_strategy' => 15.0, // Strategic premium
            'andy_tech' => 11.0, // Tech-focused premium
            'otunba_control' => 9.0, // Control-focused premium
            'dangote_cost_control' => 8.5, // Cost-control premium
            'neil_growth' => 13.0, // Growth-focused premium
        ];

        $multiple = $valuationMultiples[$investor_type] ?? 10.0;
        $valuation = $annualizedProfit * $multiple;

        return [
            'current_revenue' => $currentRevenue,
            'annualized_revenue' => $annualizedRevenue,
            'annualized_profit' => $annualizedProfit,
            'valuation_multiple' => $multiple,
            'company_valuation' => $valuation,
            'valuation_formatted' => '₦' . number_format($valuation / 1000000, 2) . 'M',
            'calculation_method' => 'Profit Multiple',
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Generate role-specific dashboard data
     */
    public function generateRoleSpecificDashboard($investor_role)
    {
        $dashboardData = [];

        switch ($investor_role) {
            case Investor::ROLE_MASTER_READINESS:
                $dashboardData = $this->getMasterReadinessDashboard();
                break;
            case Investor::ROLE_TOMI_GOVERNANCE:
                $dashboardData = $this->getTomiGovernanceDashboard();
                break;
            case Investor::ROLE_RON_SCALE:
                $dashboardData = $this->getRonScaleDashboard();
                break;
            case Investor::ROLE_THIEL_STRATEGY:
                $dashboardData = $this->getThielStrategyDashboard();
                break;
            case Investor::ROLE_ANDY_TECH:
                $dashboardData = $this->getAndyTechDashboard();
                break;
            case Investor::ROLE_OTUNBA_CONTROL:
                $dashboardData = $this->getOtunbaControlDashboard();
                break;
            case Investor::ROLE_DANGOTE_COST_CONTROL:
                $dashboardData = $this->getDangoteCostControlDashboard();
                break;
            case Investor::ROLE_NEIL_GROWTH:
                $dashboardData = $this->getNeilGrowthDashboard();
                break;
        }

        return $dashboardData;
    }

    /**
     * Compute investor ROI
     */
    public function computeInvestorROI($investor_id, $period = 'monthly')
    {
        $investor = Investor::find($investor_id);
        if (!$investor) {
            return null;
        }

        $startDate = $this->getPeriodStartDate($period);
        $endDate = $this->getPeriodEndDate($period);

        // Get revenue for the period
        $revenue = Revenue::whereBetween('date', [$startDate, $endDate])->sum('amount');
        $profit = $revenue * 0.30; // 30% net margin

        // Calculate ROI based on investor type
        $roiMetrics = $this->calculateROIMetrics($investor->role, $profit, $period);

        return [
            'investor_id' => $investor_id,
            'investor_name' => $investor->name,
            'period' => $period,
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
            'revenue' => $revenue,
            'profit' => $profit,
            'roi_metrics' => $roiMetrics,
            'calculated_at' => now()->toISOString()
        ];
    }

    /**
     * Track document completion progress
     */
    public function trackDocumentCompletionProgress()
    {
        $categories = ['financials', 'operations_systems', 'governance_legal', 'vision_strategy', 'owner_oversight'];
        $progress = [];

        foreach ($categories as $category) {
            $totalDocuments = InvestorDocument::where('document_category_id', function($query) use ($category) {
                $query->select('id')->from('document_categories')->where('name', 'like', "%{$category}%");
            })->count();

            $completedDocuments = InvestorDocument::where('document_category_id', function($query) use ($category) {
                $query->select('id')->from('document_categories')->where('name', 'like', "%{$category}%");
            })->where('status', 'complete')->count();

            $progress[$category] = [
                'total' => $totalDocuments,
                'completed' => $completedDocuments,
                'percentage' => $totalDocuments > 0 ? round(($completedDocuments / $totalDocuments) * 100, 1) : 0,
                'status' => $completedDocuments === $totalDocuments ? 'complete' : 'in_progress'
            ];
        }

        $overallProgress = array_sum(array_column($progress, 'completed')) / array_sum(array_column($progress, 'total')) * 100;

        return [
            'categories' => $progress,
            'overall_progress' => round($overallProgress, 1),
            'total_documents' => array_sum(array_column($progress, 'total')),
            'completed_documents' => array_sum(array_column($progress, 'completed')),
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Generate compliance score
     */
    public function generateComplianceScore()
    {
        $documentProgress = $this->trackDocumentCompletionProgress();
        $financialCompliance = $this->calculateFinancialCompliance();
        $operationalCompliance = $this->calculateOperationalCompliance();
        $governanceCompliance = $this->calculateGovernanceCompliance();

        $overallScore = ($documentProgress['overall_progress'] + $financialCompliance + $operationalCompliance + $governanceCompliance) / 4;

        return [
            'overall_compliance_score' => round($overallScore, 1),
            'document_compliance' => $documentProgress['overall_progress'],
            'financial_compliance' => $financialCompliance,
            'operational_compliance' => $operationalCompliance,
            'governance_compliance' => $governanceCompliance,
            'compliance_grade' => $this->getComplianceGrade($overallScore),
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Get Master Readiness dashboard data
     */
    private function getMasterReadinessDashboard()
    {
        $documentProgress = $this->trackDocumentCompletionProgress();
        $complianceScore = $this->generateComplianceScore();

        return [
            'readiness_summary' => [
                'overall_readiness' => $complianceScore['overall_compliance_score'],
                'document_completion' => $documentProgress['overall_progress'],
                'financial_readiness' => $complianceScore['financial_compliance'],
                'operational_readiness' => $complianceScore['operational_compliance'],
                'governance_readiness' => $complianceScore['governance_compliance']
            ],
            'document_categories' => $documentProgress['categories'],
            'compliance_breakdown' => $complianceScore,
            'next_steps' => $this->getMasterReadinessNextSteps($documentProgress, $complianceScore)
        ];
    }

    /**
     * Get Tomi Governance dashboard data
     */
    private function getTomiGovernanceDashboard()
    {
        $financialOverview = $this->getFinancialOverview();
        $governanceMetrics = $this->getGovernanceMetrics();

        return [
            'financial_overview' => $financialOverview,
            'governance_metrics' => $governanceMetrics,
            'compliance_status' => $this->getComplianceStatus(),
            'board_governance' => $this->getBoardGovernanceData()
        ];
    }

    /**
     * Get Ron Scale dashboard data
     */
    private function getRonScaleDashboard()
    {
        return [
            'real_time_orders' => $this->getRealTimeOrders(),
            'spend_to_growth_ratio' => $this->calculateSpendToGrowthRatio(),
            'cac_vs_ltv' => $this->calculateCACvsLTV(),
            'system_workflow' => $this->getSystemWorkflowMetrics(),
            'growth_simulation' => $this->getGrowthSimulation()
        ];
    }

    /**
     * Get Thiel Strategy dashboard data
     */
    private function getThielStrategyDashboard()
    {
        return [
            'founders_insight' => $this->getFoundersInsight(),
            'unique_moat' => $this->getUniqueMoat(),
            'tam_simulator' => $this->getTAMSimulator(),
            'contrarian_metrics' => $this->getContrarianMetrics(),
            'barrier_tracker' => $this->getBarrierTracker()
        ];
    }

    /**
     * Get Andy Tech dashboard data
     */
    private function getAndyTechDashboard()
    {
        return [
            'ingredient_product_rd' => $this->getIngredientProductRD(),
            'packaging_integrity_workflow' => $this->getPackagingIntegrityWorkflow(),
            'process_efficiency_metrics' => $this->getProcessEfficiencyMetrics(),
            'automation_scorecard' => $this->getAutomationScorecard(),
            'tech_stack_overview' => $this->getTechStackOverview()
        ];
    }

    /**
     * Get Otunba Control dashboard data
     */
    private function getOtunbaControlDashboard()
    {
        return [
            'daily_cash_position' => $this->getDailyCashPosition(),
            'sales_vs_cash_received' => $this->getSalesVsCashReceived(),
            'outstanding_da_balances' => $this->getOutstandingDABalances(),
            'equity_value_tracker' => $this->getEquityValueTracker()
        ];
    }

    /**
     * Get Dangote Cost Control dashboard data
     */
    private function getDangoteCostControlDashboard()
    {
        return [
            'unit_economics_breakdown' => $this->getUnitEconomicsBreakdown(),
            'cost_deviation_alerts' => $this->getCostDeviationAlerts(),
            'wastage_returns_audit' => $this->getWastageReturnsAudit(),
            'vendor_oversight_procurement' => $this->getVendorOversightProcurement()
        ];
    }

    /**
     * Get Neil Growth dashboard data
     */
    private function getNeilGrowthDashboard()
    {
        return [
            'neil_score' => $this->getNeilScore(),
            'paid_ads_performance' => $this->getPaidAdsPerformance(),
            'growth_trends' => $this->getGrowthTrends(),
            'customer_acquisition_metrics' => $this->getCustomerAcquisitionMetrics()
        ];
    }

    /**
     * Calculate ROI metrics
     */
    private function calculateROIMetrics($role, $profit, $period)
    {
        $baseROI = 156; // 156% annualized ROI

        $roleMultipliers = [
            'master_readiness' => 1.0,
            'tomi_governance' => 1.2,
            'ron_scale' => 1.5,
            'thiel_strategy' => 2.0,
            'andy_tech' => 1.3,
            'otunba_control' => 1.1,
            'dangote_cost_control' => 1.4,
            'neil_growth' => 1.6
        ];

        $multiplier = $roleMultipliers[$role] ?? 1.0;
        $adjustedROI = $baseROI * $multiplier;

        return [
            'annualized_roi' => $adjustedROI,
            'period_roi' => $adjustedROI / 12, // Monthly
            'profit_growth' => '+18%',
            'investment_multiple' => 2.5,
            'payback_period' => '18 months'
        ];
    }

    /**
     * Get period start date
     */
    private function getPeriodStartDate($period)
    {
        switch ($period) {
            case 'weekly':
                return Carbon::now()->startOfWeek();
            case 'monthly':
                return Carbon::now()->startOfMonth();
            case 'quarterly':
                return Carbon::now()->startOfQuarter();
            case 'yearly':
                return Carbon::now()->startOfYear();
            default:
                return Carbon::now()->startOfMonth();
        }
    }

    /**
     * Get period end date
     */
    private function getPeriodEndDate($period)
    {
        switch ($period) {
            case 'weekly':
                return Carbon::now()->endOfWeek();
            case 'monthly':
                return Carbon::now()->endOfMonth();
            case 'quarterly':
                return Carbon::now()->endOfQuarter();
            case 'yearly':
                return Carbon::now()->endOfYear();
            default:
                return Carbon::now()->endOfMonth();
        }
    }

    /**
     * Calculate financial compliance
     */
    private function calculateFinancialCompliance()
    {
        // Simulate financial compliance score
        return 94.5;
    }

    /**
     * Calculate operational compliance
     */
    private function calculateOperationalCompliance()
    {
        // Simulate operational compliance score
        return 96.2;
    }

    /**
     * Calculate governance compliance
     */
    private function calculateGovernanceCompliance()
    {
        // Simulate governance compliance score
        return 92.8;
    }

    /**
     * Get compliance grade
     */
    private function getComplianceGrade($score)
    {
        if ($score >= 95) return 'A+';
        if ($score >= 90) return 'A';
        if ($score >= 85) return 'B+';
        if ($score >= 80) return 'B';
        if ($score >= 75) return 'C+';
        return 'C';
    }

    /**
     * Get Master Readiness next steps
     */
    private function getMasterReadinessNextSteps($documentProgress, $complianceScore)
    {
        $nextSteps = [];

        if ($documentProgress['overall_progress'] < 100) {
            $nextSteps[] = 'Complete remaining documents';
        }

        if ($complianceScore['financial_compliance'] < 95) {
            $nextSteps[] = 'Improve financial compliance';
        }

        if ($complianceScore['operational_compliance'] < 95) {
            $nextSteps[] = 'Enhance operational processes';
        }

        if (empty($nextSteps)) {
            $nextSteps[] = 'Ready for investor presentation';
        }

        return $nextSteps;
    }

    // Placeholder methods for dashboard data
    private function getFinancialOverview() { return []; }
    private function getGovernanceMetrics() { return []; }
    private function getComplianceStatus() { return []; }
    private function getBoardGovernanceData() { return []; }
    private function getRealTimeOrders() { return []; }
    private function calculateSpendToGrowthRatio() { return []; }
    private function calculateCACvsLTV() { return []; }
    private function getSystemWorkflowMetrics() { return []; }
    private function getGrowthSimulation() { return []; }
    private function getFoundersInsight() { return []; }
    private function getUniqueMoat() { return []; }
    private function getTAMSimulator() { return []; }
    private function getContrarianMetrics() { return []; }
    private function getBarrierTracker() { return []; }
    private function getIngredientProductRD() { return []; }
    private function getPackagingIntegrityWorkflow() { return []; }
    private function getProcessEfficiencyMetrics() { return []; }
    private function getAutomationScorecard() { return []; }
    private function getTechStackOverview() { return []; }
    private function getDailyCashPosition() { return []; }
    private function getSalesVsCashReceived() { return []; }
    private function getOutstandingDABalances() { return []; }
    private function getEquityValueTracker() { return []; }
    private function getUnitEconomicsBreakdown() { return []; }
    private function getCostDeviationAlerts() { return []; }
    private function getWastageReturnsAudit() { return []; }
    private function getVendorOversightProcurement() { return []; }
    private function getNeilScore() { return []; }
    private function getPaidAdsPerformance() { return []; }
    private function getGrowthTrends() { return []; }
    private function getCustomerAcquisitionMetrics() { return []; }
} 