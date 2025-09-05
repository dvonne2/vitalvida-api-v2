<?php

namespace App\Jobs;

use App\Models\ApiKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupExpiredApiKeys implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60; // 1 minute
    public $tries = 3;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get expired API keys
            $expiredKeys = ApiKey::where('expires_at', '<', now())
                ->where('is_active', true)
                ->get();

            if ($expiredKeys->isEmpty()) {
                Log::info('No expired API keys found for cleanup');
                return;
            }

            Log::info("Cleaning up {$expiredKeys->count()} expired API keys");

            $deactivatedCount = 0;
            $deletedCount = 0;

            foreach ($expiredKeys as $key) {
                try {
                    // Check if key was used recently (within last 7 days)
                    $recentlyUsed = $key->last_used_at && 
                        $key->last_used_at->isAfter(now()->subDays(7));

                    if ($recentlyUsed) {
                        // Deactivate but keep for audit
                        $key->update(['is_active' => false]);
                        $deactivatedCount++;
                        
                        Log::info('Deactivated expired API key', [
                            'key_id' => $key->id,
                            'user_id' => $key->user_id,
                            'last_used' => $key->last_used_at
                        ]);
                    } else {
                        // Delete old unused keys
                        $key->delete();
                        $deletedCount++;
                        
                        Log::info('Deleted expired API key', [
                            'key_id' => $key->id,
                            'user_id' => $key->user_id
                        ]);
                    }

                } catch (\Exception $e) {
                    Log::error('Failed to cleanup API key', [
                        'key_id' => $key->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('API key cleanup completed', [
                'deactivated' => $deactivatedCount,
                'deleted' => $deletedCount,
                'total_processed' => $expiredKeys->count()
            ]);

        } catch (\Exception $e) {
            Log::error('API key cleanup job failed', [
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('API key cleanup job failed', [
            'error' => $exception->getMessage(),
            'job' => $this
        ]);
    }
} 