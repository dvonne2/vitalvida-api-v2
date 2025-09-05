<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OtpVerificationService
{
    public function verifyOtp(string $orderNumber, string $otpCode): array
    {
        $otp = DB::table('delivery_otps')
            ->where('order_number', $orderNumber)
            ->where('otp_code', $otpCode)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return [
                'verified' => false,
                'message' => 'Invalid or expired OTP'
            ];
        }

        DB::table('delivery_otps')
            ->where('id', $otp->id)
            ->update(['status' => 'used', 'used_at' => now()]);

        return [
            'verified' => true,
            'message' => 'OTP verified successfully'
        ];
    }

    public function isOtpRequired(string $orderNumber): bool
    {
        $order = DB::table('orders')
            ->where('order_number', $orderNumber)
            ->first();

        return in_array($order->fulfillment_type ?? 'delivery', ['delivery', 'pickup']);
    }
}
