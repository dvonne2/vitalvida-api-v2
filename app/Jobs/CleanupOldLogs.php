<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\LogCleanupService;
use App\Models\SystemLog;

class CleanupOldLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
    public function handle(LogCleanupService $cleanupService): void
    {
        try {
            $results = $cleanupService->cleanupOldLogs();
            
            // Log the cleanup results
            SystemLog::create([
                'level' => 'info',
                'message' => 'Automated log cleanup completed',
                'context' => $results,
                'source' => 'cleanup_job'
            ]);

        } catch (\Exception $e) {
            // Log the error
            SystemLog::create([
                'level' => 'error',
                'message' => 'Log cleanup job failed: ' . $e->getMessage(),
                'context' => [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ],
                'source' => 'cleanup_job'
            ]);

            throw $e;
        }
    }
} 