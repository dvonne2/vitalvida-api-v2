<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class SystemLogController extends Controller
{
    /**
     * Get paginated system logs with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = SystemLog::orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('source', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $perPage = $request->get('per_page', 15);
        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'level' => $log->level,
                    'message' => $log->message,
                    'timestamp' => $log->created_at->format('Y-m-d H:i:s'),
                    'context' => $log->context,
                    'source' => $log->source
                ];
            }),
            'meta' => [
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total()
                ]
            ]
        ]);
    }

    /**
     * Get system log statistics
     */
    public function getStats(): JsonResponse
    {
        $totalLogs = SystemLog::count();
        $errorsToday = SystemLog::where('level', 'error')
            ->whereDate('created_at', today())
            ->count();
        $warningsToday = SystemLog::where('level', 'warning')
            ->whereDate('created_at', today())
            ->count();
        $criticalAlerts = SystemLog::where('level', 'critical')
            ->whereDate('created_at', today())
            ->count();

        return response()->json([
            'total_logs' => $totalLogs,
            'errors_today' => $errorsToday,
            'warnings_today' => $warningsToday,
            'critical_alerts' => $criticalAlerts
        ]);
    }

    /**
     * Get error logs specifically
     */
    public function getErrorLogs(Request $request): JsonResponse
    {
        $query = SystemLog::whereIn('level', ['error', 'critical'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $perPage = $request->get('per_page', 15);
        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'level' => $log->level,
                    'message' => $log->message,
                    'timestamp' => $log->created_at->format('Y-m-d H:i:s'),
                    'context' => $log->context,
                    'source' => $log->source
                ];
            }),
            'meta' => [
                'total_errors' => SystemLog::whereIn('level', ['error', 'critical'])->count(),
                'errors_today' => SystemLog::whereIn('level', ['error', 'critical'])
                    ->whereDate('created_at', today())
                    ->count(),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total()
                ]
            ]
        ]);
    }

    /**
     * Get system metrics
     */
    public function getSystemMetrics(): JsonResponse
    {
        $today = today();
        $yesterday = today()->subDay();

        $metrics = [
            'today' => [
                'total' => SystemLog::whereDate('created_at', $today)->count(),
                'info' => SystemLog::where('level', 'info')->whereDate('created_at', $today)->count(),
                'warning' => SystemLog::where('level', 'warning')->whereDate('created_at', $today)->count(),
                'error' => SystemLog::where('level', 'error')->whereDate('created_at', $today)->count(),
                'critical' => SystemLog::where('level', 'critical')->whereDate('created_at', $today)->count(),
            ],
            'yesterday' => [
                'total' => SystemLog::whereDate('created_at', $yesterday)->count(),
                'info' => SystemLog::where('level', 'info')->whereDate('created_at', $yesterday)->count(),
                'warning' => SystemLog::where('level', 'warning')->whereDate('created_at', $yesterday)->count(),
                'error' => SystemLog::where('level', 'error')->whereDate('created_at', $yesterday)->count(),
                'critical' => SystemLog::where('level', 'critical')->whereDate('created_at', $yesterday)->count(),
            ],
            'sources' => SystemLog::selectRaw('source, COUNT(*) as count')
                ->whereNotNull('source')
                ->groupBy('source')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
        ];

        return response()->json($metrics);
    }

    /**
     * Export system logs
     */
    public function exportLogs(Request $request): JsonResponse
    {
        $query = SystemLog::orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $logs = $query->get();

        $csvData = [];
        $csvData[] = ['Timestamp', 'Level', 'Message', 'Source', 'Context'];

        foreach ($logs as $log) {
            $csvData[] = [
                $log->created_at->format('Y-m-d H:i:s'),
                $log->level,
                $log->message,
                $log->source,
                json_encode($log->context)
            ];
        }

        $filename = 'system_logs_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        // Store CSV file temporarily
        $filePath = storage_path('app/temp/' . $filename);
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        $file = fopen($filePath, 'w');
        foreach ($csvData as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        return response()->json([
            'success' => true,
            'message' => 'System logs exported successfully',
            'download_url' => url('storage/temp/' . $filename),
            'filename' => $filename,
            'total_records' => count($logs)
        ]);
    }

    /**
     * Clean old system logs
     */
    public function cleanup(Request $request): JsonResponse
    {
        $retentionDays = $request->get('retention_days', 30);
        $cutoffDate = now()->subDays($retentionDays);

        $deletedCount = SystemLog::where('created_at', '<', $cutoffDate)->delete();

        return response()->json([
            'success' => true,
            'message' => "Cleaned up {$deletedCount} old system logs",
            'deleted_count' => $deletedCount,
            'retention_days' => $retentionDays
        ]);
    }
} 