<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OTPService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function checkPayment(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string|exists:orders,order_number'
        ]);

        $order = Order::where('order_number', $request->order_number)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => true,
                'message' => 'Order already paid',
                'order' => $order
            ]);
        }

        try {
            // Call Moniepoint API
            $response = Http::withToken(config('services.moniepoint.api_key'))
                ->timeout(15)
                ->get(config('services.moniepoint.api_url') . '/transactions', [
                    'phone' => $order->customer_phone,
                    'amount' => $order->total_amount * 100,
                    'reference' => $order->order_number,
                    'status' => 'successful',
                    'date_from' => $order->created_at->format('Y-m-d'),
                    'date_to' => now()->format('Y-m-d')
                ]);

            if ($response->successful()) {
                $transactions = $response->json('data', []);
                
                foreach ($transactions as $transaction) {
                    if ($this->isMatchingTransaction($transaction, $order)) {
                        $this->processManualPayment($order, $transaction, $request->user());
                        
                        return response()->json([
                            'success' => true,
                            'message' => 'Payment found and confirmed!',
                            'order' => $order->fresh(),
                            'transaction' => $transaction
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'No matching payment found in Moniepoint',
                'checked_at' => now()
            ]);

        } catch (\Exception $e) {
            Log::error('Manual payment check failed', [
                'order_number' => $order->order_number,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment: ' . $e->getMessage()
            ], 500);
        }
    }

    private function isMatchingTransaction($transaction, $order)
    {
        return $transaction['amount'] == ($order->total_amount * 100) &&
               $transaction['customer_phone'] == $order->customer_phone &&
               $transaction['status'] == 'successful';
    }

    private function processManualPayment($order, $transaction, $user)
    {
        // Update order
        $order->update([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_reference' => $transaction['reference'] ?? 'MANUAL-' . time(),
            'verified_by' => $user->id,
            'verified_at' => now()
        ]);

        // Create payment record
        Payment::create([
            'payment_id' => 'VV-PAY-MANUAL-' . $order->id,
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

        // Log manual verification
        Log::info('🔧 MANUAL VERIFICATION SUCCESSFUL', [
            'order_number' => $order->order_number,
            'verified_by' => $user->name,
            'user_id' => $user->id,
            'customer_phone' => $order->customer_phone,
            'amount' => $order->total_amount,
            'transaction_ref' => $transaction['reference']
        ]);
    }
}
