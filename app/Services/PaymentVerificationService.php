<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentVerificationService
{
    public function verifyPaymentStatus(string $orderNumber): array
    {
        $payment = DB::table('payments')
            ->where('order_number', $orderNumber)
            ->where('status', 'completed')
            ->first();

        if (!$payment) {
            return [
                'verified' => false,
                'message' => 'Payment not found or not completed',
                'payment_id' => null
            ];
        }

        return [
            'verified' => true,
            'message' => 'Payment verified successfully',
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method ?? 'unknown'
        ];
    }

    public function isRecentPayment(string $orderNumber): bool
    {
        $payment = DB::table('payments')
            ->where('order_number', $orderNumber)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDay())
            ->first();

        return $payment !== null;
    }
}
