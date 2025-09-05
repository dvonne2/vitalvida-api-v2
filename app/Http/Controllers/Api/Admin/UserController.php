<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Get paginated list of users with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = User::query();

            // Search filter
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // Role filter
            if ($request->has('role_filter')) {
                $query->where('role', $request->get('role_filter'));
            }

            // Status filter
            if ($request->has('status_filter')) {
                $status = $request->get('status_filter');
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $query->where('is_active', false);
                }
            }

            // KYC status filter
            if ($request->has('kyc_filter')) {
                $query->where('kyc_status', $request->get('kyc_filter'));
            }

            // Date range filter
            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->get('date_from'));
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->get('date_to'));
            }

            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate
            $perPage = $request->get('per_page', 15);
            $users = $query->paginate($perPage);

            // Transform data
            $users->getCollection()->transform(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'role_display' => $user->getRoleDisplayName(),
                    'status' => $user->is_active ? 'active' : 'inactive',
                    'kyc_status' => $user->kyc_status,
                    'last_login' => $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never',
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    'profile_completion' => $user->getProfileCompletionPercentage(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $users,
                'filters' => [
                    'search' => $request->get('search'),
                    'role_filter' => $request->get('role_filter'),
                    'status_filter' => $request->get('status_filter'),
                    'kyc_filter' => $request->get('kyc_filter'),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed user information
     */
    public function show($id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            // Get recent activity
            $recentActivity = ActivityLog::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Get security events
            $securityEvents = SecurityEvent::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'role_display' => $user->getRoleDisplayName(),
                'status' => $user->is_active ? 'active' : 'inactive',
                'kyc_status' => $user->kyc_status,
                'kyc_status_color' => $user->getKycStatusColor(),
                'kyc_status_icon' => $user->getKycStatusIcon(),
                'last_login' => $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never',
                'last_login_ip' => $user->last_login_ip,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
                'profile_completion' => $user->getProfileCompletionPercentage(),
                'permissions' => $user->getRolePermissions(),
                'recent_activity' => $recentActivity,
                'security_events' => $securityEvents,
            ];

            return response()->json([
                'success' => true,
                'data' => $userData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new user
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'nullable|string|max:20|unique:users,phone',
                'password' => ['required', 'confirmed', Password::defaults()],
                'role' => 'required|in:production,inventory,telesales,DA,accountant,CFO,CEO,superadmin',
                'is_active' => 'boolean',
                'kyc_status' => 'in:pending,approved,rejected',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'is_active' => $request->get('is_active', true),
                'kyc_status' => $request->get('kyc_status', 'pending'),
                'email_verified_at' => now(),
            ]);

            // Log activity
            ActivityLog::logActivity('user_created', auth()->id(), [
                'created_user_id' => $user->id,
                'created_user_email' => $user->email,
                'role' => $user->role,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->is_active ? 'active' : 'inactive',
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user information
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $id,
                'phone' => 'sometimes|nullable|string|max:20|unique:users,phone,' . $id,
                'role' => 'sometimes|required|in:production,inventory,telesales,DA,accountant,CFO,CEO,superadmin',
                'is_active' => 'sometimes|boolean',
                'kyc_status' => 'sometimes|in:pending,approved,rejected',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $oldData = $user->toArray();
            $user->update($request->only(['name', 'email', 'phone', 'role', 'is_active', 'kyc_status']));

            // Log activity
            ActivityLog::logActivity('user_updated', auth()->id(), [
                'updated_user_id' => $user->id,
                'changes' => array_diff_assoc($user->toArray(), $oldData),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->is_active ? 'active' : 'inactive',
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user (soft delete)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            // Prevent deleting own account
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete your own account'
                ], 400);
            }

            DB::beginTransaction();

            // Log activity before deletion
            ActivityLog::logActivity('user_deleted', auth()->id(), [
                'deleted_user_id' => $user->id,
                'deleted_user_email' => $user->email,
                'deleted_user_role' => $user->role,
            ]);

            $user->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle user status (active/inactive)
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            // Prevent deactivating own account
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot deactivate your own account'
                ], 400);
            }

            DB::beginTransaction();

            $oldStatus = $user->is_active;
            $user->update(['is_active' => !$oldStatus]);

            // Log activity
            ActivityLog::logActivity('status_updated', auth()->id(), [
                'updated_user_id' => $user->id,
                'old_status' => $oldStatus ? 'active' : 'inactive',
                'new_status' => $user->is_active ? 'active' : 'inactive',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'data' => [
                    'id' => $user->id,
                    'status' => $user->is_active ? 'active' : 'inactive',
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user role
     */
    public function updateRole(Request $request, $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'role' => 'required|in:production,inventory,telesales,DA,accountant,CFO,CEO,superadmin',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $oldRole = $user->role;
            $user->update(['role' => $request->role]);

            // Log activity
            ActivityLog::logActivity('role_updated', auth()->id(), [
                'updated_user_id' => $user->id,
                'old_role' => $oldRole,
                'new_role' => $user->role,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User role updated successfully',
                'data' => [
                    'id' => $user->id,
                    'role' => $user->role,
                    'role_display' => $user->getRoleDisplayName(),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'inactive_users' => User::where('is_active', false)->count(),
                'pending_kyc' => User::where('kyc_status', 'pending')->count(),
                'approved_kyc' => User::where('kyc_status', 'approved')->count(),
                'rejected_kyc' => User::where('kyc_status', 'rejected')->count(),
                'users_by_role' => User::selectRaw('role, COUNT(*) as count')
                    ->groupBy('role')
                    ->pluck('count', 'role')
                    ->toArray(),
                'recent_registrations' => User::where('created_at', '>=', now()->subDays(7))->count(),
                'users_with_recent_activity' => ActivityLog::where('created_at', '>=', now()->subDays(7))
                    ->distinct('user_id')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load user statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 