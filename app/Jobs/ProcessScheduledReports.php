<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Report;
use App\Services\ReportGeneratorService;
use Illuminate\Support\Facades\Log;

class ProcessScheduledReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 3;
    public $backoff = [120, 300, 600]; // Retry delays in seconds

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(ReportGeneratorService $reportService): void
    {
        Log::info('Starting scheduled reports processing job');

        try {
            $startTime = microtime(true);

            // Get reports that need to be generated
            $pendingReports = Report::pendingGeneration()->get();

            Log::info('Found pending reports for generation', [
                'count' => $pendingReports->count()
            ]);

            $processedCount = 0;
            $failedCount = 0;

            foreach ($pendingReports as $report) {
                try {
                    $this->processReport($report, $reportService);
                    $processedCount++;

                    Log::info('Successfully processed scheduled report', [
                        'report_id' => $report->id,
                        'report_name' => $report->report_name,
                        'report_type' => $report->report_type
                    ]);

                } catch (\Exception $e) {
                    $failedCount++;
                    $report->update(['status' => 'failed']);

                    Log::error('Failed to process scheduled report', [
                        'report_id' => $report->id,
                        'report_name' => $report->report_name,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $processingTime = microtime(true) - $startTime;

            Log::info('Scheduled reports processing completed', [
                'processing_time' => round($processingTime, 2) . ' seconds',
                'total_reports' => $pendingReports->count(),
                'processed_count' => $processedCount,
                'failed_count' => $failedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Scheduled reports processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Process individual report
     */
    private function processReport(Report $report, ReportGeneratorService $reportService): void
    {
        $config = $report->report_config;
        $parameters = [
            'start_date' => $config['start_date'] ?? now()->startOfMonth(),
            'end_date' => $config['end_date'] ?? now(),
            'type' => $config['type'] ?? 'comprehensive',
            'format' => $config['format'] ?? 'json',
            'template_id' => $config['template_id'] ?? null
        ];

        // Generate report based on type
        $generatedReport = match($report->report_type) {
            'financial' => $reportService->generateFinancialReport($parameters),
            'operational' => $reportService->generateOperationalReport($parameters),
            'compliance' => $reportService->generateComplianceReport($parameters),
            'custom' => $reportService->generateCustomReport($parameters),
            default => throw new \InvalidArgumentException("Unknown report type: {$report->report_type}")
        };

        // Update report with generated data
        $report->update([
            'report_data' => $generatedReport,
            'status' => 'completed',
            'generated_at' => now(),
            'file_size' => $this->calculateFileSize($generatedReport),
            'expires_at' => $this->calculateExpiryDate($config)
        ]);
    }

    /**
     * Calculate file size for report
     */
    private function calculateFileSize(array $reportData): int
    {
        return strlen(json_encode($reportData));
    }

    /**
     * Calculate expiry date for report
     */
    private function calculateExpiryDate(array $config): \Carbon\Carbon
    {
        $retentionDays = $config['retention_days'] ?? 30;
        return now()->addDays($retentionDays);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Scheduled reports processing job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Could send notification to administrators here
        // Notification::route('mail', config('reports.admin_email'))
        //     ->notify(new ScheduledReportsProcessingFailedNotification($exception));
    }
} 