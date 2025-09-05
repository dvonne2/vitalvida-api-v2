<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Services\NotificationService;
use App\Services\StockDeductionService;
use Exception;

class OtpVerificationController extends Controller
{
    protected $notificationService;
    protected $stockService;

    public function __construct(
        NotificationService $notificationService,
        StockDeductionService $stockService
    ) {
        $this->notificationService = $notificationService;
        $this->stockService = $stockService;
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string|max:100',
            'otp' => 'required|string|size:6',
            'delivery_agent_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid input',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $order = Order::where('order_number', $request->order_number)->first();

            if (!$order) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'Order not found',
                    'message' => 'The order number you entered does not exist.'
                ], 404);
            }

            if ($order->otp_verified) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'OTP already used',
                    'message' => 'This order has already been delivered and verified.'
                ], 409);
            }

            if ($order->delivery_otp !== $request->otp) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid OTP',
                    'message' => 'The OTP you entered is incorrect. Please check and try again.'
                ], 400);
            }

            $order->update([
                'otp_verified' => true,
                'otp_verified_at' => now(),
                'status' => 'delivered'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order delivered successfully!',
                'data' => [
                    'order_number' => $order->order_number,
                    'verified_at' => $order->otp_verified_at
                ]
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'error' => 'Verification failed',
                'message' => 'An error occurred while verifying the OTP.'
            ], 500);
        }
    }
}
