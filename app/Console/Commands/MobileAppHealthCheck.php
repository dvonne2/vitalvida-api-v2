<?php

namespace App\Console\Commands;

use App\Models\ApiRequest;
use App\Models\DeviceToken;
use App\Models\PushNotification;
use Illuminate\Console\Command;

class MobileAppHealthCheck extends Command
{
    protected $signature = 'mobile:health-check 
                            {--detailed : Show detailed metrics}
                            {--period=1 : Hours to analyze (default: 1 hour)}';

    protected $description = 'Check mobile app health and performance metrics';

    public function handle()
    {
        try {
            $period = $this->option('period');
            $detailed = $this->option('detailed');
            
            $this->info("Mobile App Health Check - Last {$period} hour(s)");
            $this->newLine();

            $metrics = $this->collectMetrics($period);
            $this->displayMetrics($metrics, $detailed);

            $this->newLine();
            $this->displayRecommendations($metrics);

            return 0;

        } catch (\Exception $e) {
            $this->error('Health check failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Collect health metrics
     */
    private function collectMetrics(int $period): array
    {
        $since = now()->subHours($period);

        // API Request Metrics
        $totalRequests = ApiRequest::where('created_at', '>=', $since)->count();
        $successfulRequests = ApiRequest::where('created_at', '>=', $since)
            ->where('status', 'success')
            ->count();
        $errorRequests = ApiRequest::where('created_at', '>=', $since)
            ->where('status', 'error')
            ->count();
        $cacheHits = ApiRequest::where('created_at', '>=', $since)
            ->where('status', 'cache_hit')
            ->count();

        // Device Token Metrics
        $totalDevices = DeviceToken::where('is_active', true)->count();
        $recentDevices = DeviceToken::where('last_used_at', '>=', $since)->count();
        $inactiveDevices = DeviceToken::where('is_active', true)
            ->where('last_used_at', '<', $since)
            ->count();

        // Push Notification Metrics
        $totalNotifications = PushNotification::where('created_at', '>=', $since)->count();
        $sentNotifications = PushNotification::where('created_at', '>=', $since)
            ->where('status', 'sent')
            ->count();
        $deliveredNotifications = PushNotification::where('created_at', '>=', $since)
            ->where('status', 'delivered')
            ->count();
        $failedNotifications = PushNotification::where('created_at', '>=', $since)
            ->where('status', 'failed')
            ->count();

        // Performance Metrics
        $avgResponseTime = ApiRequest::where('created_at', '>=', $since)
            ->where('response_time', '>', 0)
            ->avg('response_time');

        $maxResponseTime = ApiRequest::where('created_at', '>=', $since)
            ->where('response_time', '>', 0)
            ->max('response_time');

        return [
            'period_hours' => $period,
            'api_requests' => [
                'total' => $totalRequests,
                'successful' => $successfulRequests,
                'errors' => $errorRequests,
                'cache_hits' => $cacheHits,
                'success_rate' => $totalRequests > 0 ? round(($successfulRequests / $totalRequests) * 100, 2) : 0,
                'error_rate' => $totalRequests > 0 ? round(($errorRequests / $totalRequests) * 100, 2) : 0,
                'cache_hit_rate' => $totalRequests > 0 ? round(($cacheHits / $totalRequests) * 100, 2) : 0
            ],
            'device_tokens' => [
                'total_active' => $totalDevices,
                'recent_activity' => $recentDevices,
                'inactive' => $inactiveDevices,
                'activity_rate' => $totalDevices > 0 ? round(($recentDevices / $totalDevices) * 100, 2) : 0
            ],
            'push_notifications' => [
                'total' => $totalNotifications,
                'sent' => $sentNotifications,
                'delivered' => $deliveredNotifications,
                'failed' => $failedNotifications,
                'delivery_rate' => $totalNotifications > 0 ? round(($deliveredNotifications / $totalNotifications) * 100, 2) : 0,
                'failure_rate' => $totalNotifications > 0 ? round(($failedNotifications / $totalNotifications) * 100, 2) : 0
            ],
            'performance' => [
                'avg_response_time_ms' => round($avgResponseTime ?? 0, 2),
                'max_response_time_ms' => round($maxResponseTime ?? 0, 2),
                'status' => $this->getPerformanceStatus($avgResponseTime ?? 0)
            ]
        ];
    }

    /**
     * Display metrics
     */
    private function displayMetrics(array $metrics, bool $detailed): void
    {
        // API Requests
        $this->info('📊 API Requests');
        $this->table(
            ['Metric', 'Value', 'Status'],
            [
                ['Total Requests', $metrics['api_requests']['total'], ''],
                ['Success Rate', $metrics['api_requests']['success_rate'] . '%', $this->getStatusIcon($metrics['api_requests']['success_rate'], 90, 80)],
                ['Error Rate', $metrics['api_requests']['error_rate'] . '%', $this->getStatusIcon($metrics['api_requests']['error_rate'], 5, 10, true)],
                ['Cache Hit Rate', $metrics['api_requests']['cache_hit_rate'] . '%', $this->getStatusIcon($metrics['api_requests']['cache_hit_rate'], 50, 30)]
            ]
        );

        // Device Tokens
        $this->info('📱 Device Tokens');
        $this->table(
            ['Metric', 'Value', 'Status'],
            [
                ['Active Devices', $metrics['device_tokens']['total_active'], ''],
                ['Recent Activity', $metrics['device_tokens']['recent_activity'], ''],
                ['Activity Rate', $metrics['device_tokens']['activity_rate'] . '%', $this->getStatusIcon($metrics['device_tokens']['activity_rate'], 70, 50)]
            ]
        );

        // Push Notifications
        $this->info('🔔 Push Notifications');
        $this->table(
            ['Metric', 'Value', 'Status'],
            [
                ['Total Sent', $metrics['push_notifications']['total'], ''],
                ['Delivery Rate', $metrics['push_notifications']['delivery_rate'] . '%', $this->getStatusIcon($metrics['push_notifications']['delivery_rate'], 90, 80)],
                ['Failure Rate', $metrics['push_notifications']['failure_rate'] . '%', $this->getStatusIcon($metrics['push_notifications']['failure_rate'], 5, 10, true)]
            ]
        );

        // Performance
        $this->info('⚡ Performance');
        $this->table(
            ['Metric', 'Value', 'Status'],
            [
                ['Avg Response Time', $metrics['performance']['avg_response_time_ms'] . 'ms', $this->getStatusIcon($metrics['performance']['avg_response_time_ms'], 500, 1000, true)],
                ['Max Response Time', $metrics['performance']['max_response_time_ms'] . 'ms', $this->getStatusIcon($metrics['performance']['max_response_time_ms'], 2000, 5000, true)],
                ['Overall Status', $metrics['performance']['status'], $this->getStatusIcon($metrics['performance']['status'] === 'excellent' ? 100 : ($metrics['performance']['status'] === 'good' ? 80 : ($metrics['performance']['status'] === 'fair' ? 60 : 40)), 80, 60)]
            ]
        );

        if ($detailed) {
            $this->displayDetailedMetrics($metrics);
        }
    }

    /**
     * Display detailed metrics
     */
    private function displayDetailedMetrics(array $metrics): void
    {
        $this->newLine();
        $this->info('🔍 Detailed Metrics');

        // Top error endpoints
        $topErrors = ApiRequest::where('created_at', '>=', now()->subHours($metrics['period_hours']))
            ->where('status', 'error')
            ->selectRaw('path, COUNT(*) as error_count')
            ->groupBy('path')
            ->orderBy('error_count', 'desc')
            ->limit(5)
            ->get();

        if ($topErrors->isNotEmpty()) {
            $this->warn('Top Error Endpoints:');
            $this->table(
                ['Endpoint', 'Error Count'],
                $topErrors->map(fn($error) => [$error->path, $error->error_count])->toArray()
            );
        }

        // Platform distribution
        $platformStats = DeviceToken::where('is_active', true)
            ->selectRaw('platform, COUNT(*) as count')
            ->groupBy('platform')
            ->get();

        if ($platformStats->isNotEmpty()) {
            $this->info('Platform Distribution:');
            $this->table(
                ['Platform', 'Active Devices'],
                $platformStats->map(fn($stat) => [$stat->platform, $stat->count])->toArray()
            );
        }
    }

    /**
     * Display recommendations
     */
    private function displayRecommendations(array $metrics): void
    {
        $this->info('💡 Recommendations');

        $recommendations = [];

        if ($metrics['api_requests']['error_rate'] > 10) {
            $recommendations[] = '🔴 High error rate detected. Review API endpoints and error handling.';
        }

        if ($metrics['device_tokens']['activity_rate'] < 50) {
            $recommendations[] = '🟡 Low device activity. Consider re-engagement campaigns.';
        }

        if ($metrics['push_notifications']['failure_rate'] > 20) {
            $recommendations[] = '🔴 High push notification failure rate. Check FCM/APNS configuration.';
        }

        if ($metrics['performance']['avg_response_time_ms'] > 1000) {
            $recommendations[] = '🟡 High response time. Consider caching and optimization.';
        }

        if ($metrics['api_requests']['cache_hit_rate'] < 30) {
            $recommendations[] = '🟡 Low cache hit rate. Review caching strategy.';
        }

        if (empty($recommendations)) {
            $this->info('✅ All systems are performing well!');
        } else {
            foreach ($recommendations as $recommendation) {
                $this->line($recommendation);
            }
        }
    }

    /**
     * Get status icon
     */
    private function getStatusIcon(float $value, float $goodThreshold, float $warningThreshold, bool $inverted = false): string
    {
        if ($inverted) {
            if ($value <= $goodThreshold) return '🟢';
            if ($value <= $warningThreshold) return '🟡';
            return '🔴';
        } else {
            if ($value >= $goodThreshold) return '🟢';
            if ($value >= $warningThreshold) return '🟡';
            return '🔴';
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
} 