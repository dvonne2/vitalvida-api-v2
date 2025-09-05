<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\OtpNotificationService;
use Illuminate\Support\Facades\Log;

class MoniepointWebhookController extends Controller
{
    protected OtpNotificationService $otpService;

    public function __construct(OtpNotificationService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function handleWebhook(Request $request)
    {
        try {
            Log::info('Moniepoint webhook received', $request->all());

            $orderNumber = $request->input('order_number');
            $customerPhone = $request->input('customer_phone');
            $amount = $request->input('amount');
            $transactionReference = $request->input('transaction_reference');

            if (!$orderNumber || !$customerPhone || !$amount || !$transactionReference) {
                return response()->json(['error' => 'Missing required fields'], 400);
            }

            $order = Order::where('order_number', $orderNumber)
                         ->where('payment_status', 'pending')
                         ->first();

            if (!$order) {
                return response()->json(['error' => 'Order not found or already processed'], 404);
            }

            if ($order->total_amount != $amount) {
                return response()->json(['error' => 'Amount mismatch'], 400);
            }

            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            $order->update([
                'payment_status' => 'paid',
                'status' => 'ready_for_delivery',
                'payment_reference' => $transactionReference,
                'delivery_otp' => $otp
            ]);

            // Send OTP notifications via all channels
            $notificationResults = $this->otpService->sendOtpToCustomer($order, $otp);

            Log::info('Payment processed and notifications sent', [
                'order' => $order->order_number,
                'otp_generated' => $otp,
                'notifications' => $notificationResults
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed, OTP sent to customer',
                'order_id' => $order->id,
                'notifications' => [
                    'sms_sent' => $notificationResults['sms'],
                    'whatsapp_sent' => $notificationResults['whatsapp'],
                    'email_sent' => $notificationResults['email']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Moniepoint webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
