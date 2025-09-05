<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Report;
use App\Models\AnalyticsMetric;
use App\Models\ReportTemplate;
use App\Models\PredictiveModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupOldReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900; // 15 minutes
    public $tries = 2;
    public $backoff = [300, 600]; // Retry delays in seconds

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
    public function handle(): void
    {
        Log::info('Starting cleanup of old reports and analytics data');

        try {
            $startTime = microtime(true);

            $cleanupStats = [
                'reports_deleted' => 0,
                'metrics_deleted' => 0,
                'templates_deleted' => 0,
                'models_deleted' => 0,
                'files_deleted' => 0,
                'storage_freed' => 0
            ];

            // Cleanup old reports
            $cleanupStats = $this->cleanupOldReports($cleanupStats);

            // Cleanup old analytics metrics
            $cleanupStats = $this->cleanupOldMetrics($cleanupStats);

            // Cleanup old report templates
            $cleanupStats = $this->cleanupOldTemplates($cleanupStats);

            // Cleanup old predictive models
            $cleanupStats = $this->cleanupOldModels($cleanupStats);

            // Cleanup old files
            $cleanupStats = $this->cleanupOldFiles($cleanupStats);

            $processingTime = microtime(true) - $startTime;

            Log::info('Cleanup of old reports and analytics data completed', [
                'processing_time' => round($processingTime, 2) . ' seconds',
                'stats' => $cleanupStats
            ]);

        } catch (\Exception $e) {
            Log::error('Cleanup of old reports and analytics data failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Cleanup old reports
     */
    private function cleanupOldReports(array $stats): array
    {
        $oldReports = Report::forCleanup(30)->get(); // Reports older than 30 days

        foreach ($oldReports as $report) {
            try {
                // Delete associated file if exists
                if ($report->file_path && Storage::exists($report->file_path)) {
                    Storage::delete($report->file_path);
                    $stats['files_deleted']++;
                    $stats['storage_freed'] += $report->file_size ?? 0;
                }

                $report->delete();
                $stats['reports_deleted']++;

            } catch (\Exception $e) {
                Log::warning('Failed to cleanup report', [
                    'report_id' => $report->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Cleaned up old reports', [
            'deleted_count' => $stats['reports_deleted'],
            'files_deleted' => $stats['files_deleted'],
            'storage_freed' => $stats['storage_freed']
        ]);

        return $stats;
    }

    /**
     * Cleanup old analytics metrics
     */
    private function cleanupOldMetrics(array $stats): array
    {
        // Delete real-time metrics older than 7 days
        $oldRealTimeMetrics = AnalyticsMetric::where('aggregation_level', 'real_time')
            ->where('recorded_at', '<', now()->subDays(7))
            ->delete();

        // Delete hourly metrics older than 30 days
        $oldHourlyMetrics = AnalyticsMetric::where('aggregation_level', 'hourly')
            ->where('recorded_at', '<', now()->subDays(30))
            ->delete();

        // Delete daily metrics older than 1 year
        $oldDailyMetrics = AnalyticsMetric::where('aggregation_level', 'daily')
            ->where('recorded_at', '<', now()->subYear())
            ->delete();

        $stats['metrics_deleted'] = $oldRealTimeMetrics + $oldHourlyMetrics + $oldDailyMetrics;

        Log::info('Cleaned up old analytics metrics', [
            'real_time_deleted' => $oldRealTimeMetrics,
            'hourly_deleted' => $oldHourlyMetrics,
            'daily_deleted' => $oldDailyMetrics,
            'total_deleted' => $stats['metrics_deleted']
        ]);

        return $stats;
    }

    /**
     * Cleanup old report templates
     */
    private function cleanupOldTemplates(array $stats): array
    {
        $oldTemplates = ReportTemplate::forCleanup(90)->get(); // Templates inactive for 90 days

        foreach ($oldTemplates as $template) {
            try {
                $template->delete();
                $stats['templates_deleted']++;

            } catch (\Exception $e) {
                Log::warning('Failed to cleanup template', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Cleaned up old report templates', [
            'deleted_count' => $stats['templates_deleted']
        ]);

        return $stats;
    }

    /**
     * Cleanup old predictive models
     */
    private function cleanupOldModels(array $stats): array
    {
        $oldModels = PredictiveModel::forCleanup(90)->get(); // Models inactive for 90 days

        foreach ($oldModels as $model) {
            try {
                $model->delete();
                $stats['models_deleted']++;

            } catch (\Exception $e) {
                Log::warning('Failed to cleanup model', [
                    'model_id' => $model->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Cleaned up old predictive models', [
            'deleted_count' => $stats['models_deleted']
        ]);

        return $stats;
    }

    /**
     * Cleanup old files
     */
    private function cleanupOldFiles(array $stats): array
    {
        try {
            // Cleanup old report files
            $reportFiles = Storage::files('reports');
            $oldReportFiles = array_filter($reportFiles, function($file) {
                $fileTime = Storage::lastModified($file);
                return $fileTime < now()->subDays(30)->timestamp;
            });

            foreach ($oldReportFiles as $file) {
                try {
                    $fileSize = Storage::size($file);
                    Storage::delete($file);
                    $stats['files_deleted']++;
                    $stats['storage_freed'] += $fileSize;

                } catch (\Exception $e) {
                    Log::warning('Failed to delete old report file', [
                        'file' => $file,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Cleanup old analytics cache files
            $cacheFiles = Storage::files('analytics/cache');
            $oldCacheFiles = array_filter($cacheFiles, function($file) {
                $fileTime = Storage::lastModified($file);
                return $fileTime < now()->subDays(7)->timestamp;
            });

            foreach ($oldCacheFiles as $file) {
                try {
                    $fileSize = Storage::size($file);
                    Storage::delete($file);
                    $stats['files_deleted']++;
                    $stats['storage_freed'] += $fileSize;

                } catch (\Exception $e) {
                    Log::warning('Failed to delete old cache file', [
                        'file' => $file,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Cleaned up old files', [
                'files_deleted' => $stats['files_deleted'],
                'storage_freed' => $stats['storage_freed']
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to cleanup old files', [
                'error' => $e->getMessage()
            ]);
        }

        return $stats;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Cleanup of old reports and analytics data failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Could send notification to administrators here
        // Notification::route('mail', config('analytics.admin_email'))
        //     ->notify(new CleanupFailedNotification($exception));
    }
} 