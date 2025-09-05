<?php

namespace App\Http\Controllers\Api\KycPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /**
     * Admin login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'remember_me' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $credentials = $request->only('email', 'password');
            $remember = $request->boolean('remember_me', false);

            // Check if user exists and is an admin
            $user = \App\Models\User::where('email', $credentials['email'])
                ->where('role', 'admin')
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials or insufficient permissions'
                ], 401);
            }

            // Verify password
            if (!Hash::check($credentials['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check if account is active
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is deactivated. Please contact system administrator.'
                ], 403);
            }

            // Generate token
            $token = $user->createToken('kyc-admin-token', ['admin'])->plainTextToken;

            // Log login activity
            \App\Models\SystemActivity::create([
                'activity_type' => 'LOGIN',
                'description' => "Admin user {$user->name} logged in successfully",
                'status' => 'success',
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'login_method' => 'email',
                    'remember_me' => $remember
                ]
            ]);

            // Update last login
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'avatar' => $user->avatar,
                        'last_login_at' => $user->last_login_at,
                        'permissions' => $this->getUserPermissions($user)
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('sanctum.expiration') * 60, // Convert to seconds
                    'remember_me' => $remember
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
     * Admin logout
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if ($user) {
                // Log logout activity
                \App\Models\SystemActivity::create([
                    'activity_type' => 'LOGOUT',
                    'description' => "Admin user {$user->name} logged out",
                    'status' => 'success',
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);

                // Revoke current token
                $user->currentAccessToken()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
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
     * Get current admin user profile
     */
    public function profile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'avatar' => $user->avatar,
                        'phone' => $user->phone,
                        'is_active' => $user->is_active,
                        'email_verified_at' => $user->email_verified_at,
                        'last_login_at' => $user->last_login_at,
                        'last_login_ip' => $user->last_login_ip,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                        'permissions' => $this->getUserPermissions($user)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update admin profile
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $request->user()->id,
            'phone' => 'nullable|string|max:20',
            'current_password' => 'nullable|string|min:6',
            'new_password' => 'nullable|string|min:8|confirmed',
            'new_password_confirmation' => 'nullable|string|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Check current password if changing password
            if ($request->filled('new_password')) {
                if (!$request->filled('current_password')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Current password is required to change password',
                        'errors' => ['current_password' => ['Current password is required']]
                    ], 422);
                }

                if (!Hash::check($request->current_password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Current password is incorrect',
                        'errors' => ['current_password' => ['Current password is incorrect']]
                    ], 422);
                }
            }

            // Update user data
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone
            ];

            if ($request->filled('new_password')) {
                $updateData['password'] = Hash::make($request->new_password);
            }

            $user->update($updateData);

            // Log profile update
            \App\Models\SystemActivity::create([
                'activity_type' => 'PROFILE_UPDATED',
                'description' => "Admin user {$user->name} updated their profile",
                'status' => 'success',
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'updated_fields' => array_keys($updateData)
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'avatar' => $user->avatar,
                        'phone' => $user->phone,
                        'is_active' => $user->is_active,
                        'email_verified_at' => $user->email_verified_at,
                        'last_login_at' => $user->last_login_at,
                        'last_login_ip' => $user->last_login_ip,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                        'permissions' => $this->getUserPermissions($user)
                    ]
                ]
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
     * Refresh token
     */
    public function refreshToken(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Revoke current token
            $user->currentAccessToken()->delete();

            // Generate new token
            $token = $user->createToken('kyc-admin-token', ['admin'])->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('sanctum.expiration') * 60
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

    /**
     * Get user permissions
     */
    private function getUserPermissions($user)
    {
        // In a real application, this would come from a permissions table
        // For now, we'll return default admin permissions
        return [
            'dashboard' => ['view'],
            'applications' => ['view', 'approve', 'reject', 'edit'],
            'ai_insights' => ['view', 'export'],
            'system_logs' => ['view', 'export'],
            'users' => ['view', 'create', 'edit', 'delete'],
            'settings' => ['view', 'edit']
        ];
    }
}
