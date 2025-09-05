<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemLog;

class SystemLogController extends Controller
{
    /**
     * Display a listing of system logs
     */
    public function index(Request $request)
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
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $logs = $query->paginate(20);

        // Get statistics
        $stats = [
            'total_logs' => SystemLog::count(),
            'errors_today' => SystemLog::where('level', 'error')
                ->whereDate('created_at', today())
                ->count(),
            'warnings_today' => SystemLog::where('level', 'warning')
                ->whereDate('created_at', today())
                ->count(),
            'critical_alerts' => SystemLog::where('level', 'critical')
                ->whereDate('created_at', today())
                ->count()
        ];

        return view('admin.system-logs', compact('logs', 'stats'));
    }

    /**
     * Export system logs
     */
    public function export(Request $request)
    {
        $query = SystemLog::orderBy('created_at', 'desc');

        // Apply same filters as index
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
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $logs = $query->get();

        $filename = 'system_logs_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $filePath = storage_path('app/temp/' . $filename);

        // Ensure directory exists
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        $file = fopen($filePath, 'w');
        
        // CSV headers
        fputcsv($file, ['Timestamp', 'Level', 'Message', 'Source', 'Context']);

        // CSV data
        foreach ($logs as $log) {
            fputcsv($file, [
                $log->created_at->format('Y-m-d H:i:s'),
                $log->level,
                $log->message,
                $log->source,
                json_encode($log->context)
            ]);
        }

        fclose($file);

        return response()->download($filePath, $filename)->deleteFileAfterSend();
    }

    /**
     * Clean old system logs
     */
    public function cleanup(Request $request)
    {
        $retentionDays = $request->get('retention_days', 30);
        $cutoffDate = now()->subDays($retentionDays);

        $deletedCount = SystemLog::where('created_at', '<', $cutoffDate)->delete();

        return redirect()->route('admin.system-logs')
            ->with('success', "Cleaned up {$deletedCount} old system logs");
    }
} 