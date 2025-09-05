<?php

namespace App\Jobs;

use App\Services\MobilePushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledPushNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 minutes
    public $tries = 3;

    /**
     * Execute the job.
     */
    public function handle(MobilePushNotificationService $notificationService): void
    {
        try {
            // Get scheduled notifications that are due
            $scheduledNotifications = $this->getScheduledNotifications();

            if ($scheduledNotifications->isEmpty()) {
                Log::info('No scheduled push notifications found');
                return;
            }

            Log::info("Processing {$scheduledNotifications->count()} scheduled push notifications");

            foreach ($scheduledNotifications as $notification) {
                try {
                    // Send the notification
                    $result = $notificationService->sendToUser(
                        $notification->user_id,
                        $notification->title,
                        $notification->body,
                        $notification->data ?? []
                    );

                    if ($result['success']) {
                        $notification->update([
                            'status' => 'sent',
                            'sent_at' => now()
                        ]);

                        Log::info('Scheduled push notification sent successfully', [
                            'notification_id' => $notification->id,
                            'user_id' => $notification->user_id
                        ]);
                    } else {
                        $notification->update([
                            'status' => 'failed',
                            'error_message' => $result['error'] ?? 'Unknown error'
                        ]);

                        Log::error('Scheduled push notification failed', [
                            'notification_id' => $notification->id,
                            'error' => $result['error']
                        ]);
                    }

                } catch (\Exception $e) {
                    Log::error('Scheduled push notification processing failed', [
                        'notification_id' => $notification->id,
                        'error' => $e->getMessage()
                    ]);

                    $notification->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Scheduled push notification processing completed');

        } catch (\Exception $e) {
            Log::error('Scheduled push notification job failed', [
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get scheduled notifications that are due
     */
    private function getScheduledNotifications()
    {
        return \App\Models\PushNotification::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->where('scheduled_at', '>', now()->subMinutes(5)) // Only process recent ones
            ->orderBy('scheduled_at', 'asc')
            ->limit(100)
            ->get();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Scheduled push notification job failed', [
            'error' => $exception->getMessage(),
            'job' => $this
        ]);
    }
} 