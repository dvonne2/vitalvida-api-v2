<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\OtpNotificationService;
use App\Services\StockDeductionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DeliveryOtpController extends Controller
{
    protected $otpNotificationService;
    protected $stockDeductionService;

    public function __construct(
        OtpNotificationService $otpNotificationService,
        StockDeductionService $stockDeductionService
    ) {
        $this->otpNotificationService = $otpNotificationService;
        $this->stockDeductionService = $stockDeductionService;
    }

    public function verifyOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_number' => 'required|string',
                'otp' => 'required|string|size:6',
                'delivery_agent_id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'message' => 'Please check your input and try again.',
                    'errors' => $validator->errors()
                ], 400);
            }

            $orderNumber = $request->order_number;
            $otp = $request->otp;
            $deliveryAgentId = $request->delivery_agent_id;

            $order = Order::where('order_number', $orderNumber)->first();

            if (!$order) {
                Log::warning('OTP verification attempted for non-existent order', [
                    'order_number' => $orderNumber,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Order not found',
                    'message' => 'The order number provided does not exist.'
                ], 404);
            }

            if ($order->status !== 'ready_for_delivery') {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid order status',
                    'message' => 'This order is not ready for delivery.'
                ], 400);
            }

            if ($order->status === 'delivered') {
                return response()->json([
                    'success' => false,
                    'error' => 'Already delivered',
                    'message' => 'This order has already been delivered.'
                ], 400);
            }

            if ($order->delivery_otp !== $otp) {
                Log::warning('Invalid OTP attempt', [
                    'order_number' => $orderNumber,
                    'provided_otp' => $otp,
                    'delivery_agent_id' => $deliveryAgentId,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Invalid OTP',
                    'message' => 'The OTP you entered is incorrect. Please check and try again.'
                ], 400);
            }

            $order->update([
                'status' => 'delivered',
                'delivery_date' => now(),
                'assigned_da_id' => $deliveryAgentId,
                'delivery_otp' => null
            ]);

            $stockDeductionResult = $this->stockDeductionService->deductStock($order, $deliveryAgentId);

            $notifications = [
                'delivery_agent_notified' => $this->otpNotificationService->notifyDeliveryAgentSuccess($order, $deliveryAgentId),
                'customer_sms' => $this->otpNotificationService->sendCustomerDeliveryConfirmationSms($order),
                'customer_whatsapp' => $this->otpNotificationService->sendCustomerDeliveryConfirmationWhatsApp($order),
                'customer_email' => $this->otpNotificationService->sendDeliveryConfirmationEmail($order)
            ];

            Log::info('Order delivered successfully', [
                'order_number' => $orderNumber,
                'delivery_agent_id' => $deliveryAgentId,
                'delivery_date' => $order->delivery_date,
                'stock_deducted' => $stockDeductionResult['success'],
                'notifications' => $notifications
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order delivered successfully!',
                'data' => [
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'delivery_address' => $order->delivery_address,
                    'verified_at' => $order->delivery_date,
                    'stock_deducted' => $stockDeductionResult['success'],
                    'notifications_sent' => $notifications
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('OTP verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Server error',
                'message' => 'An error occurred while processing your request. Please try again.'
            ], 500);
        }
    }

    public function getOrderInfo($orderNumber)
    {
        try {
            $order = Order::where('order_number', $orderNumber)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'error' => 'Order not found',
                    'message' => 'The order number provided does not exist.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'delivery_address' => $order->delivery_address,
                    'items' => $order->items,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'has_otp' => !empty($order->delivery_otp)
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to fetch order info', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage()
            ]);

}
    }
}
