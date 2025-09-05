<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PayoutEligibilityService
{
    public function checkEligibility($orderId)
    {
        $order = DB::table('orders')->where('id', $orderId)->first();
        
        if (!$order) {
            return [
                'eligible' => false,
                'reasons' => ['Order not found']
            ];
        }

        $reasons = [];
        $eligible = true;

        // 1. Check payment verification
        $paymentVerified = DB::table('system_logs')
            ->where('type', 'payment_verified')
            ->whereRaw("JSON_EXTRACT(context, '$.order_id') = ?", [$orderId])
            ->exists();

        if (!$paymentVerified) {
            $eligible = false;
            $reasons[] = 'Payment not verified';
        }

        // 2. Check OTP verification
        if (!$order->otp_verified) {
            $eligible = false;
            $reasons[] = 'OTP not verified';
        }

        // 3. Check IM photo approval for weekly bonus
        $daId = $order->assigned_da_id;
        $currentWeek = now()->startOfWeek();
        
        $photoApproved = DB::table('system_logs')
            ->where('type', 'photo_approved')
            ->whereRaw("JSON_EXTRACT(context, '$.da_id') = ?", [$daId])
            ->where('created_at', '>=', $currentWeek)
            ->exists();

        if (!$photoApproved) {
            $eligible = false;
            $reasons[] = 'Photo not approved';
        }

        return [
            'eligible' => $eligible,
            'reasons' => $reasons,
            'order_id' => $orderId,
            'da_id' => $daId
        ];
    }
}
