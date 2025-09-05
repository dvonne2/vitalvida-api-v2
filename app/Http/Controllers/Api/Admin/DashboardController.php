<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\SystemLog;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $currentMonth = now()->startOfMonth();
            $lastMonth = now()->subMonth()->startOfMonth();

            // User statistics
            $totalUsers = User::count();
            $currentMonthUsers = User::where('created_at', '>=', $currentMonth)->count();
            $lastMonthUsers = User::whereBetween('created_at', [$lastMonth, $currentMonth])->count();
            $usersGrowth = $lastMonthUsers > 0 ? round((($currentMonthUsers - $lastMonthUsers) / $lastMonthUsers) * 100, 1) : 0;

            // System health (mock data for now)
            $systemHealth = 99.9;
            $healthChange = 0.1;

            // Security events
            $securityEvents = SecurityEvent::where('created_at', '>=', $currentMonth)->count();
            $lastMonthSecurityEvents = SecurityEvent::whereBetween('created_at', [$lastMonth, $currentMonth])->count();
            $securityChange = $lastMonthSecurityEvents > 0 ? round((($securityEvents - $lastMonthSecurityEvents) / $lastMonthSecurityEvents) * 100, 1) : 0;

            // Performance metrics (mock data)
            $performance = 'Fast';
            $performanceChange = 5.0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_users' => $totalUsers,
                    'users_growth' => $usersGrowth > 0 ? "+{$usersGrowth}%" : "{$usersGrowth}%",
                    'system_health' => "{$systemHealth}%",
                    'health_status' => $healthChange > 0 ? "+{$healthChange}%" : "{$healthChange}%",
                    'security_events' => $securityEvents,
                    'events_change' => $securityChange > 0 ? "+{$securityChange}%" : "{$securityChange}%",
                    'performance' => $performance,
                    'performance_change' => "+{$performanceChange}%"
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent activity
     */
    public function getRecentActivity(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 10);
            $hours = $request->get('hours', 24);

            $activities = ActivityLog::with('user')
                ->where('created_at', '>=', now()->subHours($hours))
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($activity) {
                    return [
                        'type' => $activity->action,
                        'message' => $this->getActivityMessage($activity),
                        'time' => $activity->time_ago,
                        'status' => $this->getActivityStatus($activity->action),
                        'user' => $activity->user ? $activity->user->name : 'System',
                        'ip_address' => $activity->ip_address,
                        'details' => $activity->details
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'activities' => $activities
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load recent activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system overview
     */
    public function getSystemOverview(): JsonResponse
    {
        try {
            $overview = [
                'active_users' => User::where('is_active', true)->count(),
                'pending_kyc' => User::where('kyc_status', 'pending')->count(),
                'recent_logins' => ActivityLog::where('action', 'login')
                    ->where('created_at', '>=', now()->subHours(24))
                    ->count(),
                'security_alerts' => SecurityEvent::suspicious()
                    ->where('created_at', '>=', now()->subHours(24))
                    ->count(),
                'system_errors' => SystemLog::error()
                    ->where('created_at', '>=', now()->subHours(24))
                    ->count(),
                'database_connections' => DB::connection()->getPdo() ? 'healthy' : 'error',
                'storage_available' => disk_free_space(storage_path()) > 1000000000 ? 'healthy' : 'warning',
                'last_backup' => now()->subHours(2), // Mock data
                'active_sessions' => DB::table('personal_access_tokens')
                    ->where('expires_at', '>', now())
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $overview
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load system overview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get quick actions
     */
    public function getQuickActions(): JsonResponse
    {
        try {
            $user = auth()->user();
            $actions = [];

            // User management actions
            if ($user->can('manage_users')) {
                $actions[] = [
                    'id' => 'create_user',
                    'title' => 'Create New User',
                    'description' => 'Add a new user to the system',
                    'icon' => '👤',
                    'action' => 'POST',
                    'endpoint' => '/api/admin/users',
                    'color' => 'blue'
                ];
            }

            // System logs
            if ($user->can('view_reports')) {
                $actions[] = [
                    'id' => 'view_logs',
                    'title' => 'View System Logs',
                    'description' => 'Check system activity and errors',
                    'icon' => '📊',
                    'action' => 'GET',
                    'endpoint' => '/api/admin/system/logs',
                    'color' => 'green'
                ];
            }

            // Database backup
            if ($user->can('manage_system')) {
                $actions[] = [
                    'id' => 'backup_database',
                    'title' => 'Backup Database',
                    'description' => 'Create a database backup',
                    'icon' => '💾',
                    'action' => 'POST',
                    'endpoint' => '/api/admin/database/backup',
                    'color' => 'orange'
                ];
            }

            // Security monitoring
            if ($user->can('view_reports')) {
                $actions[] = [
                    'id' => 'security_monitor',
                    'title' => 'Security Monitor',
                    'description' => 'View security events and alerts',
                    'icon' => '🛡️',
                    'action' => 'GET',
                    'endpoint' => '/api/admin/security/events',
                    'color' => 'red'
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'actions' => $actions
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load quick actions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity message based on action type
     */
    private function getActivityMessage($activity): string
    {
        return match($activity->action) {
            'login' => 'Logged into system',
            'logout' => 'Logged out of system',
            'login_failed' => 'Failed login attempt',
            'profile_update' => 'Updated profile settings',
            'password_change' => 'Changed password',
            'user_created' => 'Created new user account',
            'user_updated' => 'Updated user information',
            'user_deleted' => 'Deleted user account',
            'role_updated' => 'Updated user role',
            'status_updated' => 'Updated user status',
            'report_generated' => 'Generated system report',
            'backup_created' => 'Created database backup',
            'security_alert' => 'Security alert triggered',
            default => ucfirst(str_replace('_', ' ', $activity->action))
        };
    }

    /**
     * Get activity status based on action type
     */
    private function getActivityStatus($action): string
    {
        return match($action) {
            'login', 'profile_update', 'password_change', 'user_created', 'user_updated', 'role_updated', 'status_updated', 'backup_created' => 'success',
            'login_failed', 'security_alert' => 'error',
            'logout', 'user_deleted', 'report_generated' => 'info',
            default => 'info'
        };
    }
} 