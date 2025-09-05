<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class SystemLogger
{
    public static function logAction($actionType, $userId = null, $ipAddress = null, $context = [])
    {
        try {
            $ipAddress = $ipAddress ?? Request::ip();
            $userId = $userId ?? auth()->id();

            DB::table('system_logs')->insert([
                'type' => $actionType,
                'message' => self::generateMessage($actionType, $context),
                'context' => json_encode($context),
                'level' => self::determineLevel($actionType),
                'user_id' => $userId,
                'created_at' => now()
            ]);

        } catch (\Exception $e) {
            \Log::error("SystemLogger failed: " . $e->getMessage());
        }
    }

    private static function generateMessage($actionType, $context)
    {
        switch ($actionType) {
            case 'payment_verified':
                return "Payment verified for Order #{$context['order_id']} - Amount: ₦{$context['amount']}";
            case 'otp_verified':
                return "OTP verified for Order #{$context['order_id']} by DA: {$context['da_id']}";
            case 'otp_verification_failed':
                return "OTP verification FAILED for Order #{$context['order_id']}";
            case 'payout_blocked':
                return "Payout BLOCKED for Order #{$context['order_id']} - Reasons: " . implode(', ', $context['reasons']);
            case 'fraud_attempt':
                return "FRAUD ATTEMPT detected for Order #{$context['order_id']}";
            default:
                return "Action performed: {$actionType}";
        }
    }

    private static function determineLevel($actionType)
    {
        $criticalActions = ['fraud_attempt', 'payout_blocked', 'otp_verification_failed'];
        return in_array($actionType, $criticalActions) ? 'critical' : 'info';
    }
}
