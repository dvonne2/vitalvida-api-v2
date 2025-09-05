<?php

namespace App\Http\Controllers;

use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SecurityController extends Controller
{
    /**
     * Get security dashboard overview
     */
    public function dashboard(Request $request)
    {
        try {
            $days = $request->get('days', 7);
            
            // Get security statistics
            $stats = SecurityLog::getSecurityStats($days);
            
            // Get recent suspicious activities
            $recentSuspicious = SecurityLog::where('created_at', '>=', now()->subDays($days))
                ->suspicious()
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            // Get failed login attempts
            $failedLogins = SecurityLog::where('created_at', '>=', now()->subDays($days))
                ->where('event_type', 'failed_login')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            // Get top IP addresses with suspicious activities
            $topSuspiciousIPs = SecurityLog::where('created_at', '>=', now()->subDays($days))
                ->where('is_suspicious', true)
                ->select('ip_address', DB::raw('count(*) as count'))
                ->groupBy('ip_address')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();
            
            // Get authentication events by hour (last 24 hours)
            $authEventsByHour = SecurityLog::where('created_at', '>=', now()->subDay())
                ->authEvents()
                ->select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hour'),
                    DB::raw('count(*) as count')
                )
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'recent_suspicious' => $recentSuspicious,
                    'failed_logins' => $failedLogins,
                    'top_suspicious_ips' => $topSuspiciousIPs,
                    'auth_events_by_hour' => $authEventsByHour,
                    'generated_at' => now()->toISOString(),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load security dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get security logs with filtering
     */
    public function logs(Request $request)
    {
        try {
            $query = SecurityLog::with('user');
            
            // Apply filters
            if ($request->has('event_type')) {
                $query->where('event_type', $request->event_type);
            }
            
            if ($request->has('risk_level')) {
                $query->where('risk_level', $request->risk_level);
            }
            
            if ($request->has('ip_address')) {
                $query->where('ip_address', 'like', '%' . $request->ip_address . '%');
            }
            
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            
            if ($request->has('suspicious')) {
                $query->where('is_suspicious', $request->boolean('suspicious'));
            }
            
            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }
            
            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 50);
            $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $logs
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load security logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get user security profile
     */
    public function userProfile(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            // Get user's recent security events
            $recentEvents = SecurityLog::getRecentUserEvents($userId, 168); // Last 7 days
            
            // Get user's login history
            $loginHistory = SecurityLog::where('user_id', $userId)
                ->whereIn('event_type', ['login', 'logout', 'failed_login'])
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
            
            // Get suspicious activities for this user
            $suspiciousActivities = SecurityLog::where('user_id', $userId)
                ->suspicious()
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            // Get IP addresses used by this user
            $ipAddresses = SecurityLog::where('user_id', $userId)
                ->select('ip_address', DB::raw('count(*) as count'), DB::raw('max(created_at) as last_seen'))
                ->groupBy('ip_address')
                ->orderBy('last_seen', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'recent_events' => $recentEvents,
                    'login_history' => $loginHistory,
                    'suspicious_activities' => $suspiciousActivities,
                    'ip_addresses' => $ipAddresses,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load user security profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get IP address security profile
     */
    public function ipProfile(Request $request, $ipAddress)
    {
        try {
            // Get all activities from this IP
            $activities = SecurityLog::where('ip_address', $ipAddress)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
            
            // Get suspicious activities from this IP
            $suspiciousActivities = SecurityLog::getSuspiciousFromIP($ipAddress, 168); // Last 7 days
            
            // Get users associated with this IP
            $users = SecurityLog::where('ip_address', $ipAddress)
                ->whereNotNull('user_id')
                ->with('user')
                ->select('user_id')
                ->distinct()
                ->get()
                ->pluck('user');
            
            // Get activity summary
            $summary = [
                'total_activities' => SecurityLog::where('ip_address', $ipAddress)->count(),
                'suspicious_activities' => SecurityLog::where('ip_address', $ipAddress)->suspicious()->count(),
                'failed_requests' => SecurityLog::where('ip_address', $ipAddress)->failed()->count(),
                'high_risk_events' => SecurityLog::where('ip_address', $ipAddress)->highRisk()->count(),
                'unique_users' => SecurityLog::where('ip_address', $ipAddress)->distinct('user_id')->count(),
                'first_seen' => SecurityLog::where('ip_address', $ipAddress)->min('created_at'),
                'last_seen' => SecurityLog::where('ip_address', $ipAddress)->max('created_at'),
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'ip_address' => $ipAddress,
                    'summary' => $summary,
                    'activities' => $activities,
                    'suspicious_activities' => $suspiciousActivities,
                    'users' => $users,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load IP security profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Clean old security logs
     */
    public function cleanLogs(Request $request)
    {
        try {
            $days = $request->get('days', 90);
            $deletedCount = SecurityLog::cleanOldLogs($days);
            
            return response()->json([
                'success' => true,
                'message' => "Cleaned {$deletedCount} old security logs (older than {$days} days)"
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clean security logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get security recommendations
     */
    public function recommendations(Request $request)
    {
        try {
            $days = $request->get('days', 7);
            $stats = SecurityLog::getSecurityStats($days);
            
            $recommendations = [];
            
            // Analyze and generate recommendations
            if ($stats['suspicious_events'] > 10) {
                $recommendations[] = [
                    'type' => 'warning',
                    'title' => 'High Suspicious Activity',
                    'message' => 'Consider tightening security rules and reviewing access patterns',
                    'priority' => 'medium'
                ];
            }
            
            if ($stats['failed_requests'] > 50) {
                $recommendations[] = [
                    'type' => 'warning',
                    'title' => 'High Failed Requests',
                    'message' => 'Check for potential attacks or configuration issues',
                    'priority' => 'high'
                ];
            }
            
            if ($stats['high_risk_events'] > 5) {
                $recommendations[] = [
                    'type' => 'critical',
                    'title' => 'Critical Security Events',
                    'message' => 'Immediate attention required - review security logs',
                    'priority' => 'critical'
                ];
            }
            
            // General recommendations
            $recommendations[] = [
                'type' => 'info',
                'title' => 'Regular Security Review',
                'message' => 'Ensure all security patches are up to date',
                'priority' => 'low'
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'recommendations' => $recommendations,
                    'stats' => $stats,
                    'generated_at' => now()->toISOString(),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate security recommendations',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 