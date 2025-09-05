<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Payment;
use App\Services\OTPService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifyMoniepointPayments extends Command
{
    protected $signature = 'verify:moniepoint-payments';
    protected $description = 'Check Moniepoint for missed webhook payments every minute';

    public function handle()
    {
        $this->info('🔍 Starting fallback payment verification...');
        
        // Get pending orders from last 60 minutes
        $pendingOrders = Order::where('status', 'pending')
            ->where('payment_status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(60))
            ->get();

        $this->info("Found {$pendingOrders->count()} pending orders to verify");

        foreach ($pendingOrders as $order) {
            $this->verifyOrderPayment($order);
        }

        $this->info('✅ Fallback verification completed');
    }

    private function verifyOrderPayment($order)
    {
        try {
            // Call Moniepoint Transaction API
            $response = Http::withToken(config('services.moniepoint.api_key'))
                ->timeout(10)
                ->get(config('services.moniepoint.api_url') . '/transactions', [
                    'phone' => $order->customer_phone,
                    'amount' => $order->total_amount * 100, // Convert to kobo
                    'reference' => $order->order_number,
                    'status' => 'successful',
                    'date_from' => $order->created_at->format('Y-m-d'),
                    'date_to' => now()->format('Y-m-d')
                ]);

            if ($response->successful()) {
                $transactions = $response->json('data', []);
                
                foreach ($transactions as $transaction) {
                    if ($this->isMatchingTransaction($transaction, $order)) {
                        $this->processFoundPayment($order, $transaction);
                        return;
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Fallback verification failed', [
                'order_id' => $order->order_number,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function isMatchingTransaction($transaction, $order)
    {
        return $transaction['amount'] == ($order->total_amount * 100) &&
               $transaction['customer_phone'] == $order->customer_phone &&
               $transaction['status'] == 'successful';
    }

    private function processFoundPayment($order, $transaction)
    {
        // Update order status
        $order->update([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_reference' => $transaction['reference'] ?? 'FALLBACK-' . time()
        ]);

        // Create payment record
        Payment::create([
            'payment_id' => 'VV-PAY-FALLBACK-' . $order->id,
            'order_id' => $order->id,
            'amount' => $order->total_amount * 100,
            'payment_method' => 'pos',
            'transaction_reference' => $transaction['reference'],
            'moniepoint_reference' => $transaction['transaction_id'] ?? null,
            'status' => 'successful',
            'paid_at' => now(),
            'customer_id' => $order->customer_id,
            'moniepoint_response' => json_encode($transaction)
        ]);

        // Send OTP
        app(OTPService::class)->sendOrderOTP($order);

        // Log success
        Log::info('🚨 FALLBACK SAVED THE DAY!', [
            'order_id' => $order->order_number,
            'customer_phone' => $order->customer_phone,
            'amount' => $order->total_amount,
            'message' => 'Webhook missed - fallback verification successful'
        ]);

        $this->warn("🚨 Order {$order->order_number} confirmed via FALLBACK!");
    }
}
