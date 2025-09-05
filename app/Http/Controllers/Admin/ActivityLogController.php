<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activity logs
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->orderBy('timestamp', 'desc');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('username', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('user_filter')) {
            $query->whereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('username', 'like', "%{$request->user_filter}%");
            });
        }

        if ($request->filled('action_filter')) {
            $query->where('action', 'like', "%{$request->action_filter}%");
        }

        if ($request->filled('date_from')) {
            $query->where('timestamp', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('timestamp', '<=', $request->date_to . ' 23:59:59');
        }

        $activities = $query->paginate(20);

        // Get statistics
        $stats = [
            'total_activities' => ActivityLog::count(),
            'todays_activities' => ActivityLog::whereDate('timestamp', today())->count(),
            'active_users' => User::whereHas('activityLogs', function ($query) {
                $query->whereDate('timestamp', today());
            })->count()
        ];

        return view('admin.activity-logs', compact('activities', 'stats'));
    }

    /**
     * Export activity logs
     */
    public function export(Request $request)
    {
        $query = ActivityLog::with('user')->orderBy('timestamp', 'desc');

        // Apply same filters as index
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('username', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('user_filter')) {
            $query->whereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('username', 'like', "%{$request->user_filter}%");
            });
        }

        if ($request->filled('action_filter')) {
            $query->where('action', 'like', "%{$request->action_filter}%");
        }

        if ($request->filled('date_from')) {
            $query->where('timestamp', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('timestamp', '<=', $request->date_to . ' 23:59:59');
        }

        $activities = $query->get();

        $filename = 'activity_logs_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $filePath = storage_path('app/temp/' . $filename);

        // Ensure directory exists
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        $file = fopen($filePath, 'w');
        
        // CSV headers
        fputcsv($file, ['Timestamp', 'User', 'Action', 'IP Address', 'User Agent']);

        // CSV data
        foreach ($activities as $activity) {
            fputcsv($file, [
                $activity->timestamp->format('Y-m-d H:i:s'),
                $activity->user ? $activity->user->username : 'System',
                $activity->action,
                $activity->ip_address,
                $activity->user_agent
            ]);
        }

        fclose($file);

        return response()->download($filePath, $filename)->deleteFileAfterSend();
    }

    /**
     * Clean old activity logs
     */
    public function cleanup(Request $request)
    {
        $retentionDays = $request->get('retention_days', 90);
        $cutoffDate = now()->subDays($retentionDays);

        $deletedCount = ActivityLog::where('timestamp', '<', $cutoffDate)->delete();

        return redirect()->route('admin.activity-logs')
            ->with('success', "Cleaned up {$deletedCount} old activity logs");
    }
} 