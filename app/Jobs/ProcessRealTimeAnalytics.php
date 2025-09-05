<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\AnalyticsEngineService;
use Illuminate\Support\Facades\Log;

class ProcessRealTimeAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;
    public $backoff = [60, 120, 300]; // Retry delays in seconds

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
    public function handle(AnalyticsEngineService $analyticsService): void
    {
        Log::info('Starting real-time analytics processing job');

        try {
            $startTime = microtime(true);

            // Process real-time analytics
            $analytics = $analyticsService->processRealTimeAnalytics();

            $processingTime = microtime(true) - $startTime;

            Log::info('Real-time analytics processing completed', [
                'processing_time' => round($processingTime, 2) . ' seconds',
                'metrics_processed' => count($analytics),
                'categories' => array_keys($analytics)
            ]);

        } catch (\Exception $e) {
            Log::error('Real-time analytics processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Real-time analytics processing job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Could send notification to administrators here
        // Notification::route('mail', config('analytics.admin_email'))
        //     ->notify(new AnalyticsProcessingFailedNotification($exception));
    }
} 