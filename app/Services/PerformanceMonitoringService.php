<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PerformanceMonitoringService
{
    /**
     * Get comprehensive performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'api_performance' => $this->getApiPerformance(),
            'database_performance' => $this->getDatabasePerformance(),
            'cache_performance' => $this->getCachePerformance(),
            'memory_usage' => $this->getMemoryUsage(),
            'error_rates' => $this->getErrorRates(),
            'active_users' => $this->getActiveUsers(),
        ];
    }

    /**
     * Get API performance metrics by endpoint
     */
    public function getApiPerformance(): array
    {
        $endpoints = ['dashboard', 'payments', 'inventory', 'reports', 'mobile'];
        $performance = [];

        foreach ($endpoints as $endpoint) {
            $metrics = $this->getEndpointMetrics($endpoint);
            $performance[$endpoint] = [
                'avg_response_time' => $metrics['avg_response_time'],
                'request_count' => $metrics['request_count'],
                'p95_response_time' => $metrics['p95_response_time'],
                'p99_response_time' => $metrics['p99_response_time'],
            ];
        }

        return $performance;
    }

    /**
     * Get endpoint-specific metrics
     */
    private function getEndpointMetrics(string $endpoint): array
    {
        $cacheKey = "api_performance:GET:api/{$endpoint}";
        $metrics = Cache::get($cacheKey, [
            'response_times' => [],
            'request_count' => 0,
            'total_memory' => 0,
            'error_count' => 0,
        ]);

        $responseTimes = $metrics['response_times'];
        $requestCount = $metrics['request_count'];

        if (empty($responseTimes)) {
            return [
                'avg_response_time' => 0,
                'request_count' => 0,
                'p95_response_time' => 0,
                'p99_response_time' => 0,
            ];
        }

        sort($responseTimes);
        $count = count($responseTimes);
        $p95Index = (int) ($count * 0.95);
        $p99Index = (int) ($count * 0.99);

        return [
            'avg_response_time' => round(array_sum($responseTimes) / $count, 2),
            'request_count' => $requestCount,
            'p95_response_time' => $responseTimes[$p95Index] ?? 0,
            'p99_response_time' => $responseTimes[$p99Index] ?? 0,
        ];
    }

    /**
     * Get database performance metrics
     */
    public function getDatabasePerformance(): array
    {
        // Get slow query count from logs
        $slowQueries = Cache::get('metrics:slow_queries', 0);
        
        $driver = config('database.default');
        $activeConnections = 1; // Default for SQLite
        
        if ($driver === 'mysql') {
            try {
                $activeConnections = DB::select('SHOW STATUS LIKE "Threads_connected"')[0]->Value ?? 0;
            } catch (\Exception $e) {
                $activeConnections = 1;
            }
        }
        
        return [
            'slow_queries' => $slowQueries,
            'connection_pool' => $this->getConnectionPoolStatus(),
            'query_time_avg' => Cache::get('metrics:db_avg_query_time', 0),
            'active_connections' => $activeConnections,
        ];
    }

    /**
     * Get connection pool status
     */
    private function getConnectionPoolStatus(): array
    {
        $driver = config('database.default');
        
        if ($driver === 'mysql') {
            try {
                $maxConnections = DB::select('SHOW VARIABLES LIKE "max_connections"')[0]->Value ?? 0;
                $activeConnections = DB::select('SHOW STATUS LIKE "Threads_connected"')[0]->Value ?? 0;
            } catch (\Exception $e) {
                $maxConnections = 1;
                $activeConnections = 1;
            }
        } else {
            // For SQLite and other databases, use simplified metrics
            $maxConnections = 1;
            $activeConnections = 1;
        }
        
        return [
            'max_connections' => $maxConnections,
            'active_connections' => $activeConnections,
            'usage_percentage' => $maxConnections > 0 ? ($activeConnections / $maxConnections) * 100 : 0,
        ];
    }

    /**
     * Get cache performance metrics
     */
    public function getCachePerformance(): array
    {
        $cacheService = app(CacheOptimizationService::class);
        $stats = $cacheService->getCacheStats();
        
        return [
            'hit_rate' => $stats['hit_rate'],
            'total_requests' => $stats['total_requests'],
            'cache_hits' => $stats['cache_hits'],
            'cache_misses' => $stats['cache_misses'],
            'memory_usage' => $this->getRedisMemoryUsage(),
        ];
    }

    /**
     * Get Redis memory usage
     */
    private function getRedisMemoryUsage(): string
    {
        try {
            $store = Cache::getStore();
            
            // Check if we're using Redis
            if (method_exists($store, 'getRedis')) {
                $redis = $store->getRedis();
                $info = $redis->info('memory');
                $usedMemory = $info['used_memory'] ?? 0;
                
                return $this->formatBytes($usedMemory);
            } else {
                // For file cache or other stores, return a placeholder
                return 'File Cache';
            }
        } catch (\Exception $e) {
            // Return a safe fallback for any cache driver issues
            return 'File Cache';
        }
    }

    /**
     * Get memory usage metrics
     */
    public function getMemoryUsage(): array
    {
        $currentUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        $usagePercentage = ($currentUsage / $memoryLimit) * 100;

        return [
            'current_usage' => $this->formatBytes($currentUsage),
            'peak_usage' => $this->formatBytes($peakUsage),
            'memory_limit' => $this->formatBytes($memoryLimit),
            'usage_percentage' => round($usagePercentage, 2),
        ];
    }

    /**
     * Get memory limit in bytes
     */
    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        
        if ($limit === '-1') {
            return 128 * 1024 * 1024; // 128MB default
        }
        
        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }

    /**
     * Get error rates
     */
    public function getErrorRates(): array
    {
        $systemMetrics = Cache::get('system_metrics', [
            'total_requests' => 0,
            'error_count' => 0,
        ]);
        
        $totalRequests = $systemMetrics['total_requests'];
        $errorCount = $systemMetrics['error_count'];
        
        $errorRate = $totalRequests > 0 ? ($errorCount / $totalRequests) * 100 : 0;
        $successRate = 100 - $errorRate;
        
        return [
            'total_requests' => $totalRequests,
            'error_requests' => $errorCount,
            'error_rate' => round($errorRate, 2),
            'success_rate' => round($successRate, 2),
        ];
    }

    /**
     * Get active users metrics
     */
    public function getActiveUsers(): array
    {
        // Get active sessions from cache
        $activeSessions = Cache::get('active_sessions', []);
        $activeUsers = count($activeSessions);
        
        // Calculate average session duration
        $sessionDurations = array_column($activeSessions, 'duration');
        $avgDuration = !empty($sessionDurations) ? array_sum($sessionDurations) / count($sessionDurations) : 0;
        
        return [
            'active_users' => $activeUsers,
            'concurrent_users' => $activeUsers,
            'session_duration_avg' => round($avgDuration, 2),
        ];
    }

    /**
     * Track API request
     */
    public function trackApiRequest(string $endpoint, float $responseTime, int $memoryUsed, bool $isError = false): void
    {
        $method = 'GET'; // Default method
        $cacheKey = "api_performance:{$method}:{$endpoint}";
        
        $metrics = Cache::get($cacheKey, [
            'response_times' => [],
            'request_count' => 0,
            'total_memory' => 0,
            'error_count' => 0,
        ]);
        
        $metrics['response_times'][] = $responseTime;
        $metrics['request_count']++;
        $metrics['total_memory'] += $memoryUsed;
        
        if ($isError) {
            $metrics['error_count']++;
        }
        
        // Keep only last 1000 requests for performance
        if (count($metrics['response_times']) > 1000) {
            $metrics['response_times'] = array_slice($metrics['response_times'], -1000);
        }
        
        Cache::put($cacheKey, $metrics, 3600);
        
        // Update system metrics
        $this->updateSystemMetrics($responseTime, $memoryUsed, $isError);
    }

    /**
     * Update system-wide metrics
     */
    private function updateSystemMetrics(float $responseTime, int $memoryUsed, bool $isError = false): void
    {
        $systemMetrics = Cache::get('system_metrics', [
            'total_requests' => 0,
            'total_response_time' => 0,
            'total_memory_used' => 0,
            'peak_memory' => 0,
            'error_count' => 0,
            'last_updated' => now(),
        ]);
        
        $systemMetrics['total_requests']++;
        $systemMetrics['total_response_time'] += $responseTime;
        $systemMetrics['total_memory_used'] += $memoryUsed;
        $systemMetrics['peak_memory'] = max($systemMetrics['peak_memory'], memory_get_peak_usage());
        
        if ($isError) {
            $systemMetrics['error_count']++;
        }
        
        $systemMetrics['last_updated'] = now();
        
        Cache::put('system_metrics', $systemMetrics, 3600);
    }

    /**
     * Track cache access
     */
    public function trackCacheAccess(string $key, bool $isHit): void
    {
        $cacheService = app(CacheOptimizationService::class);
        
        if ($isHit) {
            $cacheService->trackCacheHit('general');
        } else {
            $cacheService->trackCacheMiss('general');
        }
    }

    /**
     * Get system health status
     */
    public function getSystemHealth(): array
    {
        $memoryUsage = $this->getMemoryUsage();
        $errorRates = $this->getErrorRates();
        $cachePerformance = $this->getCachePerformance();
        
        $healthScore = 100;
        
        // Deduct points for issues
        if ($memoryUsage['usage_percentage'] > 80) {
            $healthScore -= 20;
        }
        
        if ($errorRates['error_rate'] > 5) {
            $healthScore -= 30;
        }
        
        if ($cachePerformance['hit_rate'] < 50) {
            $healthScore -= 10;
        }
        
        return [
            'status' => $healthScore >= 90 ? 'healthy' : ($healthScore >= 70 ? 'warning' : 'critical'),
            'score' => max(0, $healthScore),
            'memory_usage' => $memoryUsage['usage_percentage'],
            'error_rate' => $errorRates['error_rate'],
            'cache_hit_rate' => $cachePerformance['hit_rate'],
            'last_updated' => now(),
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Generate performance report
     */
    public function generatePerformanceReport(): array
    {
        $performance = $this->getPerformanceMetrics();
        $health = $this->getSystemHealth();
        
        // Calculate overall performance score
        $score = $this->calculatePerformanceScore($performance, $health);
        
        return [
            'performance_score' => $score,
            'health_status' => $health,
            'metrics' => $performance,
            'recommendations' => $this->getPerformanceRecommendations($performance, $health),
            'timestamp' => now(),
        ];
    }

    /**
     * Calculate overall performance score
     */
    private function calculatePerformanceScore(array $performance, array $health): int
    {
        $score = 100;
        
        // API Performance (30% weight)
        $apiScore = 0;
        $apiCount = 0;
        foreach ($performance['api_performance'] as $endpoint => $metrics) {
            if ($metrics['avg_response_time'] > 0) {
                $apiScore += min(100, max(0, 100 - ($metrics['avg_response_time'] / 10)));
                $apiCount++;
            }
        }
        $score -= (30 * (1 - ($apiScore / ($apiCount * 100))));
        
        // Cache Performance (25% weight)
        $cacheHitRate = $performance['cache_performance']['hit_rate'];
        $score -= (25 * (1 - ($cacheHitRate / 100)));
        
        // Memory Usage (20% weight)
        $memoryUsage = $performance['memory_usage']['usage_percentage'];
        $score -= (20 * ($memoryUsage / 100));
        
        // Error Rate (15% weight)
        $errorRate = $performance['error_rates']['error_rate'];
        $score -= (15 * ($errorRate / 100));
        
        // Health Score (10% weight)
        $healthScore = $health['score'];
        $score -= (10 * (1 - ($healthScore / 100)));
        
        return max(0, min(100, round($score)));
    }

    /**
     * Get performance recommendations
     */
    private function getPerformanceRecommendations(array $performance, array $health): array
    {
        $recommendations = [];
        
        // API Performance recommendations
        foreach ($performance['api_performance'] as $endpoint => $metrics) {
            if ($metrics['avg_response_time'] > 100) {
                $recommendations[] = "Optimize {$endpoint} endpoint - current avg: {$metrics['avg_response_time']}ms";
            }
        }
        
        // Cache recommendations
        if ($performance['cache_performance']['hit_rate'] < 70) {
            $recommendations[] = "Improve cache hit rate - current: {$performance['cache_performance']['hit_rate']}%";
        }
        
        // Memory recommendations
        if ($performance['memory_usage']['usage_percentage'] > 80) {
            $recommendations[] = "High memory usage detected - {$performance['memory_usage']['usage_percentage']}%";
        }
        
        // Error rate recommendations
        if ($performance['error_rates']['error_rate'] > 5) {
            $recommendations[] = "High error rate detected - {$performance['error_rates']['error_rate']}%";
        }
        
        return $recommendations;
    }
} 