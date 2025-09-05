<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSession;
use App\Models\DeliveryAgent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Get user profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load(['deliveryAgent', 'employee']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'permissions' => $user->getRolePermissions(),
                'profile_completion' => $user->profile_completion
            ]
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), User::getValidationRules(true, $user->id));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user->update($request->only([
                'name', 'phone', 'date_of_birth', 'gender', 'address',
                'city', 'state', 'country', 'postal_code',
                'emergency_contact', 'emergency_phone', 'bio', 'preferences'
            ]));

            Log::info("Profile updated for user: {$user->email}");

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => $user->fresh()->load(['deliveryAgent', 'employee']),
                    'profile_completion' => $user->profile_completion
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Profile update failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed'
            ], 500);
        }
    }

    /**
     * Get user permissions.
     */
    public function getUserPermissions(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'permissions' => $user->getRolePermissions(),
                'role' => $user->role,
                'role_display_name' => $user->getRoleDisplayName()
            ]
        ]);
    }

    /**
     * Switch delivery agent (for multi-agent users).
     */
    public function switchDeliveryAgent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'delivery_agent_id' => 'required|exists:delivery_agents,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $deliveryAgent = DeliveryAgent::findOrFail($request->delivery_agent_id);

        // Check if user has access to this delivery agent
        if (!$user->hasPermission('delivery.switch') && $user->delivery_agent_id !== $deliveryAgent->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to switch to this delivery agent'
            ], 403);
        }

        $user->update([
            'delivery_agent_id' => $deliveryAgent->id
        ]);

        Log::info("User {$user->email} switched to delivery agent: {$deliveryAgent->name}");

        return response()->json([
            'success' => true,
            'message' => 'Delivery agent switched successfully',
            'data' => [
                'delivery_agent' => $deliveryAgent,
                'user' => $user->fresh()->load(['deliveryAgent', 'employee'])
            ]
        ]);
    }

    /**
     * Get user sessions.
     */
    public function getUserSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $sessions = UserSession::where('user_id', $user->id)
            ->orderBy('last_activity', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sessions
        ]);
    }

    /**
     * Revoke user session.
     */
    public function revokeSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|exists:user_sessions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $session = UserSession::where('id', $request->session_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found'
            ], 404);
        }

        $session->deactivate();

        Log::info("Session revoked for user: {$user->email}");

        return response()->json([
            'success' => true,
            'message' => 'Session revoked successfully'
        ]);
    }

    /**
     * Update user avatar.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        try {
            if ($request->hasFile('avatar')) {
                $avatar = $request->file('avatar');
                $filename = time() . '_' . $user->id . '.' . $avatar->getClientOriginalExtension();
                $path = $avatar->storeAs('avatars', $filename, 'public');
                
                $user->update(['avatar' => $path]);
            }

            Log::info("Avatar updated for user: {$user->email}");

            return response()->json([
                'success' => true,
                'message' => 'Avatar updated successfully',
                'data' => [
                    'avatar' => $user->avatar
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Avatar update failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Avatar update failed'
            ], 500);
        }
    }

    /**
     * Get user activity.
     */
    public function getUserActivity(Request $request): JsonResponse
    {
        $user = $request->user();
        $hours = $request->get('hours', 24);

        $activity = $user->getRecentActivity($hours);

        return response()->json([
            'success' => true,
            'data' => $activity
        ]);
    }

    /**
     * Get user security events.
     */
    public function getSecurityEvents(Request $request): JsonResponse
    {
        $user = $request->user();
        $hours = $request->get('hours', 24);

        $events = $user->getSecurityEvents($hours);

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    /**
     * Deactivate user account.
     */
    public function deactivateAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password is incorrect'
            ], 400);
        }

        $user->update(['is_active' => false]);

        // Revoke all tokens
        $user->tokens()->delete();

        Log::info("Account deactivated for user: {$user->email}");

        return response()->json([
            'success' => true,
            'message' => 'Account deactivated successfully'
        ]);
    }
} 