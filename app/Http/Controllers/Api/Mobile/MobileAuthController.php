<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\BiometricAuth;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MobileAuthController extends Controller
{
    /**
     * Mobile app login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_id' => 'required|string',
            'platform' => 'required|in:android,ios',
            'app_version' => 'nullable|string'
        ]);

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid credentials'
                ], 401);
            }

            // Generate API key for mobile app
            $apiKey = $this->generateApiKey($user, $request->device_id, $request->platform, $request->app_version);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'permissions' => $user->permissions ?? []
                    ],
                    'api_key' => $apiKey->key,
                    'expires_at' => $apiKey->expires_at->toISOString(),
                    'device_id' => $request->device_id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Login failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Setup biometric authentication
     */
    public function setupBiometric(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|string',
            'biometric_type' => 'required|in:fingerprint,face_id,voice',
            'public_key' => 'required|string'
        ]);

        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required'
                ], 401);
            }

            // Check if biometric auth already exists for this device
            $existingAuth = BiometricAuth::where('user_id', $user->id)
                ->where('device_id', $request->device_id)
                ->first();

            if ($existingAuth) {
                // Update existing biometric auth
                $existingAuth->update([
                    'biometric_type' => $request->biometric_type,
                    'public_key' => $request->public_key,
                    'is_active' => true,
                    'last_used_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'action' => 'updated',
                        'biometric_auth_id' => $existingAuth->id
                    ]
                ]);
            }

            // Create new biometric auth
            $biometricAuth = BiometricAuth::create([
                'user_id' => $user->id,
                'device_id' => $request->device_id,
                'biometric_type' => $request->biometric_type,
                'public_key' => $request->public_key,
                'is_active' => true,
                'registered_at' => now(),
                'last_used_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'action' => 'created',
                    'biometric_auth_id' => $biometricAuth->id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Biometric setup failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Biometric authentication
     */
    public function biometricAuth(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|string',
            'biometric_type' => 'required|in:fingerprint,face_id,voice',
            'signature' => 'required|string'
        ]);

        try {
            $biometricAuth = BiometricAuth::where('device_id', $request->device_id)
                ->where('biometric_type', $request->biometric_type)
                ->where('is_active', true)
                ->with('user')
                ->first();

            if (!$biometricAuth) {
                return response()->json([
                    'success' => false,
                    'error' => 'Biometric authentication not set up for this device'
                ], 401);
            }

            // Verify biometric signature (simplified - in real implementation, use proper crypto)
            if (!$this->verifyBiometricSignature($biometricAuth->public_key, $request->signature)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Biometric verification failed'
                ], 401);
            }

            // Update last used timestamp
            $biometricAuth->update(['last_used_at' => now()]);

            // Generate new API key
            $apiKey = $this->generateApiKey(
                $biometricAuth->user,
                $request->device_id,
                'mobile',
                '1.0'
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $biometricAuth->user->id,
                        'name' => $biometricAuth->user->name,
                        'email' => $biometricAuth->user->email,
                        'role' => $biometricAuth->user->role,
                        'permissions' => $biometricAuth->user->permissions ?? []
                    ],
                    'api_key' => $apiKey->key,
                    'expires_at' => $apiKey->expires_at->toISOString(),
                    'device_id' => $request->device_id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Biometric authentication failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $deviceId = $request->header('X-Device-ID');

            if ($user && $deviceId) {
                // Deactivate API keys for this device
                ApiKey::where('user_id', $user->id)
                    ->where('device_id', $deviceId)
                    ->update(['is_active' => false]);

                // Deactivate biometric auth for this device
                BiometricAuth::where('user_id', $user->id)
                    ->where('device_id', $deviceId)
                    ->update(['is_active' => false]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Logged out successfully'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Logout failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh API key
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $deviceId = $request->header('X-Device-ID');

            if (!$user || !$deviceId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required'
                ], 401);
            }

            // Generate new API key
            $apiKey = $this->generateApiKey($user, $deviceId, 'mobile', '1.0');

            return response()->json([
                'success' => true,
                'data' => [
                    'api_key' => $apiKey->key,
                    'expires_at' => $apiKey->expires_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Token refresh failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user profile
     */
    public function profile(): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'permissions' => $user->permissions ?? [],
                        'created_at' => $user->created_at->toISOString(),
                        'updated_at' => $user->updated_at->toISOString()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Profile retrieval failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate API key for mobile app
     */
    private function generateApiKey(User $user, string $deviceId, string $platform, string $appVersion): ApiKey
    {
        // Deactivate existing API keys for this device
        ApiKey::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->update(['is_active' => false]);

        // Create new API key
        return ApiKey::create([
            'user_id' => $user->id,
            'key' => 'vk_' . Str::random(48),
            'name' => "Mobile App - {$platform}",
            'client_type' => 'mobile',
            'platform' => $platform,
            'device_id' => $deviceId,
            'app_version' => $appVersion,
            'permissions' => $user->permissions ?? [],
            'is_active' => true,
            'expires_at' => now()->addDays(30), // 30 days expiry
            'last_used_at' => now()
        ]);
    }

    /**
     * Verify biometric signature (placeholder implementation)
     */
    private function verifyBiometricSignature(string $publicKey, string $signature): bool
    {
        // In a real implementation, this would verify the signature using the public key
        // For now, we'll just check if the signature is not empty
        return !empty($signature);
    }
} 