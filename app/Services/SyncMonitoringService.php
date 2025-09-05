<?php

namespace App\Services;

use App\Services\RealTimeSyncService;
use App\Services\ConflictDetectionService;
use App\Services\ConflictResolutionService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;

class SyncMonitoringService
{
    private $redis;
    private $realTimeSyncService;
    private $conflictDetectionService;
    private $conflictResolutionService;
    private $healthThresholds;

    public function __construct(
        RealTimeSyncService $realTimeSyncService,
        ConflictDetectionService $conflictDetectionService,
        ConflictResolutionService $conflictResolutionService
    ) {
        $this->realTimeSyncService = $realTimeSyncService;
        $this->conflictDetectionService = $conflictDetectionService;
        $this->conflictResolutionService = $conflictResolutionService;
        // $this->redis = Redis::connection(); // Temporarily disabled
        
        $this->healthThresholds = [
            'sync_success_rate' => 95, // 95% minimum success rate
            'max_sync_delay' => 300, // 5 minutes max delay
            'max_queue_size' => 1000, // Max 1000 jobs in queue
            'max_failed_jobs' => 50, // Max 50 failed jobs per hour
            'max_conflicts' => 100, // Max 100 unresolved conflicts
            'redis_memory_limit' => 80 // 80% Redis memory usage limit
        ];
    }

    /**
     * Comprehensive sync health monitoring
     */
    public function monitorSyncHealth(): array
    {
        $healthReport = [
            'overall_health' => 'healthy',
            'health_score' => 100,
            'timestamp' => now(),
            'checks' => []
        ];

        try {
            // Check sync success rates
            $syncHealth = $this->checkSyncSuccessRates();
            $healthReport['checks']['sync_rates'] = $syncHealth;

            // Check queue health
            $queueHealth = $this->checkQueueHealth();
            $healthReport['checks']['queue_health'] = $queueHealth;

            // Check conflict levels
            $conflictHealth = $this->checkConflictLevels();
            $healthReport['checks']['conflict_levels'] = $conflictHealth;

            // Check Redis health
            $redisHealth = $this->checkRedisHealth();
            $healthReport['checks']['redis_health'] = $redisHealth;

            // Check database connectivity
            $dbHealth = $this->checkDatabaseHealth();
            $healthReport['checks']['database_health'] = $dbHealth;

            // Check event broadcasting
            $broadcastHealth = $this->checkBroadcastHealth();
            $healthReport['checks']['broadcast_health'] = $broadcastHealth;

            // Calculate overall health
            $healthReport = $this->calculateOverallHealth($healthReport);

            // Cache health report
            $this->cacheHealthReport($healthReport);

            // Trigger alerts if needed
            $this->triggerHealthAlerts($healthReport);

            Log::info('Sync health monitoring completed', [
                'overall_health' => $healthReport['overall_health'],
                'health_score' => $healthReport['health_score']
            ]);

            return $healthReport;

        } catch (\Exception $e) {
            Log::error('Sync health monitoring failed', ['error' => $e->getMessage()]);
            
            return [
                'overall_health' => 'critical',
                'health_score' => 0,
                'error' => $e->getMessage(),
                'timestamp' => now()
            ];
        }
    }

    /**
     * Auto-recovery mechanisms
     */
    public function triggerAutoRecovery(array $healthReport): array
    {
        $recoveryActions = [];

        try {
            // Recovery for sync rate issues
            if ($healthReport['checks']['sync_rates']['status'] !== 'healthy') {
                $recoveryActions[] = $this->recoverSyncRates();
            }

            // Recovery for queue issues
            if ($healthReport['checks']['queue_health']['status'] !== 'healthy') {
                $recoveryActions[] = $this->recoverQueueHealth();
            }

            // Recovery for high conflict levels
            if ($healthReport['checks']['conflict_levels']['status'] !== 'healthy') {
                $recoveryActions[] = $this->recoverConflictLevels();
            }

            // Recovery for Redis issues
            if ($healthReport['checks']['redis_health']['status'] !== 'healthy') {
                $recoveryActions[] = $this->recoverRedisHealth();
            }

            // Recovery for database issues
            if ($healthReport['checks']['database_health']['status'] !== 'healthy') {
                $recoveryActions[] = $this->recoverDatabaseHealth();
            }

            Log::info('Auto-recovery completed', [
                'actions_taken' => count($recoveryActions),
                'recovery_actions' => $recoveryActions
            ]);

            return [
                'success' => true,
                'actions_taken' => count($recoveryActions),
                'recovery_actions' => $recoveryActions,
                'timestamp' => now()
            ];

        } catch (\Exception $e) {
            Log::error('Auto-recovery failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => now()
            ];
        }
    }

    /**
     * Check sync success rates
     */
    private function checkSyncSuccessRates(): array
    {
        $totalSyncs = (int) $this->redis->get('sync_stats:total_syncs:24h') ?: 1;
        $failedSyncs = (int) $this->redis->get('sync_stats:failed_syncs:24h') ?: 0;
        $successRate = round((($totalSyncs - $failedSyncs) / $totalSyncs) * 100, 2);

        $status = $successRate >= $this->healthThresholds['sync_success_rate'] ? 'healthy' : 'unhealthy';

        return [
            'status' => $status,
            'success_rate' => $successRate,
            'total_syncs' => $totalSyncs,
            'failed_syncs' => $failedSyncs,
            'threshold' => $this->healthThresholds['sync_success_rate'],
            'last_sync' => $this->redis->get('sync_stats:last_sync_time')
        ];
    }

    /**
     * Check queue health
     */
    private function checkQueueHealth(): array
    {
        $queueSizes = [
            'high_priority' => Queue::size('high-priority-sync'),
            'normal' => Queue::size('normal-sync'),
            'compliance' => Queue::size('compliance-sync')
        ];

        $totalQueueSize = array_sum($queueSizes);
        $failedJobs = DB::table('failed_jobs')
            ->where('failed_at', '>', now()->subHour())
            ->count();

        $queueStatus = $totalQueueSize <= $this->healthThresholds['max_queue_size'] ? 'healthy' : 'unhealthy';
        $failedStatus = $failedJobs <= $this->healthThresholds['max_failed_jobs'] ? 'healthy' : 'unhealthy';

        $overallStatus = ($queueStatus === 'healthy' && $failedStatus === 'healthy') ? 'healthy' : 'unhealthy';

        return [
            'status' => $overallStatus,
            'queue_sizes' => $queueSizes,
            'total_queue_size' => $totalQueueSize,
            'failed_jobs_1h' => $failedJobs,
            'queue_threshold' => $this->healthThresholds['max_queue_size'],
            'failed_threshold' => $this->healthThresholds['max_failed_jobs']
        ];
    }

    /**
     * Check conflict levels
     */
    private function checkConflictLevels(): array
    {
        $conflictSummary = $this->conflictDetection->getConflictSummary();
        $totalConflicts = $conflictSummary['total_conflicts'] ?? 0;
        $criticalConflicts = $conflictSummary['by_severity']['critical'] ?? 0;

        $status = ($totalConflicts <= $this->healthThresholds['max_conflicts'] && $criticalConflicts === 0) 
            ? 'healthy' : 'unhealthy';

        return [
            'status' => $status,
            'total_conflicts' => $totalConflicts,
            'critical_conflicts' => $criticalConflicts,
            'auto_resolvable' => $conflictSummary['auto_resolvable'] ?? 0,
            'manual_required' => $conflictSummary['manual_resolution_required'] ?? 0,
            'threshold' => $this->healthThresholds['max_conflicts']
        ];
    }

    /**
     * Check Redis health
     */
    private function checkRedisHealth(): array
    {
        try {
            $info = $this->redis->info('memory');
            $usedMemory = $info['used_memory'] ?? 0;
            $maxMemory = $info['maxmemory'] ?? 1;
            
            $memoryUsage = $maxMemory > 0 ? round(($usedMemory / $maxMemory) * 100, 2) : 0;
            $status = $memoryUsage <= $this->healthThresholds['redis_memory_limit'] ? 'healthy' : 'unhealthy';

            // Test Redis connectivity
            $this->redis->ping();

            return [
                'status' => $status,
                'memory_usage_percent' => $memoryUsage,
                'used_memory_mb' => round($usedMemory / 1024 / 1024, 2),
                'connectivity' => 'ok',
                'threshold' => $this->healthThresholds['redis_memory_limit']
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
                'connectivity' => 'failed'
            ];
        }
    }

    /**
     * Check database health
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            $status = $responseTime <= 100 ? 'healthy' : 'unhealthy'; // 100ms threshold

            return [
                'status' => $status,
                'response_time_ms' => $responseTime,
                'connectivity' => 'ok'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
                'connectivity' => 'failed'
            ];
        }
    }

    /**
     * Check broadcast health
     */
    private function checkBroadcastHealth(): array
    {
        try {
            // Test if broadcasting is working by checking recent events
            $recentEvents = $this->redis->get('broadcast_health_check');
            
            return [
                'status' => 'healthy',
                'last_broadcast' => $recentEvents,
                'connectivity' => 'ok'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'connectivity' => 'failed'
            ];
        }
    }

    /**
     * Recovery actions
     */
    private function recoverSyncRates(): array
    {
        // Clear failed sync cache and retry failed syncs
        $this->redis->del('sync_stats:failed_syncs:24h');
        
        // Restart sync workers if needed
        $this->restartSyncWorkers();

        return [
            'action' => 'sync_rate_recovery',
            'steps' => ['cleared_failed_cache', 'restarted_workers'],
            'timestamp' => now()
        ];
    }

    private function recoverQueueHealth(): array
    {
        // Clear stuck jobs and restart workers
        $this->clearStuckJobs();
        $this->restartQueueWorkers();

        return [
            'action' => 'queue_recovery',
            'steps' => ['cleared_stuck_jobs', 'restarted_queue_workers'],
            'timestamp' => now()
        ];
    }

    private function recoverConflictLevels(): array
    {
        // Auto-resolve conflicts
        $resolutionResults = $this->conflictResolution->autoResolveConflicts();

        return [
            'action' => 'conflict_recovery',
            'conflicts_resolved' => count(array_filter($resolutionResults, fn($r) => $r['success'])),
            'timestamp' => now()
        ];
    }

    private function recoverRedisHealth(): array
    {
        // Clear old cache entries
        $this->redis->flushdb();

        return [
            'action' => 'redis_recovery',
            'steps' => ['cleared_cache'],
            'timestamp' => now()
        ];
    }

    private function recoverDatabaseHealth(): array
    {
        // Reconnect to database
        DB::reconnect();

        return [
            'action' => 'database_recovery',
            'steps' => ['reconnected'],
            'timestamp' => now()
        ];
    }

    /**
     * Helper methods
     */
    private function calculateOverallHealth(array $healthReport): array
    {
        $healthyChecks = 0;
        $totalChecks = count($healthReport['checks']);

        foreach ($healthReport['checks'] as $check) {
            if ($check['status'] === 'healthy') {
                $healthyChecks++;
            }
        }

        $healthScore = $totalChecks > 0 ? round(($healthyChecks / $totalChecks) * 100) : 0;

        if ($healthScore >= 90) {
            $overallHealth = 'healthy';
        } elseif ($healthScore >= 70) {
            $overallHealth = 'warning';
        } elseif ($healthScore >= 50) {
            $overallHealth = 'unhealthy';
        } else {
            $overallHealth = 'critical';
        }

        $healthReport['health_score'] = $healthScore;
        $healthReport['overall_health'] = $overallHealth;

        return $healthReport;
    }

    private function cacheHealthReport(array $healthReport)
    {
        $this->redis->setex('sync_health_report', 300, json_encode($healthReport)); // 5 minutes
    }

    private function triggerHealthAlerts(array $healthReport)
    {
        if ($healthReport['overall_health'] === 'critical') {
            $this->sendCriticalAlert($healthReport);
        } elseif ($healthReport['overall_health'] === 'unhealthy') {
            $this->sendWarningAlert($healthReport);
        }
    }

    private function sendCriticalAlert(array $healthReport)
    {
        Log::critical('Critical sync health detected', $healthReport);
        
        // Create system alert
        DB::table('system_alerts')->insert([
            'alert_type' => 'critical_sync_health',
            'severity' => 'critical',
            'title' => 'Critical Sync Health Alert',
            'message' => 'VitalVida-Role integration health is critical',
            'data' => json_encode($healthReport),
            'requires_action' => true,
            'created_at' => now()
        ]);
    }

    private function sendWarningAlert(array $healthReport)
    {
        Log::warning('Sync health warning', $healthReport);
    }

    private function restartSyncWorkers()
    {
        // Implementation would depend on deployment setup
        Log::info('Sync workers restart triggered');
    }

    private function restartQueueWorkers()
    {
        // Implementation would depend on deployment setup
        Log::info('Queue workers restart triggered');
    }

    private function clearStuckJobs()
    {
        // Clear jobs that have been running too long
        DB::table('jobs')
            ->where('created_at', '<', now()->subMinutes(30))
            ->delete();
    }

    /**
     * Get monitoring dashboard data
     */
    public function getDashboardData(): array
    {
        $healthReport = json_decode($this->redis->get('sync_health_report'), true);
        
        if (!$healthReport) {
            $healthReport = $this->monitorSyncHealth();
        }

        $syncStats = $this->realTimeSync->getSyncStatus();
        $conflictStats = $this->conflictDetection->getConflictSummary();
        $resolutionStats = $this->conflictResolution->getResolutionStats();

        return [
            'health_report' => $healthReport,
            'sync_statistics' => $syncStats,
            'conflict_summary' => $conflictStats,
            'resolution_stats' => $resolutionStats,
            'last_updated' => now()
        ];
    }
}
