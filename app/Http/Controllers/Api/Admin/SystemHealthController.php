<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use App\Services\LogCleanupService;
use Illuminate\Http\JsonResponse;

class SystemHealthController extends Controller
{
    protected SystemHealthService $healthService;
    protected LogCleanupService $cleanupService;

    public function __construct(SystemHealthService $healthService, LogCleanupService $cleanupService)
    {
        $this->healthService = $healthService;
        $this->cleanupService = $cleanupService;
    }

    /**
     * Get system health status
     */
    public function getHealth(): JsonResponse
    {
        $health = $this->healthService->getOverallHealth();
        
        return response()->json($health);
    }

    /**
     * Get system performance metrics
     */
    public function getPerformance(): JsonResponse
    {
        $performance = $this->healthService->getPerformanceMetrics();
        
        return response()->json($performance);
    }

    /**
     * Get log statistics
     */
    public function getLogStatistics(): JsonResponse
    {
        $statistics = $this->cleanupService->getLogStatistics();
        
        return response()->json($statistics);
    }

    /**
     * Clean up old logs
     */
    public function cleanupLogs(): JsonResponse
    {
        $results = $this->cleanupService->cleanupOldLogs();
        
        return response()->json([
            'success' => true,
            'message' => 'Log cleanup completed successfully',
            'results' => $results
        ]);
    }

    /**
     * Archive logs
     */
    public function archiveLogs(): JsonResponse
    {
        $results = $this->cleanupService->archiveLogs();
        
        return response()->json([
            'success' => true,
            'message' => 'Log archiving completed successfully',
            'results' => $results
        ]);
    }

    /**
     * Optimize log tables
     */
    public function optimizeTables(): JsonResponse
    {
        $results = $this->cleanupService->optimizeLogTables();
        
        return response()->json([
            'success' => true,
            'message' => 'Table optimization completed',
            'results' => $results
        ]);
    }

    /**
     * Get system alerts
     */
    public function getAlerts(): JsonResponse
    {
        $alerts = [];

        // Check for critical system issues
        $health = $this->healthService->getOverallHealth();
        $healthPercentage = (float) str_replace('%', '', $health['overall_health']);
        
        if ($healthPercentage < 80) {
            $alerts[] = [
                'type' => 'critical',
                'message' => 'System health is below 80%',
                'value' => $health['overall_health'],
                'timestamp' => now()->format('Y-m-d H:i:s')
            ];
        }

        // Check for storage issues
        $storage = $health['components']['storage'];
        if ($storage['status'] === 'warning') {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'Storage usage is high',
                'value' => $storage['usage'],
                'timestamp' => now()->format('Y-m-d H:i:s')
            ];
        }

        // Check for memory issues
        $memory = $health['components']['memory'];
        if ($memory['status'] === 'warning') {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'Memory usage is high',
                'value' => $memory['usage'],
                'timestamp' => now()->format('Y-m-d H:i:s')
            ];
        }

        return response()->json([
            'alerts' => $alerts,
            'total_alerts' => count($alerts),
            'critical_count' => collect($alerts)->where('type', 'critical')->count(),
            'warning_count' => collect($alerts)->where('type', 'warning')->count()
        ]);
    }

    /**
     * Get system summary
     */
    public function getSummary(): JsonResponse
    {
        $health = $this->healthService->getOverallHealth();
        $performance = $this->healthService->getPerformanceMetrics();
        $logStats = $this->cleanupService->getLogStatistics();

        return response()->json([
            'system_health' => $health['overall_health'],
            'performance_score' => $performance['performance_score'],
            'log_statistics' => $logStats,
            'last_updated' => now()->format('Y-m-d H:i:s')
        ]);
    }
} 