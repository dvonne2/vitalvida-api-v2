<?php

namespace App\Services;

use App\Models\OrderOtp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OtpService
{
    const MAX_ATTEMPTS = 3;
    const MAX_RESENDS = 2;
    const EXPIRY_MINUTES = 10;

    public function generateInitialOtp(string $orderNumber): array
    {
        try {
            $existingOtp = OrderOtp::where('order_number', $orderNumber)->first();
            
            if ($existingOtp) {
                return [
                    'success' => false,
                    'message' => 'OTP already exists for this order'
                ];
            }

            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            $orderOtp = OrderOtp::create([
                'order_number' => $orderNumber,
                'otp_code' => $otp,
                'expires_at' => Carbon::now()->addMinutes(self::EXPIRY_MINUTES),
                'attempt_count' => 0,
                'resend_count' => 0
            ]);

            return [
                'success' => true,
                'message' => 'OTP generated successfully',
                'data' => [
                    'otp_code' => $otp,
                    'expires_at' => $orderOtp->expires_at,
                    'expires_in_minutes' => self::EXPIRY_MINUTES
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate OTP',
                'error' => $e->getMessage()
            ];
        }
    }

    public function verifyOtp(string $orderNumber, string $otpCode): array
    {
        $orderOtp = OrderOtp::where('order_number', $orderNumber)->first();

        if (!$orderOtp) {
            return [
                'success' => false,
                'message' => 'No OTP found for this order',
                'status_code' => 404
            ];
        }

        if ($orderOtp->is_verified) {
            return [
                'success' => false,
                'message' => 'OTP already verified',
                'status_code' => 422
            ];
        }

        if ($orderOtp->is_locked) {
            return [
                'success' => false,
                'message' => 'OTP verification locked due to failed attempts',
                'status_code' => 403
            ];
        }

        if ($orderOtp->isExpired()) {
            return [
                'success' => false,
                'message' => 'OTP has expired',
                'status_code' => 422
            ];
        }

        if ($orderOtp->otp_code !== $otpCode) {
            $orderOtp->incrementAttempts();
            
            $remainingAttempts = self::MAX_ATTEMPTS - $orderOtp->attempt_count;
            $message = $remainingAttempts > 0 
                ? "Invalid OTP. You have {$remainingAttempts} attempts remaining."
                : "Invalid OTP. Maximum attempts reached.";

            return [
                'success' => false,
                'message' => $message,
                'status_code' => 422,
                'data' => [
                    'attempts_made' => $orderOtp->attempt_count,
                    'remaining_attempts' => $remainingAttempts,
                    'is_locked' => $orderOtp->is_locked
                ]
            ];
        }

        $orderOtp->markAsVerified();

        return [
            'success' => true,
            'message' => 'OTP verified successfully',
            'status_code' => 200,
            'data' => [
                'verified_at' => Carbon::now(),
                'attempts_made' => $orderOtp->attempt_count
            ]
        ];
    }

    public function getOtpStatus(string $orderNumber): array
    {
        $orderOtp = OrderOtp::where('order_number', $orderNumber)->first();

        if (!$orderOtp) {
            return [
                'success' => false,
                'message' => 'No OTP found for this order',
                'status_code' => 404
            ];
        }

        return [
            'success' => true,
            'data' => [
                'order_number' => $orderOtp->order_number,
                'is_verified' => $orderOtp->is_verified,
                'is_locked' => $orderOtp->is_locked,
                'is_expired' => $orderOtp->isExpired(),
                'attempt_count' => $orderOtp->attempt_count,
                'resend_count' => $orderOtp->resend_count,
                'remaining_attempts' => max(0, self::MAX_ATTEMPTS - $orderOtp->attempt_count),
                'remaining_resends' => max(0, self::MAX_RESENDS - $orderOtp->resend_count),
                'expires_at' => $orderOtp->expires_at
            ]
        ];
    }
}

    public function resendOtp(string $orderNumber): array
    {
        try {
            $orderOtp = OrderOtp::where('order_number', $orderNumber)->first();

            if (!$orderOtp) {
                return [
                    'success' => false,
                    'message' => 'No OTP found for this order',
                    'status_code' => 404
                ];
            }

            if ($orderOtp->is_verified) {
                return [
                    'success' => false,
                    'message' => 'OTP already verified',
                    'status_code' => 422
                ];
            }

            if ($orderOtp->isMaxResendsReached()) {
                return [
                    'success' => false,
                    'message' => 'Maximum resend limit reached',
                    'status_code' => 403
                ];
            }

            $orderOtp->regenerateOtp();

            return [
                'success' => true,
                'message' => 'New OTP sent successfully',
                'status_code' => 200,
                'data' => [
                    'otp_code' => $orderOtp->otp_code,
                    'expires_at' => $orderOtp->expires_at,
                    'expires_in_minutes' => self::EXPIRY_MINUTES,
                    'resend_count' => $orderOtp->resend_count,
                    'remaining_resends' => self::MAX_RESENDS - $orderOtp->resend_count
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to resend OTP',
                'status_code' => 500,
                'error' => $e->getMessage()
            ];
        }
    }
