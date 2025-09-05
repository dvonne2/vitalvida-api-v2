<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\DashboardMetricsService;
use App\Services\RevenueAnalyticsService;
use App\Services\RiskAssessmentService;
use App\Models\Order;
use App\Models\Revenue;
use App\Models\DepartmentPerformance;
use App\Models\Alert;
use App\Models\Decision;
use App\Models\Risk;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    protected $dashboardService;
    protected $revenueService;
    protected $riskService;

    public function __construct(
        DashboardMetricsService $dashboardService,
        RevenueAnalyticsService $revenueService,
        RiskAssessmentService $riskService
    ) {
        $this->dashboardService = $dashboardService;
        $this->revenueService = $revenueService;
        $this->riskService = $riskService;
    }

    /**
     * Export dashboard summary
     */
    public function exportDashboardSummary(Request $request): JsonResponse
    {
        try {
            $format = $request->get('format', 'csv');
            $dateRange = $request->get('date_range', 'last_30_days');
            
            $dashboardData = $this->dashboardService->getDashboardData();
            $revenueData = $this->revenueService->getRevenueAnalytics();
            $riskData = $this->riskService->getRiskAssessment();
            
            $exportData = [
                'dashboard_metrics' => $dashboardData,
                'revenue_analytics' => $revenueData,
                'risk_assessment' => $riskData,
                'export_date' => now()->toISOString(),
                'date_range' => $dateRange
            ];
            
            $filename = "dashboard_summary_{$dateRange}_" . now()->format('Y-m-d_H-i-s');
            
            if ($format === 'csv') {
                $csvContent = $this->generateCSV($exportData);
                $filepath = "exports/{$filename}.csv";
                Storage::put($filepath, $csvContent);
            } elseif ($format === 'json') {
                $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT);
                $filepath = "exports/{$filename}.json";
                Storage::put($filepath, $jsonContent);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unsupported format'
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'format' => $format,
                    'download_url' => "/api/ceo/export/download/{$filename}.{$format}",
                    'file_size' => Storage::size($filepath) . ' bytes'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export dashboard summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export performance report
     */
    public function exportPerformanceReport(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'monthly');
            $format = $request->get('format', 'csv');
            
            $performanceData = DepartmentPerformance::with('department')
                ->where('measurement_date', '>=', Carbon::now()->subDays(30))
                ->get()
                ->groupBy('department.name');
            
            $exportData = [];
            foreach ($performanceData as $department => $metrics) {
                $departmentData = [
                    'department' => $department,
                    'metrics' => []
                ];
                
                foreach ($metrics as $metric) {
                    $departmentData['metrics'][] = [
                        'metric' => $metric->metric_name,
                        'target' => $metric->target_value,
                        'actual' => $metric->actual_value,
                        'status' => $metric->status,
                        'trend' => $metric->trend,
                        'performance_score' => $metric->performance_score
                    ];
                }
                
                $exportData[] = $departmentData;
            }
            
            $filename = "performance_report_{$period}_" . now()->format('Y-m-d_H-i-s');
            
            if ($format === 'csv') {
                $csvContent = $this->generatePerformanceCSV($exportData);
                $filepath = "exports/{$filename}.csv";
                Storage::put($filepath, $csvContent);
            } else {
                $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT);
                $filepath = "exports/{$filename}.json";
                Storage::put($filepath, $jsonContent);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'format' => $format,
                    'download_url' => "/api/ceo/export/download/{$filename}.{$format}",
                    'records' => count($exportData)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export performance report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export financial statements
     */
    public function exportFinancialStatements(Request $request): JsonResponse
    {
        try {
            $month = $request->get('month', Carbon::now()->format('Y-m'));
            $format = $request->get('format', 'csv');
            
            $revenueData = $this->revenueService->getRevenueAnalytics();
            $financialData = [
                'revenue_analytics' => $revenueData,
                'month' => $month,
                'export_date' => now()->toISOString()
            ];
            
            $filename = "financial_statements_{$month}_" . now()->format('Y-m-d_H-i-s');
            
            if ($format === 'csv') {
                $csvContent = $this->generateFinancialCSV($financialData);
                $filepath = "exports/{$filename}.csv";
                Storage::put($filepath, $csvContent);
            } else {
                $jsonContent = json_encode($financialData, JSON_PRETTY_PRINT);
                $filepath = "exports/{$filename}.json";
                Storage::put($filepath, $jsonContent);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'format' => $format,
                    'download_url' => "/api/ceo/export/download/{$filename}.{$format}",
                    'month' => $month
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export financial statements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export alert history
     */
    public function exportAlertHistory(Request $request): JsonResponse
    {
        try {
            $dateRange = $request->get('date_range', 'last_30_days');
            $format = $request->get('format', 'csv');
            
            $startDate = Carbon::now()->subDays(30);
            if ($dateRange === 'last_7_days') {
                $startDate = Carbon::now()->subDays(7);
            } elseif ($dateRange === 'last_90_days') {
                $startDate = Carbon::now()->subDays(90);
            }
            
            $alerts = Alert::where('created_at', '>=', $startDate)
                ->orderBy('created_at', 'desc')
                ->get();
            
            $exportData = $alerts->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'type' => $alert->type,
                    'title' => $alert->title,
                    'severity' => $alert->severity,
                    'department' => $alert->department,
                    'action' => $alert->action,
                    'created_at' => $alert->created_at->format('Y-m-d H:i:s'),
                    'status' => $alert->status
                ];
            })->toArray();
            
            $filename = "alert_history_{$dateRange}_" . now()->format('Y-m-d_H-i-s');
            
            if ($format === 'csv') {
                $csvContent = $this->generateAlertCSV($exportData);
                $filepath = "exports/{$filename}.csv";
                Storage::put($filepath, $csvContent);
            } else {
                $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT);
                $filepath = "exports/{$filename}.json";
                Storage::put($filepath, $jsonContent);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'format' => $format,
                    'download_url' => "/api/ceo/export/download/{$filename}.{$format}",
                    'records' => count($exportData)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export alert history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export experiment results
     */
    public function exportExperimentResults(Request $request): JsonResponse
    {
        try {
            $format = $request->get('format', 'csv');
            
            $experiments = \App\Models\Experiment::orderBy('created_at', 'desc')->get();
            
            $exportData = $experiments->map(function ($experiment) {
                return [
                    'id' => $experiment->id,
                    'idea' => $experiment->idea,
                    'channel' => $experiment->channel,
                    'start_date' => $experiment->start_date,
                    'owner' => $experiment->owner,
                    'status' => $experiment->status,
                    'result' => $experiment->result,
                    'verdict' => $experiment->verdict,
                    'budget_allocated' => $experiment->budget_allocated,
                    'roi' => $experiment->roi
                ];
            })->toArray();
            
            $filename = "experiment_results_" . now()->format('Y-m-d_H-i-s');
            
            if ($format === 'csv') {
                $csvContent = $this->generateExperimentCSV($exportData);
                $filepath = "exports/{$filename}.csv";
                Storage::put($filepath, $csvContent);
            } else {
                $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT);
                $filepath = "exports/{$filename}.json";
                Storage::put($filepath, $jsonContent);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'format' => $format,
                    'download_url' => "/api/ceo/export/download/{$filename}.{$format}",
                    'records' => count($exportData)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export experiment results',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download exported file
     */
    public function downloadFile(string $filename): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $filepath = "exports/{$filename}";
            
            if (!Storage::exists($filepath)) {
                abort(404, 'File not found');
            }
            
            return Storage::download($filepath);
        } catch (\Exception $e) {
            abort(500, 'Download failed');
        }
    }

    /**
     * Generate CSV content for dashboard data
     */
    private function generateCSV(array $data): string
    {
        $csv = "Dashboard Summary Report\n";
        $csv .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";
        
        // Add metrics
        $csv .= "Metrics,Value,Status\n";
        if (isset($data['dashboard_metrics']['monthly_growth'])) {
            $csv .= "Monthly Orders," . $data['dashboard_metrics']['monthly_growth']['orders']['current'] . ",Active\n";
            $csv .= "Revenue Growth," . $data['dashboard_metrics']['monthly_growth']['revenue']['growth_percentage'] . "%,Active\n";
        }
        
        return $csv;
    }

    /**
     * Generate CSV content for performance data
     */
    private function generatePerformanceCSV(array $data): string
    {
        $csv = "Department Performance Report\n";
        $csv .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";
        
        $csv .= "Department,Metric,Target,Actual,Status,Trend,Score\n";
        
        foreach ($data as $department) {
            foreach ($department['metrics'] as $metric) {
                $csv .= "{$department['department']},{$metric['metric']},{$metric['target']},{$metric['actual']},{$metric['status']},{$metric['trend']},{$metric['performance_score']}\n";
            }
        }
        
        return $csv;
    }

    /**
     * Generate CSV content for financial data
     */
    private function generateFinancialCSV(array $data): string
    {
        $csv = "Financial Statements Report\n";
        $csv .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";
        
        if (isset($data['revenue_analytics']['net_margin'])) {
            $margin = $data['revenue_analytics']['net_margin'];
            $csv .= "Revenue MTD,₦{$margin['revenue_mtd']}\n";
            $csv .= "Net Profit,₦{$margin['net_profit']}\n";
            $csv .= "Net Margin,{$margin['net_margin_percentage']}%\n";
        }
        
        return $csv;
    }

    /**
     * Generate CSV content for alert data
     */
    private function generateAlertCSV(array $data): string
    {
        $csv = "Alert History Report\n";
        $csv .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";
        
        $csv .= "ID,Type,Title,Severity,Department,Action,Created At,Status\n";
        
        foreach ($data as $alert) {
            $csv .= "{$alert['id']},{$alert['type']},{$alert['title']},{$alert['severity']},{$alert['department']},{$alert['action']},{$alert['created_at']},{$alert['status']}\n";
        }
        
        return $csv;
    }

    /**
     * Generate CSV content for experiment data
     */
    private function generateExperimentCSV(array $data): string
    {
        $csv = "Experiment Results Report\n";
        $csv .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";
        
        $csv .= "ID,Idea,Channel,Start Date,Owner,Status,Result,Verdict,Budget,ROI\n";
        
        foreach ($data as $experiment) {
            $csv .= "{$experiment['id']},{$experiment['idea']},{$experiment['channel']},{$experiment['start_date']},{$experiment['owner']},{$experiment['status']},{$experiment['result']},{$experiment['verdict']},₦{$experiment['budget_allocated']},{$experiment['roi']}%\n";
        }
        
        return $csv;
    }
} 