<?php

namespace App\Jobs;

use App\Models\ApiRequest;
use App\Models\DeviceToken;
use App\Models\PushNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorMobileAppHealth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 minutes
    public $tries = 3;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $healthMetrics = $this->collectHealthMetrics();
            $this->analyzeHealthMetrics($healthMetrics);
            $this->generateHealthReport($healthMetrics);

            Log::info('Mobile app health monitoring completed', $healthMetrics);

        } catch (\Exception $e) {
            Log::error('Mobile app health monitoring failed', [
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Collect health metrics
     */
    private function collectHealthMetrics(): array
    {
        $now = now();
        $lastHour = $now->subHour();
        $lastDay = $now->subDay();

        // API request metrics
        $totalRequests = ApiRequest::where('created_at', '>=', $lastHour)->count();
        $successfulRequests = ApiRequest::where('created_at', '>=', $lastHour)
            ->where('status', 'success')
            ->count();
        $errorRequests = ApiRequest::where('created_at', '>=', $lastHour)
            ->where('status', 'error')
            ->count();
        $cacheHits = ApiRequest::where('created_at', '>=', $lastHour)
            ->where('status', 'cache_hit')
            ->count();

        // Device token metrics
        $totalDevices = DeviceToken::where('is_active', true)->count();
        $recentDevices = DeviceToken::where('last_used_at', '>=', $lastDay)->count();
        $inactiveDevices = DeviceToken::where('is_active', true)
            ->where('last_used_at', '<', $lastDay)
            ->count();

        // Push notification metrics
        $totalNotifications = PushNotification::where('created_at', '>=', $lastHour)->count();
        $sentNotifications = PushNotification::where('created_at', '>=', $lastHour)
            ->where('status', 'sent')
            ->count();
        $deliveredNotifications = PushNotification::where('created_at', '>=', $lastHour)
            ->where('status', 'delivered')
            ->count();
        $failedNotifications = PushNotification::where('created_at', '>=', $lastHour)
            ->where('status', 'failed')
            ->count();

        // Performance metrics
        $avgResponseTime = ApiRequest::where('created_at', '>=', $lastHour)
            ->where('response_time', '>', 0)
            ->avg('response_time');

        return [
            'timestamp' => $now->toISOString(),
            'period' => 'last_hour',
            'api_requests' => [
                'total' => $totalRequests,
                'successful' => $successfulRequests,
                'errors' => $errorRequests,
                'cache_hits' => $cacheHits,
                'success_rate' => $totalRequests > 0 ? ($successfulRequests / $totalRequests) * 100 : 0,
                'error_rate' => $totalRequests > 0 ? ($errorRequests / $totalRequests) * 100 : 0,
                'cache_hit_rate' => $totalRequests > 0 ? ($cacheHits / $totalRequests) * 100 : 0
            ],
            'device_tokens' => [
                'total_active' => $totalDevices,
                'recent_activity' => $recentDevices,
                'inactive' => $inactiveDevices,
                'activity_rate' => $totalDevices > 0 ? ($recentDevices / $totalDevices) * 100 : 0
            ],
            'push_notifications' => [
                'total' => $totalNotifications,
                'sent' => $sentNotifications,
                'delivered' => $deliveredNotifications,
                'failed' => $failedNotifications,
                'delivery_rate' => $totalNotifications > 0 ? ($deliveredNotifications / $totalNotifications) * 100 : 0,
                'failure_rate' => $totalNotifications > 0 ? ($failedNotifications / $totalNotifications) * 100 : 0
            ],
            'performance' => [
                'avg_response_time_ms' => round($avgResponseTime ?? 0, 2),
                'status' => $this->getPerformanceStatus($avgResponseTime ?? 0)
            ]
        ];
    }

    /**
     * Analyze health metrics and trigger alerts if needed
     */
    private function analyzeHealthMetrics(array $metrics): void
    {
        $alerts = [];

        // Check API error rate
        if ($metrics['api_requests']['error_rate'] > 10) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "High API error rate: {$metrics['api_requests']['error_rate']}%",
                'metric' => 'api_error_rate'
            ];
        }

        // Check device activity rate
        if ($metrics['device_tokens']['activity_rate'] < 50) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "Low device activity rate: {$metrics['device_tokens']['activity_rate']}%",
                'metric' => 'device_activity_rate'
            ];
        }

        // Check push notification failure rate
        if ($metrics['push_notifications']['failure_rate'] > 20) {
            $alerts[] = [
                'type' => 'error',
                'message' => "High push notification failure rate: {$metrics['push_notifications']['failure_rate']}%",
                'metric' => 'push_failure_rate'
            ];
        }

        // Check response time
        if ($metrics['performance']['avg_response_time_ms'] > 1000) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "High average response time: {$metrics['performance']['avg_response_time_ms']}ms",
                'metric' => 'response_time'
            ];
        }

        // Log alerts
        foreach ($alerts as $alert) {
            Log::warning('Mobile app health alert', $alert);
        }

        // Store alerts for dashboard
        if (!empty($alerts)) {
            $this->storeHealthAlerts($alerts);
        }
    }

    /**
     * Generate health report
     */
    private function generateHealthReport(array $metrics): void
    {
        // Store metrics for historical analysis
        \App\Models\MobileHealthMetric::create([
            'metrics' => $metrics,
            'recorded_at' => now()
        ]);

        // Send report to administrators if there are issues
        if ($this->hasCriticalIssues($metrics)) {
            $this->sendHealthReportToAdmins($metrics);
        }
    }

    /**
     * Get performance status
     */
    private function getPerformanceStatus(float $avgResponseTime): string
    {
        return match(true) {
            $avgResponseTime < 500 => 'excellent',
            $avgResponseTime < 1000 => 'good',
            $avgResponseTime < 2000 => 'fair',
            default => 'poor'
        };
    }

    /**
     * Check for critical issues
     */
    private function hasCriticalIssues(array $metrics): bool
    {
        return $metrics['api_requests']['error_rate'] > 20 ||
               $metrics['push_notifications']['failure_rate'] > 30 ||
               $metrics['performance']['avg_response_time_ms'] > 2000;
    }

    /**
     * Store health alerts
     */
    private function storeHealthAlerts(array $alerts): void
    {
        foreach ($alerts as $alert) {
            \App\Models\MobileHealthAlert::create([
                'type' => $alert['type'],
                'message' => $alert['message'],
                'metric' => $alert['metric'],
                'created_at' => now()
            ]);
        }
    }

    /**
     * Send health report to administrators
     */
    private function sendHealthReportToAdmins(array $metrics): void
    {
        $admins = \App\Models\User::whereIn('role', ['admin', 'ceo', 'gm'])->get();
        
        foreach ($admins as $admin) {
            // Send email notification
            $admin->notify(new \App\Notifications\MobileHealthAlert($metrics));
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Mobile app health monitoring job failed', [
            'error' => $exception->getMessage(),
            'job' => $this
        ]);
    }
} 