<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\SystemLog;
use App\Models\SecurityEvent;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LogCleanupService
{
    /**
     * Clean up old logs based on retention policies
     */
    public function cleanupOldLogs(): array
    {
        $results = [
            'activity_logs' => $this->cleanupActivityLogs(),
            'system_logs' => $this->cleanupSystemLogs(),
            'security_events' => $this->cleanupSecurityEvents()
        ];

        $this->logCleanupResults($results);

        return $results;
    }

    /**
     * Clean up old activity logs
     */
    private function cleanupActivityLogs(): array
    {
        $retentionDays = config('logging.activity_logs_retention_days', 90);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $deletedCount = ActivityLog::where('timestamp', '<', $cutoffDate)->delete();

        return [
            'deleted_count' => $deletedCount,
            'retention_days' => $retentionDays,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Clean up old system logs
     */
    private function cleanupSystemLogs(): array
    {
        $retentionDays = config('logging.system_logs_retention_days', 30);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $deletedCount = SystemLog::where('created_at', '<', $cutoffDate)->delete();

        return [
            'deleted_count' => $deletedCount,
            'retention_days' => $retentionDays,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Clean up old security events
     */
    private function cleanupSecurityEvents(): array
    {
        $retentionDays = config('logging.security_events_retention_days', 60);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $deletedCount = SecurityEvent::where('created_at', '<', $cutoffDate)->delete();

        return [
            'deleted_count' => $deletedCount,
            'retention_days' => $retentionDays,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Get log statistics before cleanup
     */
    public function getLogStatistics(): array
    {
        return [
            'activity_logs' => [
                'total' => ActivityLog::count(),
                'old_logs' => ActivityLog::where('timestamp', '<', Carbon::now()->subDays(90))->count(),
                'retention_days' => config('logging.activity_logs_retention_days', 90)
            ],
            'system_logs' => [
                'total' => SystemLog::count(),
                'old_logs' => SystemLog::where('created_at', '<', Carbon::now()->subDays(30))->count(),
                'retention_days' => config('logging.system_logs_retention_days', 30)
            ],
            'security_events' => [
                'total' => SecurityEvent::count(),
                'old_logs' => SecurityEvent::where('created_at', '<', Carbon::now()->subDays(60))->count(),
                'retention_days' => config('logging.security_events_retention_days', 60)
            ]
        ];
    }

    /**
     * Log cleanup results
     */
    private function logCleanupResults(array $results): void
    {
        $totalDeleted = array_sum(array_column($results, 'deleted_count'));
        
        SystemLog::create([
            'level' => 'info',
            'message' => "Log cleanup completed. Total deleted: {$totalDeleted}",
            'context' => $results,
            'source' => 'log_cleanup_service'
        ]);

        Log::info('Log cleanup completed', $results);
    }

    /**
     * Archive logs instead of deleting (for compliance)
     */
    public function archiveLogs(): array
    {
        $results = [
            'activity_logs' => $this->archiveActivityLogs(),
            'system_logs' => $this->archiveSystemLogs(),
            'security_events' => $this->archiveSecurityEvents()
        ];

        return $results;
    }

    /**
     * Archive old activity logs
     */
    private function archiveActivityLogs(): array
    {
        $retentionDays = config('logging.activity_logs_retention_days', 90);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $oldLogs = ActivityLog::where('timestamp', '<', $cutoffDate)->get();
        $archivedCount = 0;

        foreach ($oldLogs as $log) {
            // Here you would typically move to archive storage
            // For now, we'll just mark as archived
            $log->update(['archived' => true]);
            $archivedCount++;
        }

        return [
            'archived_count' => $archivedCount,
            'retention_days' => $retentionDays
        ];
    }

    /**
     * Archive old system logs
     */
    private function archiveSystemLogs(): array
    {
        $retentionDays = config('logging.system_logs_retention_days', 30);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $oldLogs = SystemLog::where('created_at', '<', $cutoffDate)->get();
        $archivedCount = 0;

        foreach ($oldLogs as $log) {
            // Here you would typically move to archive storage
            // For now, we'll just mark as archived
            $log->update(['archived' => true]);
            $archivedCount++;
        }

        return [
            'archived_count' => $archivedCount,
            'retention_days' => $retentionDays
        ];
    }

    /**
     * Archive old security events
     */
    private function archiveSecurityEvents(): array
    {
        $retentionDays = config('logging.security_events_retention_days', 60);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $oldEvents = SecurityEvent::where('created_at', '<', $cutoffDate)->get();
        $archivedCount = 0;

        foreach ($oldEvents as $event) {
            // Here you would typically move to archive storage
            // For now, we'll just mark as archived
            $event->update(['archived' => true]);
            $archivedCount++;
        }

        return [
            'archived_count' => $archivedCount,
            'retention_days' => $retentionDays
        ];
    }

    /**
     * Optimize log tables
     */
    public function optimizeLogTables(): array
    {
        $results = [];

        try {
            // Optimize activity_logs table
            \DB::statement('VACUUM activity_logs');
            $results['activity_logs'] = 'optimized';
        } catch (\Exception $e) {
            $results['activity_logs'] = 'error: ' . $e->getMessage();
        }

        try {
            // Optimize system_logs table
            \DB::statement('VACUUM system_logs');
            $results['system_logs'] = 'optimized';
        } catch (\Exception $e) {
            $results['system_logs'] = 'error: ' . $e->getMessage();
        }

        try {
            // Optimize security_events table
            \DB::statement('VACUUM security_events');
            $results['security_events'] = 'optimized';
        } catch (\Exception $e) {
            $results['security_events'] = 'error: ' . $e->getMessage();
        }

        return $results;
    }
} 