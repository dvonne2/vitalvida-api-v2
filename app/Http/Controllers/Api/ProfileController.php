<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Get user profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'profile' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                    'age' => $user->age,
                    'gender' => $user->gender,
                    'address' => $user->address,
                    'city' => $user->city,
                    'state' => $user->state,
                    'country' => $user->country,
                    'postal_code' => $user->postal_code,
                    'emergency_contact' => $user->emergency_contact,
                    'emergency_phone' => $user->emergency_phone,
                    'bio' => $user->bio,
                    'avatar' => $user->avatar,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'kyc_status' => $user->kyc_status,
                    'preferences' => $user->preferences,
                    'profile_completion' => $user->profile_completion,
                    'full_address' => $user->full_address,
                    'last_login_at' => $user->last_login_at,
                    'is_delivery_agent' => $user->isDeliveryAgent(),
                ],
                'account_stats' => [
                    'active_sessions' => $user->tokens()->count(),
                    'member_since' => $user->created_at->format('M Y'),
                    'email_verified' => !is_null($user->email_verified_at),
                ]
            ]
        ]);
    }

    /**
     * Update user profile
     */
    public function update(Request $request): JsonResponse
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
            $user->update($validator->validated());
            
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'profile_completion' => $user->fresh()->profile_completion,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update user preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'preferences' => ['required', 'array'],
            'preferences.notifications' => ['boolean'],
            'preferences.email_updates' => ['boolean'],
            'preferences.sms_alerts' => ['boolean'],
            'preferences.language' => ['string', 'in:en,fr,es,de'],
            'preferences.timezone' => ['string', 'max:50'],
            'preferences.theme' => ['string', 'in:light,dark,auto'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $user->update(['preferences' => $validator->validated()['preferences']]);

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'data' => [
                'preferences' => $user->preferences
            ]
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => User::getPasswordRules(true),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Revoke other sessions for security
        $currentToken = $request->user()->currentAccessToken();
        $user->tokens()->where('id', '!=', $currentToken->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully. Other sessions have been logged out.'
        ]);
    }

    /**
     * Delete account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'string', 'in:DELETE_MY_ACCOUNT'],
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

        // Revoke all tokens
        $user->tokens()->delete();
        
        // Soft delete or deactivate user
        $user->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Account deactivated successfully'
        ]);
    }
}
