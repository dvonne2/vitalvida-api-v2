<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BasicOTPService
{
    const OTP_LENGTH = 6;
    const OTP_EXPIRY_MINUTES = 5;
    const MAX_VERIFICATION_ATTEMPTS = 3;
    const MAX_RESEND_ATTEMPTS = 3;
    const RESEND_COOLDOWN_MINUTES = 1;
    const LOCKOUT_DURATION_MINUTES = 30;
    
    public function generateOTP(string $identifier): array
    {
        if ($this->isLockedOut($identifier)) {
            $lockoutExpiry = $this->getLockoutExpiry($identifier);
            return [
                'success' => false,
                'error' => 'Account temporarily locked due to too many attempts',
                'lockout_until' => $lockoutExpiry,
                'wait_minutes' => $lockoutExpiry->diffInMinutes(now())
            ];
        }

        $resendCheck = $this->checkResendLimits($identifier);
        if (!$resendCheck['allowed']) {
            return [
                'success' => false,
                'error' => $resendCheck['error'],
                'next_resend_at' => $resendCheck['next_resend_at'] ?? null
            ];
        }

        $otp = $this->generateSecureOTP();
        $expiryTime = now()->addMinutes(self::OTP_EXPIRY_MINUTES);
        
        $otpData = [
            'code' => $otp,
            'expires_at' => $expiryTime->toISOString(),
            'created_at' => now()->toISOString(),
            'attempts' => 0,
            'identifier' => $identifier,
            'session_id' => bin2hex(random_bytes(16))
        ];

        $otpKey = "otp:{$identifier}";
        Cache::put($otpKey, $otpData, $expiryTime);

        $this->updateResendTracking($identifier);

        Log::info('OTP generated', [
            'identifier' => $this->maskIdentifier($identifier),
            'expires_at' => $expiryTime,
            'session_id' => $otpData['session_id']
        ]);

        return [
            'success' => true,
            'message' => 'OTP generated successfully',
            'expires_at' => $expiryTime,
            'expires_in_minutes' => self::OTP_EXPIRY_MINUTES,
            'session_id' => $otpData['session_id'],
            'resend_count' => $this->getResendCount($identifier),
            'remaining_resends' => self::MAX_RESEND_ATTEMPTS - $this->getResendCount($identifier),
            'otp_code' => config('app.debug') ? $otp : null
        ];
    }

    public function verifyOTP(string $identifier, string $code, ?string $sessionId = null): array
    {
        if ($this->isLockedOut($identifier)) {
            return [
                'success' => false,
                'error' => 'Account temporarily locked due to too many attempts'
            ];
        }

        $otpKey = "otp:{$identifier}";
        $otpData = Cache::get($otpKey);

        if (!$otpData) {
            $this->incrementVerificationAttempts($identifier);
            return [
                'success' => false,
                'error' => 'Invalid or expired OTP'
            ];
        }

        $expiryTime = Carbon::parse($otpData['expires_at']);
        if (now()->isAfter($expiryTime)) {
            Cache::forget($otpKey);
            return [
                'success' => false,
                'error' => 'OTP has expired',
                'expired_at' => $expiryTime
            ];
        }

        if ($sessionId && $otpData['session_id'] !== $sessionId) {
            $this->incrementVerificationAttempts($identifier);
            return [
                'success' => false,
                'error' => 'Invalid session'
            ];
        }

        if ($otpData['attempts'] >= self::MAX_VERIFICATION_ATTEMPTS) {
            Cache::forget($otpKey);
            $this->lockoutUser($identifier);
            return [
                'success' => false,
                'error' => 'Too many verification attempts. OTP invalidated.'
            ];
        }

        if (!hash_equals($otpData['code'], $code)) {
            $otpData['attempts']++;
            Cache::put($otpKey, $otpData, $expiryTime);
            
            $this->incrementVerificationAttempts($identifier);
            
            $remainingAttempts = self::MAX_VERIFICATION_ATTEMPTS - $otpData['attempts'];
            
            return [
                'success' => false,
                'error' => 'Invalid OTP code',
                'remaining_attempts' => $remainingAttempts
            ];
        }

        $this->cleanupOTPData($identifier);
        
        return [
            'success' => true,
            'message' => 'OTP verified successfully',
            'session_id' => $otpData['session_id']
        ];
    }

    public function getOTPStatus(string $identifier): array
    {
        $otpKey = "otp:{$identifier}";
        $otpData = Cache::get($otpKey);
        
        $status = [
            'has_active_otp' => false,
            'is_locked_out' => $this->isLockedOut($identifier),
            'resend_count' => $this->getResendCount($identifier),
            'remaining_resends' => self::MAX_RESEND_ATTEMPTS - $this->getResendCount($identifier)
        ];
        
        if ($otpData) {
            $expiryTime = Carbon::parse($otpData['expires_at']);
            $status['has_active_otp'] = now()->isBefore($expiryTime);
            $status['expires_at'] = $expiryTime;
            $status['expires_in_seconds'] = $expiryTime->diffInSeconds(now());
            $status['attempts_used'] = $otpData['attempts'];
            $status['remaining_attempts'] = self::MAX_VERIFICATION_ATTEMPTS - $otpData['attempts'];
        }
        
        return $status;
    }

    private function checkResendLimits(string $identifier): array
    {
        $resendCount = $this->getResendCount($identifier);
        
        if ($resendCount >= self::MAX_RESEND_ATTEMPTS) {
            return [
                'allowed' => false,
                'error' => 'Daily resend limit reached. Please try again tomorrow.'
            ];
        }

        $lastResendKey = "otp_resend:{$identifier}";
        $lastResendTime = Cache::get($lastResendKey);
        
        if ($lastResendTime) {
            $nextAllowedTime = Carbon::parse($lastResendTime)->addMinutes(self::RESEND_COOLDOWN_MINUTES);
            if (now()->isBefore($nextAllowedTime)) {
                return [
                    'allowed' => false,
                    'error' => 'Please wait before requesting another OTP',
                    'next_resend_at' => $nextAllowedTime,
                    'wait_seconds' => $nextAllowedTime->diffInSeconds(now())
                ];
            }
        }

        return ['allowed' => true];
    }

    private function updateResendTracking(string $identifier): void
    {
        $resendCountKey = "otp_resend_count:{$identifier}";
        $resendKey = "otp_resend:{$identifier}";
        
        $currentCount = Cache::get($resendCountKey, 0);
        Cache::put($resendCountKey, $currentCount + 1, now()->endOfDay());
        
        Cache::put($resendKey, now()->toISOString(), now()->addMinutes(self::RESEND_COOLDOWN_MINUTES));
    }

    private function getResendCount(string $identifier): int
    {
        $resendCountKey = "otp_resend_count:{$identifier}";
        return Cache::get($resendCountKey, 0);
    }

    private function incrementVerificationAttempts(string $identifier): void
    {
        $attemptsKey = "otp_attempts:{$identifier}";
        $attempts = Cache::get($attemptsKey, 0) + 1;
        
        Cache::put($attemptsKey, $attempts, now()->addMinutes(self::LOCKOUT_DURATION_MINUTES));
        
        if ($attempts >= self::MAX_VERIFICATION_ATTEMPTS) {
            $this->lockoutUser($identifier);
        }
    }

    private function lockoutUser(string $identifier): void
    {
        $lockoutKey = "otp_lockout:{$identifier}";
        $lockoutUntil = now()->addMinutes(self::LOCKOUT_DURATION_MINUTES);
        
        Cache::put($lockoutKey, $lockoutUntil->toISOString(), $lockoutUntil);
        
        Log::warning('User locked out due to too many OTP attempts', [
            'identifier' => $this->maskIdentifier($identifier),
            'lockout_until' => $lockoutUntil
        ]);
    }

    private function isLockedOut(string $identifier): bool
    {
        $lockoutKey = "otp_lockout:{$identifier}";
        $lockoutTime = Cache::get($lockoutKey);
        
        if (!$lockoutTime) {
            return false;
        }
        
        return now()->isBefore(Carbon::parse($lockoutTime));
    }

    private function getLockoutExpiry(string $identifier): ?Carbon
    {
        $lockoutKey = "otp_lockout:{$identifier}";
        $lockoutTime = Cache::get($lockoutKey);
        
        return $lockoutTime ? Carbon::parse($lockoutTime) : null;
    }

    private function generateSecureOTP(): string
    {
        $otp = '';
        for ($i = 0; $i < self::OTP_LENGTH; $i++) {
            $otp .= random_int(0, 9);
        }
        return $otp;
    }

    private function cleanupOTPData(string $identifier): void
    {
        $keys = [
            "otp:{$identifier}",
            "otp_attempts:{$identifier}",
            "otp_resend:{$identifier}"
        ];
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    private function maskIdentifier(string $identifier): string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $identifier);
            return substr($parts[0], 0, 2) . '***@' . $parts[1];
        }
        
        return substr($identifier, 0, 3) . '***' . substr($identifier, -2);
    }

    public function resetUserOTPLimits(string $identifier): void
    {
        $keys = [
            "otp:{$identifier}",
            "otp_attempts:{$identifier}",
            "otp_resend:{$identifier}",
            "otp_lockout:{$identifier}",
            "otp_resend_count:{$identifier}"
        ];
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        
        Log::info('OTP limits reset for user', [
            'identifier' => $this->maskIdentifier($identifier)
        ]);
    }
}
