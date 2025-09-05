<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\HasApiTokens;

class AuthController extends Controller
{
    /**
     * User login
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'device_name' => 'nullable|string'
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check if user has inventory portal access
            if (!$this->hasInventoryAccess($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Insufficient permissions.'
                ], 403);
            }

            // Create token
            $token = $user->createToken($request->device_name ?? 'inventory-portal')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user->only(['id', 'name', 'email', 'role', 'is_active']),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * User registration (admin only)
     */
    public function register(Request $request)
    {
        try {
            // Check if current user has permission to create users
            if (!auth()->user()->can('create_users')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to create users'
                ], 403);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => ['required', 'confirmed', Password::defaults()],
                'role' => 'required|in:superadmin,inventory,DA,production,accountant,CFO,CEO',
                'phone' => 'nullable|string|max:20',
                'department' => 'nullable|string|max:100',
                'permissions' => 'nullable|array',
                'permissions.*' => 'exists:permissions,id'
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'phone' => $request->phone,
                'department' => $request->department,
                'status' => 'active'
            ]);

            // Assign permissions if provided
            if ($request->permissions) {
                $user->permissions()->attach($request->permissions);
            }

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $user->load(['role', 'permissions'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * User logout
     */
    public function logout(Request $request)
    {
        try {
            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current user profile
     */
    public function getProfile(Request $request)
    {
        try {
            $user = $request->user()->load(['role', 'permissions']);

            return response()->json([
                'success' => true,
                'data' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'department' => 'nullable|string|max:100',
                'current_password' => 'required_with:new_password',
                'new_password' => 'nullable|confirmed|' . Password::defaults(),
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            // Verify current password if changing password
            if ($request->new_password) {
                if (!Hash::check($request->current_password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Current password is incorrect'
                    ], 400);
                }
            }

            $updateData = $request->only(['name', 'phone', 'department']);

            if ($request->new_password) {
                $updateData['password'] = Hash::make($request->new_password);
            }

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $updateData['avatar'] = $avatarPath;
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user->fresh()->load(['role', 'permissions'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all users (admin only)
     */
    public function getAllUsers(Request $request)
    {
        try {
            if (!auth()->user()->can('view_users')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions'
                ], 403);
            }

            $query = User::with(['role', 'permissions']);

            // Apply filters
            if ($request->role) {
                $query->where('role', $request->role);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('email', 'like', '%' . $request->search . '%');
                });
            }

            $users = $query->orderBy('name')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $users
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
     * Get user details (admin only)
     */
    public function getUserDetails(Request $request, $userId)
    {
        try {
            if (!auth()->user()->can('view_users')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions'
                ], 403);
            }

            $user = User::with(['role', 'permissions'])->findOrFail($userId);

            return response()->json([
                'success' => true,
                'data' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user (admin only)
     */
    public function updateUser(Request $request, $userId)
    {
        try {
            if (!auth()->user()->can('edit_users')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions'
                ], 403);
            }

            $user = User::findOrFail($userId);

            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $userId,
                'role' => 'sometimes|required|in:superadmin,inventory,DA,production,accountant,CFO,CEO',
                'phone' => 'nullable|string|max:20',
                'department' => 'nullable|string|max:100',
                'status' => 'sometimes|required|in:active,inactive,suspended',
                'permissions' => 'nullable|array',
                'permissions.*' => 'exists:permissions,id'
            ]);

            $user->update($request->except(['permissions']));

            // Update permissions if provided
            if ($request->has('permissions')) {
                $user->permissions()->sync($request->permissions);
            }

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user->fresh()->load(['role', 'permissions'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user (admin only)
     */
    public function deleteUser(Request $request, $userId)
    {
        try {
            if (!auth()->user()->can('delete_users')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions'
                ], 403);
            }

            $user = User::findOrFail($userId);

            // Prevent self-deletion
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete your own account'
                ], 400);
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
     * Get available roles
     */
    public function getRoles(Request $request)
    {
        try {
            $roles = [
                'superadmin' => 'Super Administrator',
                'inventory' => 'Inventory Manager',
                'DA' => 'Delivery Agent',
                'production' => 'Production Manager',
                'accountant' => 'Accountant',
                'CFO' => 'Chief Financial Officer',
                'CEO' => 'Chief Executive Officer'
            ];

            return response()->json([
                'success' => true,
                'data' => $roles
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available permissions
     */
    public function getPermissions(Request $request)
    {
        try {
            $permissions = Permission::orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => $permissions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change user password (admin only)
     */
    public function changeUserPassword(Request $request, $userId)
    {
        try {
            if (!auth()->user()->can('edit_users')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions'
                ], 403);
            }

            $request->validate([
                'new_password' => ['required', 'confirmed', Password::defaults()]
            ]);

            $user = User::findOrFail($userId);
            $user->update(['password' => Hash::make($request->new_password)]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user activity log
     */
    public function getUserActivity(Request $request, $userId = null)
    {
        try {
            $targetUserId = $userId ?? auth()->id();

            // Check permissions
            if ($userId && !auth()->user()->can('view_users')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions'
                ], 403);
            }

            // This would typically query an activity log table
            // For now, return empty array as placeholder
            $activities = [];

            return response()->json([
                'success' => true,
                'data' => $activities
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh token
     */
    public function refreshToken(Request $request)
    {
        try {
            $user = $request->user();
            
            // Revoke current token
            $request->user()->currentAccessToken()->delete();
            
            // Create new token
            $token = $user->createToken('inventory-portal-refresh')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper methods

    private function hasInventoryAccess($user)
    {
        $allowedRoles = ['superadmin', 'inventory', 'DA', 'production', 'accountant', 'CFO', 'CEO'];
        return in_array($user->role, $allowedRoles) && $user->is_active === true;
    }
} 