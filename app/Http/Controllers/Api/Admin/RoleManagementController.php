<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleManagementController extends Controller
{
    /**
     * Get all roles with user counts
     */
    public function roles(Request $request)
    {
        $roles = collect(User::ROLES)->map(function ($label, $role) {
            $userCount = User::where('role', $role)->count();
            $activeCount = User::where('role', $role)->where('is_active', true)->count();
            
            return [
                'role' => $role,
                'label' => $label,
                'total_users' => $userCount,
                'active_users' => $activeCount,
                'inactive_users' => $userCount - $activeCount,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Get users by role
     */
    public function usersByRole(Request $request, $role)
    {
        if (!array_key_exists($role, User::ROLES)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role',
                'error_code' => 'INVALID_ROLE'
            ], 400);
        }

        $query = User::where('role', $role);

        // Apply filters
        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('kyc_status') && $request->kyc_status) {
            $query->where('kyc_status', $request->kyc_status);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->with(['kycLogs', 'actionLogs'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'role_label' => User::ROLES[$role],
                'users' => $users,
            ]
        ]);
    }

    /**
     * Bulk assign role to users
     */
    public function bulkAssignRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'role' => 'required|in:' . implode(',', array_keys(User::ROLES)),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Prevent assigning superadmin role to multiple users
        if ($request->role === 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot bulk assign superadmin role',
                'error_code' => 'SUPERADMIN_BULK_ASSIGN_PROHIBITED'
            ], 403);
        }

        $users = User::whereIn('id', $request->user_ids)->get();
        $updatedCount = 0;

        foreach ($users as $user) {
            $oldRole = $user->role;
            
            // Prevent changing superadmin roles
            if ($oldRole === 'superadmin') {
                continue;
            }

            $user->update(['role' => $request->role]);
            $updatedCount++;

            // Log the action
            ActionLog::create([
                'user_id' => $request->user()->id,
                'action' => 'admin.role.bulk_assign',
                'model_type' => User::class,
                'model_id' => $user->id,
                'old_values' => ['role' => $oldRole],
                'new_values' => ['role' => $request->role],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'assigned_user_id' => $user->id,
                    'old_role' => $oldRole,
                    'new_role' => $request->role,
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully assigned role to {$updatedCount} users",
            'data' => [
                'updated_count' => $updatedCount,
                'total_requested' => count($request->user_ids),
                'role' => $request->role,
                'role_label' => User::ROLES[$request->role],
            ]
        ]);
    }

    /**
     * Get role statistics
     */
    public function roleStats(Request $request)
    {
        $stats = [];

        foreach (User::ROLES as $role => $label) {
            $totalUsers = User::where('role', $role)->count();
            $activeUsers = User::where('role', $role)->where('is_active', true)->count();
            $pendingKyc = User::where('role', $role)->where('kyc_status', 'pending')->count();
            $approvedKyc = User::where('role', $role)->where('kyc_status', 'approved')->count();

            $stats[$role] = [
                'label' => $label,
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'inactive_users' => $totalUsers - $activeUsers,
                'pending_kyc' => $pendingKyc,
                'approved_kyc' => $approvedKyc,
                'activity_rate' => $totalUsers > 0 ? ($activeUsers / $totalUsers) * 100 : 0,
            ];
        }

        // Get role distribution over time (last 30 days)
        $roleDistribution = User::selectRaw('role, DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('role', 'date')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'role_distribution' => $roleDistribution,
            ]
        ]);
    }

    /**
     * Get role permissions (for future expansion)
     */
    public function rolePermissions(Request $request)
    {
        // This is a placeholder for future permission system
        $permissions = [
            'superadmin' => [
                'all_permissions' => true,
                'user_management' => true,
                'role_management' => true,
                'kyc_management' => true,
                'system_configuration' => true,
                'audit_logs' => true,
                'reports' => true,
            ],
            'ceo' => [
                'user_management' => true,
                'role_management' => true,
                'kyc_management' => true,
                'system_configuration' => true,
                'audit_logs' => true,
                'reports' => true,
            ],
            'cfo' => [
                'financial_reports' => true,
                'payment_management' => true,
                'audit_logs' => true,
                'reports' => true,
            ],
            'accountant' => [
                'financial_reports' => true,
                'payment_management' => true,
                'reports' => true,
            ],
            'production' => [
                'inventory_management' => true,
                'order_management' => true,
                'reports' => true,
            ],
            'inventory' => [
                'inventory_management' => true,
                'reports' => true,
            ],
            'telesales' => [
                'lead_management' => true,
                'order_management' => true,
                'reports' => true,
            ],
            'da' => [
                'delivery_management' => true,
                'order_management' => true,
                'reports' => true,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    /**
     * Get user activity by role
     */
    public function userActivityByRole(Request $request, $role)
    {
        if (!array_key_exists($role, User::ROLES)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role',
                'error_code' => 'INVALID_ROLE'
            ], 400);
        }

        $users = User::where('role', $role)->pluck('id');

        $activities = ActionLog::whereIn('user_id', $users)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'role_label' => User::ROLES[$role],
                'activities' => $activities,
            ]
        ]);
    }

    /**
     * Get role performance metrics
     */
    public function rolePerformance(Request $request)
    {
        $performance = [];

        foreach (User::ROLES as $role => $label) {
            $userIds = User::where('role', $role)->pluck('id');
            
            $performance[$role] = [
                'label' => $label,
                'total_users' => $userIds->count(),
                'recent_activities' => ActionLog::whereIn('user_id', $userIds)
                    ->where('created_at', '>', now()->subDays(7))
                    ->count(),
                'avg_activities_per_user' => $userIds->count() > 0 ? 
                    ActionLog::whereIn('user_id', $userIds)->count() / $userIds->count() : 0,
                'suspicious_activities' => ActionLog::whereIn('user_id', $userIds)
                    ->where('is_suspicious', true)
                    ->where('created_at', '>', now()->subDays(30))
                    ->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $performance
        ]);
    }
} 