<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ActivityLogController extends Controller
{
    /**
     * Get paginated activity logs with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with('user')
            ->orderBy('timestamp', 'desc');

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
            $query->where('timestamp', '>=', Carbon::parse($request->date_from));
        }

        if ($request->filled('date_to')) {
            $query->where('timestamp', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $perPage = $request->get('per_page', 15);
        $activities = $query->paginate($perPage);

        // Get statistics
        $totalActivities = ActivityLog::count();
        $todaysActivities = ActivityLog::whereDate('timestamp', today())->count();
        $activeUsers = User::whereHas('activityLogs', function ($query) {
            $query->whereDate('timestamp', today());
        })->count();

        return response()->json([
            'data' => $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'timestamp' => $activity->timestamp->format('d/m/Y, H:i:s'),
                    'user' => $activity->user ? $activity->user->username : 'System',
                    'action' => $activity->action,
                    'ip_address' => $activity->ip_address,
                    'user_agent' => $activity->user_agent,
                    'details' => $activity->details
                ];
            }),
            'meta' => [
                'total_activities' => $totalActivities,
                'todays_activities' => $todaysActivities,
                'active_users' => $activeUsers,
                'pagination' => [
                    'current_page' => $activities->currentPage(),
                    'last_page' => $activities->lastPage(),
                    'per_page' => $activities->perPage(),
                    'total' => $activities->total()
                ]
            ]
        ]);
    }

    /**
     * Get activity log statistics
     */
    public function getStats(): JsonResponse
    {
        $totalActivities = ActivityLog::count();
        $activitiesToday = ActivityLog::whereDate('timestamp', today())->count();
        $uniqueUsersToday = User::whereHas('activityLogs', function ($query) {
            $query->whereDate('timestamp', today());
        })->count();

        return response()->json([
            'total_activities' => $totalActivities,
            'activities_today' => $activitiesToday,
            'unique_users_today' => $uniqueUsersToday
        ]);
    }

    /**
     * Export activity logs as CSV
     */
    public function export(Request $request): JsonResponse
    {
        $query = ActivityLog::with('user')
            ->orderBy('timestamp', 'desc');

        // Apply same filters as index method
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
            $query->where('timestamp', '>=', Carbon::parse($request->date_from));
        }

        if ($request->filled('date_to')) {
            $query->where('timestamp', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $activities = $query->get();

        $csvData = [];
        $csvData[] = ['Timestamp', 'User', 'Action', 'IP Address', 'User Agent'];

        foreach ($activities as $activity) {
            $csvData[] = [
                $activity->timestamp->format('d/m/Y, H:i:s'),
                $activity->user ? $activity->user->username : 'System',
                $activity->action,
                $activity->ip_address,
                $activity->user_agent
            ];
        }

        $filename = 'activity_logs_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
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
            'message' => 'Activity logs exported successfully',
            'download_url' => url('storage/temp/' . $filename),
            'filename' => $filename,
            'total_records' => count($activities)
        ]);
    }

    /**
     * Log new activity (internal use)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'action' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'user_agent' => 'nullable|string',
            'details' => 'nullable|array'
        ]);

        $activity = ActivityLog::create([
            'user_id' => $request->user_id,
            'action' => $request->action,
            'ip_address' => $request->ip_address,
            'user_agent' => $request->user_agent,
            'details' => $request->details,
            'timestamp' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Activity logged successfully',
            'activity_id' => $activity->id
        ], 201);
    }

    /**
     * Clean old activity logs
     */
    public function cleanup(Request $request): JsonResponse
    {
        $retentionDays = $request->get('retention_days', 90);
        $cutoffDate = now()->subDays($retentionDays);

        $deletedCount = ActivityLog::where('timestamp', '<', $cutoffDate)->delete();

        return response()->json([
            'success' => true,
            'message' => "Cleaned up {$deletedCount} old activity logs",
            'deleted_count' => $deletedCount,
            'retention_days' => $retentionDays
        ]);
    }

    /**
     * Get activities for specific user
     */
    public function getUserActivities(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        
        $activities = ActivityLog::where('user_id', $userId)
            ->orderBy('timestamp', 'desc')
            ->paginate(15);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email
            ],
            'activities' => $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'timestamp' => $activity->timestamp->format('d/m/Y, H:i:s'),
                    'action' => $activity->action,
                    'ip_address' => $activity->ip_address,
                    'details' => $activity->details
                ];
            }),
            'meta' => [
                'total_activities' => $activities->total(),
                'pagination' => [
                    'current_page' => $activities->currentPage(),
                    'last_page' => $activities->lastPage(),
                    'per_page' => $activities->perPage()
                ]
            ]
        ]);
    }
} 