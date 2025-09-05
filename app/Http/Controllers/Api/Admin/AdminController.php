<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActionLog;
use App\Models\KycLog;
use App\Models\Order;
use App\Models\PaymentLog;
use App\Models\Lead;
use App\Models\PurchaseOrder;
use App\Models\InventoryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    /**
     * Admin Dashboard Overview
     */
    public function dashboard(Request $request)
    {
        $admin = $request->user();

        // Get system statistics
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'pending_kyc' => User::where('kyc_status', 'pending')->count(),
            'approved_kyc' => User::where('kyc_status', 'approved')->count(),
            'rejected_kyc' => User::where('kyc_status', 'rejected')->count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'total_payments' => PaymentLog::where('status', 'completed')->sum('amount'),
            'total_leads' => Lead::count(),
            'active_leads' => Lead::whereIn('status', ['new', 'contacted', 'qualified'])->count(),
            'total_purchase_orders' => PurchaseOrder::count(),
            'pending_purchase_orders' => PurchaseOrder::where('status', 'pending')->count(),
        ];

        // Get recent activities
        $recentActivities = ActionLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user_name' => $log->user ? $log->user->name : 'System',
                    'created_at' => $log->created_at,
                    'ip_address' => $log->ip_address,
                    'risk_level' => $log->risk_level,
                ];
            });

        // Get user distribution by role
        $userDistribution = User::selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->get()
            ->pluck('count', 'role')
            ->toArray();

        // Get system health metrics
        $systemHealth = [
            'database_connections' => \DB::connection()->getPdo() ? 'healthy' : 'error',
            'storage_available' => disk_free_space(storage_path()) > 1000000000 ? 'healthy' : 'warning',
            'last_backup' => now()->subHours(2), // Mock data
            'active_sessions' => \DB::table('personal_access_tokens')->where('expires_at', '>', now())->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_activities' => $recentActivities,
                'user_distribution' => $userDistribution,
                'system_health' => $systemHealth,
                'admin_info' => [
                    'name' => $admin->name,
                    'role' => $admin->role,
                    'last_login' => $admin->last_login_at,
                ]
            ]
        ]);
    }

    /**
     * Get all users with pagination and filters
     */
    public function users(Request $request)
    {
        $query = User::query();

        // Apply filters
        if ($request->has('role') && $request->role) {
            $query->where('role', $request->role);
        }

        if ($request->has('kyc_status') && $request->kyc_status) {
            $query->where('kyc_status', $request->kyc_status);
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->with(['kycLogs', 'actionLogs'])
            ->paginate($request->get('per_page', 20));

        // Transform data for admin view
        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'kyc_status' => $user->kyc_status,
                'is_active' => $user->is_active,
                'email_verified_at' => $user->email_verified_at,
                'phone_verified_at' => $user->phone_verified_at,
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'kyc_documents_count' => $user->kycLogs->count(),
                'recent_activity_count' => $user->actionLogs->where('created_at', '>', now()->subDays(7))->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users,
            'filters' => [
                'roles' => User::ROLES,
                'kyc_statuses' => ['pending', 'approved', 'rejected'],
            ]
        ]);
    }

    /**
     * Get single user details
     */
    public function showUser(Request $request, $id)
    {
        $user = User::with([
            'kycLogs',
            'actionLogs' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(50);
            },
            'purchaseOrders',
            'assignedOrders',
            'paymentLogs',
            'assignedLeads',
            'bonusLogs',
        ])->findOrFail($id);

        // Get user statistics
        $userStats = [
            'total_activities' => $user->actionLogs->count(),
            'recent_activities' => $user->actionLogs->where('created_at', '>', now()->subDays(7))->count(),
            'kyc_documents' => $user->kycLogs->count(),
            'pending_kyc_documents' => $user->kycLogs->where('status', 'pending')->count(),
            'total_purchase_orders' => $user->purchaseOrders->count(),
            'total_orders' => $user->assignedOrders->count(),
            'total_payments' => $user->paymentLogs->count(),
            'total_leads' => $user->assignedLeads->count(),
            'total_bonuses' => $user->bonusLogs->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'kyc_status' => $user->kyc_status,
                    'kyc_data' => $user->kyc_data,
                    'is_active' => $user->is_active,
                    'email_verified_at' => $user->email_verified_at,
                    'phone_verified_at' => $user->phone_verified_at,
                    'last_login_at' => $user->last_login_at,
                    'last_login_ip' => $user->last_login_ip,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'stats' => $userStats,
                'kyc_logs' => $user->kycLogs,
                'recent_activities' => $user->actionLogs->take(10),
            ]
        ]);
    }

    /**
     * Create new user
     */
    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|unique:users|regex:/^[0-9]{11}$/',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => 'required|in:' . implode(',', array_keys(User::ROLES)),
            'kyc_status' => 'sometimes|in:pending,approved,rejected',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'kyc_status' => $request->kyc_status ?? 'pending',
            'is_active' => $request->is_active ?? true,
        ]);

        // Log the action
        ActionLog::create([
            'user_id' => $request->user()->id,
            'action' => 'admin.user.create',
            'model_type' => User::class,
            'model_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'created_user_id' => $user->id,
                'role' => $request->role,
                'kyc_status' => $request->kyc_status ?? 'pending',
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'kyc_status' => $user->kyc_status,
                    'is_active' => $user->is_active,
                ]
            ]
        ], 201);
    }

    /**
     * Update user
     */
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'sometimes|string|unique:users,phone,' . $id . '|regex:/^[0-9]{11}$/',
            'role' => 'sometimes|in:' . implode(',', array_keys(User::ROLES)),
            'kyc_status' => 'sometimes|in:pending,approved,rejected',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $user->toArray();
        $user->update($request->only(['name', 'email', 'phone', 'role', 'kyc_status', 'is_active']));

        // Log the action
        ActionLog::create([
            'user_id' => $request->user()->id,
            'action' => 'admin.user.update',
            'model_type' => User::class,
            'model_id' => $user->id,
            'old_values' => $oldData,
            'new_values' => $user->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'updated_user_id' => $user->id,
                'updated_fields' => array_keys($request->only(['name', 'email', 'phone', 'role', 'kyc_status', 'is_active'])),
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'kyc_status' => $user->kyc_status,
                    'is_active' => $user->is_active,
                ]
            ]
        ]);
    }

    /**
     * Delete user
     */
    public function deleteUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting superadmin users
        if ($user->role === 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete superadmin users',
                'error_code' => 'SUPERADMIN_PROTECTED'
            ], 403);
        }

        // Prevent deleting self
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own account',
                'error_code' => 'SELF_DELETE_PROHIBITED'
            ], 403);
        }

        // Log the action before deletion
        ActionLog::create([
            'user_id' => $request->user()->id,
            'action' => 'admin.user.delete',
            'model_type' => User::class,
            'model_id' => $user->id,
            'old_values' => $user->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'deleted_user_id' => $user->id,
                'deleted_user_role' => $user->role,
            ]
        ]);

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Activate user
     */
    public function activateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => true]);

        ActionLog::create([
            'user_id' => $request->user()->id,
            'action' => 'admin.user.activate',
            'model_type' => User::class,
            'model_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User activated successfully'
        ]);
    }

    /**
     * Deactivate user
     */
    public function deactivateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Prevent deactivating superadmin users
        if ($user->role === 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate superadmin users',
                'error_code' => 'SUPERADMIN_PROTECTED'
            ], 403);
        }

        // Prevent deactivating self
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate your own account',
                'error_code' => 'SELF_DEACTIVATE_PROHIBITED'
            ], 403);
        }

        $user->update(['is_active' => false]);

        ActionLog::create([
            'user_id' => $request->user()->id,
            'action' => 'admin.user.deactivate',
            'model_type' => User::class,
            'model_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User deactivated successfully'
        ]);
    }

    /**
     * Get pending KYC applications
     */
    public function kycPending(Request $request)
    {
        $query = User::where('kyc_status', 'pending');

        // Apply filters
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $applications = $query->with(['kycLogs' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }])
        ->orderBy('created_at', 'desc')
        ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }

    /**
     * Approve KYC application
     */
    public function approveKyc(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->kyc_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'KYC application is not pending',
                'error_code' => 'KYC_NOT_PENDING'
            ], 400);
        }

        $user->update([
            'kyc_status' => 'approved',
            'kyc_data' => array_merge($user->kyc_data ?? [], [
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
            ])
        ]);

        // Update all KYC logs to approved
        KycLog::where('user_id', $id)->update([
            'status' => 'approved',
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        ActionLog::create([
            'user_id' => $request->user()->id,
            'action' => 'admin.kyc.approve',
            'model_type' => User::class,
            'model_id' => $id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'approved_user_id' => $id,
                'kyc_status' => 'approved'
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'KYC application approved successfully'
        ]);
    }

    /**
     * Reject KYC application
     */
    public function rejectKyc(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);

        if ($user->kyc_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'KYC application is not pending',
                'error_code' => 'KYC_NOT_PENDING'
            ], 400);
        }

        $user->update([
            'kyc_status' => 'rejected',
            'kyc_data' => array_merge($user->kyc_data ?? [], [
                'rejected_at' => now(),
                'rejected_by' => $request->user()->id,
                'rejection_reason' => $request->reason,
            ])
        ]);

        // Update all KYC logs to rejected
        KycLog::where('user_id', $id)->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        ActionLog::create([
            'user_id' => $request->user()->id,
            'action' => 'admin.kyc.reject',
            'model_type' => User::class,
            'model_id' => $id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'rejected_user_id' => $id,
                'kyc_status' => 'rejected',
                'reason' => $request->reason
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'KYC application rejected successfully'
        ]);
    }

    /**
     * Get audit logs
     */
    public function auditLogs(Request $request)
    {
        $query = ActionLog::with('user');

        // Apply filters
        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('action') && $request->action) {
            $query->where('action', 'like', "%{$request->action}%");
        }

        if ($request->has('risk_level') && $request->risk_level) {
            $query->where('risk_level', $request->risk_level);
        }

        if ($request->has('is_suspicious') && $request->is_suspicious !== '') {
            $query->where('is_suspicious', $request->boolean('is_suspicious'));
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $logs,
            'filters' => [
                'risk_levels' => ['low', 'medium', 'high', 'critical'],
                'common_actions' => ActionLog::select('action')
                    ->distinct()
                    ->orderBy('action')
                    ->pluck('action')
                    ->take(20),
            ]
        ]);
    }

    /**
     * Get system metrics
     */
    public function systemMetrics(Request $request)
    {
        $metrics = [
            'database' => [
                'total_users' => User::count(),
                'total_orders' => Order::count(),
                'total_payments' => PaymentLog::count(),
                'total_leads' => Lead::count(),
                'total_purchase_orders' => PurchaseOrder::count(),
                'total_inventory_logs' => InventoryLog::count(),
            ],
            'performance' => [
                'avg_response_time' => 150, // Mock data - implement real metrics
                'requests_per_minute' => 45,
                'error_rate' => 0.02,
                'active_connections' => 12,
            ],
            'storage' => [
                'disk_usage' => disk_total_space(storage_path()) - disk_free_space(storage_path()),
                'disk_free' => disk_free_space(storage_path()),
                'disk_total' => disk_total_space(storage_path()),
                'file_count' => 1250, // Mock data
            ],
            'security' => [
                'failed_login_attempts' => ActionLog::where('action', 'user.login')
                    ->where('created_at', '>', now()->subHour())
                    ->where('risk_level', 'high')
                    ->count(),
                'suspicious_activities' => ActionLog::where('is_suspicious', true)
                    ->where('created_at', '>', now()->subDay())
                    ->count(),
                'active_sessions' => \DB::table('personal_access_tokens')
                    ->where('expires_at', '>', now())
                    ->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }
} 