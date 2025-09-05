<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BasicOTPService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;

class BasicOTPController extends Controller
{
    private BasicOTPService $otpService;

    public function __construct(BasicOTPService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string|max:255',
            'type' => 'sometimes|string|in:email,sms,phone',
            'purpose' => 'sometimes|string|in:registration,login,password_reset,phone_verification,email_verification'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $identifier = $request->input('identifier');
        $type = $request->input('type', 'email');
        $purpose = $request->input('purpose', 'verification');

        if ($type === 'email' && !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid email address'
            ], 422);
        }

        if (in_array($type, ['phone', 'sms']) && !preg_match('/^\+?[1-9]\d{1,14}$/', $identifier)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid phone number format'
            ], 422);
        }

        $rateLimitKey = 'otp_generate:' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'error' => 'Too many requests. Please try again later.',
                'retry_after_seconds' => $seconds
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 300);

        $result = $this->otpService->generateOTP($identifier);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        $response = [
            'success' => true,
            'message' => 'OTP generated successfully',
            'data' => [
                'expires_at' => $result['expires_at'],
                'expires_in_minutes' => $result['expires_in_minutes'],
                'session_id' => $result['session_id'],
                'resend_count' => $result['resend_count'],
                'remaining_resends' => $result['remaining_resends'],
                'type' => $type,
                'purpose' => $purpose
            ]
        ];

        if (config('app.debug') && $result['otp_code']) {
            $response['data']['otp_code'] = $result['otp_code'];
            $response['data']['note'] = 'OTP code included for testing only';
        }

        return response()->json($response, 200);
    }

    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string|max:255',
            'code' => 'required|string|size:6|regex:/^[0-9]{6}$/',
            'session_id' => 'sometimes|string|size:32'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $identifier = $request->input('identifier');
        $code = $request->input('code');
        $sessionId = $request->input('session_id');

        $rateLimitKey = 'otp_verify:' . $request->ip() . ':' . md5($identifier);
        if (RateLimiter::tooManyAttempts($rateLimitKey, 15)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'error' => 'Too many verification attempts. Please try again later.',
                'retry_after_seconds' => $seconds
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 300);

        $result = $this->otpService->verifyOTP($identifier, $code, $sessionId);

        if ($result['success']) {
            RateLimiter::clear($rateLimitKey);
            
            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => [
                    'session_id' => $result['session_id'],
                    'verified_at' => now()->toISOString()
                ]
            ], 200);
        }

        return response()->json($result, 400);
    }

    public function resend(Request $request): JsonResponse
    {
        return $this->generate($request);
    }

    public function status(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $identifier = $request->input('identifier');
        $status = $this->otpService->getOTPStatus($identifier);

        return response()->json([
            'success' => true,
            'data' => $status
        ], 200);
    }

    public function resetLimits(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $identifier = $request->input('identifier');
        $this->otpService->resetUserOTPLimits($identifier);

        return response()->json([
            'success' => true,
            'message' => 'OTP limits reset successfully'
        ], 200);
    }
}
