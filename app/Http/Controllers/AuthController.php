<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected $maxAttempts = 5;
    protected $lockoutMinutes = 15;

    public function register(RegisterRequest $request)
    {
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => $request->role ?? 'user',
                'email_verified_at' => now(), // Auto-verify for testing
            ]);

            $token = $user->createToken('auth_token', ['*'], now()->addHours(24))->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 24 * 60 * 60,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(LoginRequest $request)
    {
        $key = $this->throttleKey($request);
        
        // Check if user is locked out
        if ($this->hasTooManyLoginAttempts($request)) {
            $seconds = $this->availableIn($key);
            
            return response()->json([
                'success' => false,
                'message' => "Too many login attempts. Account locked for {$seconds} seconds.",
                'lockout_ends_at' => now()->addSeconds($seconds)->toISOString()
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->incrementLoginAttempts($request);
            
            $attempts = $this->attempts($key);
            $remaining = $this->maxAttempts - $attempts;
            
            throw ValidationException::withMessages([
                'email' => [
                    'The provided credentials are incorrect.',
                    $remaining > 0 ? "You have {$remaining} attempts remaining." : ''
                ],
            ]);
        }

        // Clear attempts on successful login
        $this->clearLoginAttempts($request);

        $token = $user->createToken('auth_token', ['*'], now()->addHours(24))->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 24 * 60 * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]);
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return response()->json(['success' => true, 'message' => 'Logged out successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function user(Request $request)
    {
        try {
            return response()->json(['success' => true, 'user' => $request->user()]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function refresh(Request $request)
    {
        try {
            $user = $request->user();
            $request->user()->currentAccessToken()->delete();
            
            $newToken = $user->createToken('auth_token', ['*'], now()->addHours(24))->plainTextToken;
            
            return response()->json([
                'success' => true,
                'access_token' => $newToken,
                'token_type' => 'Bearer',
                'expires_in' => 24 * 60 * 60
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
                ],
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
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password change failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255|regex:/^[a-zA-Z\s]+$/',
                'phone' => 'sometimes|string|regex:/^[0-9]{10,15}$/',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update($request->only(['name', 'phone', 'email']));

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        // TODO: Implement password reset logic
        return response()->json([
            'success' => false,
            'message' => 'Password reset functionality not yet implemented'
        ], 501);
    }

    public function resetPassword(Request $request)
    {
        // TODO: Implement password reset logic
        return response()->json([
            'success' => false,
            'message' => 'Password reset functionality not yet implemented'
        ], 501);
    }

    public function verifyEmail(Request $request)
    {
        // TODO: Implement email verification logic
        return response()->json([
            'success' => false,
            'message' => 'Email verification functionality not yet implemented'
        ], 501);
    }

    public function resendVerification(Request $request)
    {
        // TODO: Implement resend verification logic
        return response()->json([
            'success' => false,
            'message' => 'Resend verification functionality not yet implemented'
        ], 501);
    }

    // Rate limiting helper methods
    protected function throttleKey(Request $request)
    {
        return strtolower($request->input('email')) . '|' . $request->ip();
    }

    protected function hasTooManyLoginAttempts(Request $request)
    {
        return Cache::has($this->throttleKey($request) . ':lockout');
    }

    protected function incrementLoginAttempts(Request $request)
    {
        $key = $this->throttleKey($request);
        $attempts = Cache::get($key, 0) + 1;
        
        Cache::put($key, $attempts, now()->addMinutes($this->lockoutMinutes));
        
        if ($attempts >= $this->maxAttempts) {
            Cache::put($key . ':lockout', true, now()->addMinutes($this->lockoutMinutes));
        }
    }

    protected function clearLoginAttempts(Request $request)
    {
        $key = $this->throttleKey($request);
        Cache::forget($key);
        Cache::forget($key . ':lockout');
    }

    protected function attempts($key)
    {
        return Cache::get($key, 0);
    }

    protected function availableIn($key)
    {
        return Cache::store()->getRedis()->ttl($key . ':lockout');
    }
}
