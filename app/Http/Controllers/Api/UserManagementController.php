<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPortalAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        try {
            $query = User::query();
            
            // Apply filters
            if ($request->has('role') && $request->role !== '') {
                $query->where('role', $request->role);
            }
            
            if ($request->has('status') && $request->status !== '') {
                $isActive = $request->status === 'active';
                $query->where('is_active', $isActive);
            }
            
            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $users = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $users->items(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'nullable|string|unique:users,phone',
                'password' => 'required|string|min:8',
                // Legacy single role field (kept for backward compatibility)
                'role' => 'nullable|in:production,inventory,telesales,DA,accountant,CFO,CEO,superadmin,admin,gm,hr,investor,marketing,books,crm,analytics,auditor,financial_controller,manufacturing,logistics,kyc',
                // New Spatie-driven payload
                'roles' => 'sometimes|array',
                'roles.*' => 'string', // Spatie will validate existence if you prefer Rule::exists
                'permissions' => 'sometimes|array',
                'permissions.*' => 'string',
                'is_active' => 'boolean',
                // assigned_portals: array of objects [{ portal, can_view, can_manage }]
                'assigned_portals' => 'array',
                'assigned_portals.*.portal' => 'required|string',
                'assigned_portals.*.can_view' => 'sometimes|boolean',
                'assigned_portals.*.can_manage' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userData = $validator->validated();
            $userData['password'] = Hash::make($userData['password']);
            $userData['email_verified_at'] = now();
            $userData['kyc_status'] = 'approved'; // Auto-approve admin created users
            
            // Remove assigned_portals from user data (handle separately if needed)
            $assignedPortals = $userData['assigned_portals'] ?? [];
            unset($userData['assigned_portals']);

            DB::beginTransaction();

            $user = User::create($userData);

            // Sync Spatie roles/permissions if provided
            if ($request->filled('roles')) {
                $user->syncRoles($request->input('roles', []));
            } elseif (!empty($userData['role'])) {
                // Optionally map legacy single role into Spatie role
                $user->syncRoles([$userData['role']]);
            }
            if ($request->filled('permissions')) {
                $user->syncPermissions($request->input('permissions', []));
            }

            // Sync portal assignments if provided
            if (!empty($assignedPortals)) {
                $rows = [];
                foreach ($assignedPortals as $ap) {
                    if (!isset($ap['portal'])) { continue; }
                    $rows[] = [
                        'user_id' => $user->id,
                        'portal' => $ap['portal'],
                        'can_view' => (bool)($ap['can_view'] ?? false),
                        'can_manage' => (bool)($ap['can_manage'] ?? false),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($rows)) {
                    UserPortalAssignment::insert($rows);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $user->makeHidden(['password'])
            ], 201);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) { DB::rollBack(); }
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user
     */
    public function show($id)
    {
        try {
            $user = User::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $user->makeHidden(['password'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
                'phone' => ['sometimes', 'nullable', 'string', Rule::unique('users')->ignore($user->id)],
                'password' => 'sometimes|required|string|min:8',
                'role' => 'sometimes|nullable|in:production,inventory,telesales,DA,accountant,CFO,CEO,superadmin,admin,gm,hr,investor,marketing,books,crm,analytics,auditor,financial_controller,manufacturing,logistics,kyc',
                'roles' => 'sometimes|array',
                'roles.*' => 'string',
                'permissions' => 'sometimes|array',
                'permissions.*' => 'string',
                'is_active' => 'sometimes|boolean',
                'assigned_portals' => 'sometimes|array',
                'assigned_portals.*.portal' => 'required_with:assigned_portals|string',
                'assigned_portals.*.can_view' => 'sometimes|boolean',
                'assigned_portals.*.can_manage' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userData = $validator->validated();
            
            // Hash password if provided
            if (isset($userData['password'])) {
                $userData['password'] = Hash::make($userData['password']);
            }
            
            // Remove assigned_portals from user data (handle separately if needed)
            unset($userData['assigned_portals']);

            DB::beginTransaction();

            $user->update($userData);

            // Sync Spatie roles/permissions when provided
            if ($request->has('roles')) {
                $user->syncRoles($request->input('roles', []));
            } elseif (array_key_exists('role', $userData) && $userData['role'] !== null) {
                $user->syncRoles([$userData['role']]);
            }
            if ($request->has('permissions')) {
                $user->syncPermissions($request->input('permissions', []));
            }

            // Sync portal assignments if provided
            if ($request->has('assigned_portals')) {
                $assignedPortals = $request->get('assigned_portals', []);
                // Delete existing and recreate for simplicity
                UserPortalAssignment::where('user_id', $user->id)->delete();
                $rows = [];
                foreach ($assignedPortals as $ap) {
                    if (!isset($ap['portal'])) { continue; }
                    $rows[] = [
                        'user_id' => $user->id,
                        'portal' => $ap['portal'],
                        'can_view' => (bool)($ap['can_view'] ?? false),
                        'can_manage' => (bool)($ap['can_manage'] ?? false),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($rows)) {
                    UserPortalAssignment::insert($rows);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user->fresh()->makeHidden(['password'])
            ]);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) { DB::rollBack(); }
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified user
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Prevent deletion of superadmin users
            if ($user->role === 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete superadmin users'
                ], 403);
            }
            
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics
     */
    public function stats()
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'inactive_users' => User::where('is_active', false)->count(),
                'by_role' => User::selectRaw('role, count(*) as count')
                    ->groupBy('role')
                    ->pluck('count', 'role')
                    ->toArray(),
                'recent_users' => User::where('created_at', '>=', now()->subDays(7))->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle user active status
     */
    public function toggleStatus($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Prevent deactivating superadmin users
            if ($user->role === 'superadmin' && $user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot deactivate superadmin users'
                ], 403);
            }
            
            $user->update(['is_active' => !$user->is_active]);

            return response()->json([
                'success' => true,
                'message' => $user->is_active ? 'User activated successfully' : 'User deactivated successfully',
                'data' => $user->makeHidden(['password'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle user status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
