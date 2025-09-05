<?php

namespace App\Jobs;

use App\Models\SyncJob;
use App\Services\MobileSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMobileSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    /**
     * Execute the job.
     */
    public function handle(MobileSyncService $syncService): void
    {
        try {
            // Get pending sync jobs
            $pendingJobs = SyncJob::where('status', 'pending')
                ->where('retry_count', '<', 3)
                ->orderBy('created_at', 'asc')
                ->limit(50)
                ->get();

            if ($pendingJobs->isEmpty()) {
                Log::info('No pending mobile sync jobs found');
                return;
            }

            Log::info("Processing {$pendingJobs->count()} mobile sync jobs");

            foreach ($pendingJobs as $job) {
                try {
                    // Mark job as processing
                    $job->update(['status' => 'processing']);

                    // Process the sync job
                    $result = $this->processSyncJob($job, $syncService);

                    if ($result['success']) {
                        $job->update([
                            'status' => 'completed',
                            'processed_at' => now()
                        ]);
                    } else {
                        $job->update([
                            'status' => 'failed',
                            'error_message' => $result['error'],
                            'retry_count' => $job->retry_count + 1,
                            'processed_at' => now()
                        ]);
                    }

                } catch (\Exception $e) {
                    Log::error('Mobile sync job processing failed', [
                        'job_id' => $job->id,
                        'error' => $e->getMessage()
                    ]);

                    $job->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'retry_count' => $job->retry_count + 1,
                        'processed_at' => now()
                    ]);
                }
            }

            Log::info('Mobile sync job processing completed');

        } catch (\Exception $e) {
            Log::error('Mobile sync job processing failed', [
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Process individual sync job
     */
    private function processSyncJob(SyncJob $job, MobileSyncService $syncService): array
    {
        $data = [
            'device_id' => $job->device_id,
            'sync_items' => [
                [
                    'entity_type' => $job->entity_type,
                    'entity_id' => $job->entity_id,
                    'action' => $job->action,
                    'data' => [], // This would be populated from job data
                    'version' => 1
                ]
            ]
        ];

        return $syncService->handleSyncRequest('POST', $data);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Mobile sync job failed', [
            'error' => $exception->getMessage(),
            'job' => $this
        ]);
    }
} 