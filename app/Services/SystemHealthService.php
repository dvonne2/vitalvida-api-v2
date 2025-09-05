<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Models\SystemLog;

class SystemHealthService
{
    /**
     * Get overall system health status
     */
    public function getOverallHealth(): array
    {
        $components = [
            'database' => $this->checkDatabaseHealth(),
            'storage' => $this->checkStorageHealth(),
            'memory' => $this->checkMemoryHealth(),
            'cpu' => $this->checkCpuHealth()
        ];

        $healthyComponents = collect($components)->where('status', 'healthy')->count();
        $totalComponents = count($components);
        $healthPercentage = ($healthyComponents / $totalComponents) * 100;

        return [
            'overall_health' => number_format($healthPercentage, 1) . '%',
            'components' => $components,
            'last_checked' => now()->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Check database health
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $startTime = microtime(true);
            DB::connection()->getPdo();
            $responseTime = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'healthy',
                'response_time' => round($responseTime, 2) . 'ms',
                'last_checked' => now()->format('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_checked' => now()->format('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Check storage health
     */
    private function checkStorageHealth(): array
    {
        try {
            $disk = Storage::disk('local');
            $totalSpace = disk_total_space(storage_path());
            $freeSpace = disk_free_space(storage_path());
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercentage = ($usedSpace / $totalSpace) * 100;

            return [
                'status' => $usagePercentage < 90 ? 'healthy' : 'warning',
                'usage' => round($usagePercentage, 1) . '%',
                'available_space' => $this->formatBytes($freeSpace),
                'total_space' => $this->formatBytes($totalSpace),
                'used_space' => $this->formatBytes($usedSpace)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check memory health
     */
    private function checkMemoryHealth(): array
    {
        try {
            $memoryLimit = ini_get('memory_limit');
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            
            // Convert memory limit to bytes
            $memoryLimitBytes = $this->convertToBytes($memoryLimit);
            $usagePercentage = ($memoryUsage / $memoryLimitBytes) * 100;

            return [
                'status' => $usagePercentage < 80 ? 'healthy' : 'warning',
                'usage' => round($usagePercentage, 1) . '%',
                'current_usage' => $this->formatBytes($memoryUsage),
                'peak_usage' => $this->formatBytes($memoryPeak),
                'limit' => $memoryLimit
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check CPU health (simplified)
     */
    private function checkCpuHealth(): array
    {
        try {
            // Simple CPU check - in production you might want to use system commands
            $loadAverage = sys_getloadavg();
            $cpuUsage = $loadAverage[0]; // 1 minute load average

            return [
                'status' => $cpuUsage < 2.0 ? 'healthy' : 'warning',
                'usage' => round($cpuUsage * 10, 1) . '%', // Rough estimate
                'load_average' => $cpuUsage,
                'cores' => 1 // Simplified
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get system performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $metrics = [
            'average_response_time' => $this->getAverageResponseTime(),
            'requests_per_minute' => $this->getRequestsPerMinute(),
            'error_rate' => $this->getErrorRate(),
            'uptime' => $this->getUptime()
        ];

        $performanceScore = $this->calculatePerformanceScore($metrics);

        return [
            'performance_score' => $performanceScore,
            'metrics' => $metrics
        ];
    }

    /**
     * Get average response time
     */
    private function getAverageResponseTime(): string
    {
        // This would typically come from your monitoring system
        // For now, we'll return a simulated value
        $responseTime = rand(50, 200);
        return $responseTime . 'ms';
    }

    /**
     * Get requests per minute
     */
    private function getRequestsPerMinute(): int
    {
        // This would typically come from your monitoring system
        return rand(30, 100);
    }

    /**
     * Get error rate
     */
    private function getErrorRate(): string
    {
        $totalLogs = SystemLog::whereDate('created_at', today())->count();
        $errorLogs = SystemLog::where('level', 'error')
            ->whereDate('created_at', today())
            ->count();

        if ($totalLogs === 0) {
            return '0.00%';
        }

        $errorRate = ($errorLogs / $totalLogs) * 100;
        return number_format($errorRate, 2) . '%';
    }

    /**
     * Get system uptime
     */
    private function getUptime(): string
    {
        // This would typically come from your monitoring system
        // For now, we'll return a simulated value
        $uptime = rand(99, 100);
        return number_format($uptime, 2) . '%';
    }

    /**
     * Calculate performance score
     */
    private function calculatePerformanceScore(array $metrics): string
    {
        $score = 0;
        
        // Response time score (lower is better)
        $responseTime = (int) str_replace('ms', '', $metrics['average_response_time']);
        if ($responseTime < 100) $score += 25;
        elseif ($responseTime < 200) $score += 20;
        elseif ($responseTime < 500) $score += 15;
        else $score += 10;

        // Error rate score (lower is better)
        $errorRate = (float) str_replace('%', '', $metrics['error_rate']);
        if ($errorRate < 0.1) $score += 25;
        elseif ($errorRate < 1.0) $score += 20;
        elseif ($errorRate < 5.0) $score += 15;
        else $score += 10;

        // Uptime score
        $uptime = (float) str_replace('%', '', $metrics['uptime']);
        if ($uptime >= 99.9) $score += 25;
        elseif ($uptime >= 99.0) $score += 20;
        elseif ($uptime >= 95.0) $score += 15;
        else $score += 10;

        // Requests per minute score (higher is better)
        $rpm = $metrics['requests_per_minute'];
        if ($rpm > 50) $score += 25;
        elseif ($rpm > 30) $score += 20;
        elseif ($rpm > 10) $score += 15;
        else $score += 10;

        if ($score >= 90) return 'Excellent';
        elseif ($score >= 75) return 'Good';
        elseif ($score >= 60) return 'Fair';
        else return 'Poor';
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes(string $memoryLimit): int
    {
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);

        switch ($unit) {
            case 'k':
                return $value * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'g':
                return $value * 1024 * 1024 * 1024;
            default:
                return $value;
        }
    }

    /**
     * Log system health check
     */
    public function logHealthCheck(): void
    {
        $health = $this->getOverallHealth();
        
        SystemLog::create([
            'level' => 'info',
            'message' => 'System health check completed',
            'context' => $health,
            'source' => 'system_health_service'
        ]);
    }
} 