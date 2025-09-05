<?php

namespace App\Http\Controllers;

use App\Events\DeliveryConfirmed;
use App\Services\OtpVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DeliveryController extends Controller
{
    private $otpService;

    public function __construct(OtpVerificationService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function confirmDelivery(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string|exists:orders,order_number',
            'otp_code' => 'required|string|size:6',
            'delivery_location' => 'nullable|string',
            'recipient_name' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Verify OTP
            $otpVerification = $this->otpService->verifyOtp(
                $request->order_number, 
                $request->otp_code
            );
            
            if (!$otpVerification['verified']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP: ' . $otpVerification['message']
                ], 400);
            }

            // Update order status
            DB::table('orders')
                ->where('order_number', $request->order_number)
                ->update([
                    'status' => 'delivered',
                    'delivered_at' => now()
                ]);

            // Record delivery
            $deliveryId = DB::table('deliveries')->insertGetId([
                'order_number' => $request->order_number,
                'delivery_location' => $request->delivery_location,
                'recipient_name' => $request->recipient_name,
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
                'otp_verified' => true,
                'created_at' => now()
            ]);

            // TRIGGER AUTOMATIC INVENTORY DEDUCTION
            event(new DeliveryConfirmed(
                $request->order_number,
                ['delivery_id' => $deliveryId],
                auth()->id()
            ));

            return response()->json([
                'success' => true,
                'message' => 'Delivery confirmed! Inventory deduction in progress.',
                'data' => [
                    'order_number' => $request->order_number,
                    'delivery_id' => $deliveryId,
                    'status' => 'delivered'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery confirmation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getDeliveryStatus(string $orderNumber): JsonResponse
    {
        $order = DB::table('orders')
            ->where('order_number', $orderNumber)
            ->first();

        return response()->json([
            'success' => true,
            'order_number' => $orderNumber,
            'status' => $order->status ?? 'not_found',
            'inventory_processed' => $order->inventory_processed ?? false,
            'delivered_at' => $order->delivered_at ?? null
        ]);
    }
}
