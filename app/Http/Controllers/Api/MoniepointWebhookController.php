<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Customer;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MoniepointWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        Log::info('🎯 MONIEPOINT WEBHOOK RECEIVED', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'ip' => $request->ip(),
            'timestamp' => now()
        ]);

        try {
            // Extract and validate Moniepoint data
            $moniepointData = $this->extractMoniepointData($request);
            
            if (!$moniepointData) {
                return $this->errorResponse('Invalid webhook payload', 422);
            }

            // Find and validate order
            $order = $this->findAndValidateOrder($moniepointData);
            
            if (!$order) {
                return $this->errorResponse('Order not found or invalid', 404);
            }

            // Check for duplicate payment
            if ($this->isDuplicatePayment($order, $moniepointData)) {
                return $this->successResponse('Payment already processed', $order);
            }

            // Process the payment
            $payment = $this->processPayment($order, $moniepointData);
            
            if (!$payment) {
                return $this->errorResponse('Payment processing failed', 500);
            }

            // Send OTP
            Log::info('🔧 About to send OTP', [
                'order_number' => $order->order_number,
                'customer_phone' => $order->customer_phone
            ]);
            
            $otpSent = $this->sendOrderOTP($order);
            
            Log::info('🔧 OTP send result', [
                'order_number' => $order->order_number,
                'otp_sent' => $otpSent
            ]);

            return $this->successResponse('Payment processed successfully', $order, $payment, $otpSent);

        } catch (\Exception $e) {
            Log::error('🚨 MONIEPOINT WEBHOOK ERROR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return $this->errorResponse('Internal server error', 500);
        }
    }

    private function extractMoniepointData(Request $request)
    {
        // Handle real Moniepoint webhook format
        $data = $request->input('data');
        
        if ($data && isset($data['customFields'])) {
            // Real Moniepoint webhook
            return [
                'order_number' => $data['customFields']['Order number'] ?? null,
                'customer_phone' => $data['customFields']['Phone Number'] ?? null,
                'amount_kobo' => $data['amount'] ?? null,
                'transaction_reference' => $data['transactionReference'] ?? null,
                'transaction_status' => $data['transactionStatus'] ?? null,
                'response_message' => $data['responseMessage'] ?? null,
                'terminal_serial' => $data['terminalSerial'] ?? null,
                'transaction_time' => $data['transactionTime'] ?? null,
                'source' => 'moniepoint_webhook'
            ];
        }

        // Handle test webhook format (for development only)
        if ($request->has('reference') || $request->has('order_id')) {
            return [
                'order_number' => $request->input('order_id'),
                'customer_phone' => $request->input('customer_phone'),
                'amount_kobo' => $request->input('amount'),
                'transaction_reference' => $request->input('reference') ?? $request->input('transactionReference'),
                'transaction_status' => $request->input('status') ?? 'successful',
                'response_message' => 'TEST PAYMENT',
                'terminal_serial' => 'TEST_TERMINAL',
                'transaction_time' => now()->toISOString(),
                'source' => 'test_webhook'
            ];
        }

        return null;
    }

    private function findAndValidateOrder($data)
    {
        if (!$data['order_number']) {
            Log::error('🛑 SECURITY: No order number provided', $data);
            return null;
        }

        $order = Order::where('order_number', $data['order_number'])->first();

        if (!$order) {
            Log::error('🛑 SECURITY: Payment for non-existent order', [
                'order_number' => $data['order_number'],
                'amount_naira' => $data['amount_kobo'] / 100,
                'phone' => $data['customer_phone']
            ]);
            return null;
        }

        // Validate order status
        if ($order->payment_status === 'paid') {
            Log::info('⚠️ Payment attempt for already paid order', [
                'order_number' => $order->order_number,
                'current_status' => $order->payment_status
            ]);
            return $order; // Return for duplicate check
        }

        return $order;
    }

    private function isDuplicatePayment($order, $data)
    {
        $existingPayment = Payment::where('order_id', $order->id)
            ->where('status', 'successful')
            ->first();

        if ($existingPayment) {
            Log::info('✅ Duplicate payment blocked', [
                'order_number' => $order->order_number,
                'existing_payment_id' => $existingPayment->payment_id
            ]);
            return true;
        }

        return false;
    }

    private function processPayment($order, $data)
    {
        return DB::transaction(function () use ($order, $data) {
            // Validate amount matches (convert kobo to naira for comparison)
            $amountNaira = $data['amount_kobo'] / 100;
            
            if (abs($amountNaira - $order->total_amount) > 0.01) {
                Log::warning('💰 Amount mismatch detected', [
                    'order_number' => $order->order_number,
                    'expected_naira' => $order->total_amount,
                    'received_naira' => $amountNaira,
                    'difference' => abs($amountNaira - $order->total_amount)
                ]);
                
                // Still process but log the mismatch
            }

            // Create payment record with unique ID
            $payment = Payment::create([
                'payment_id' => 'VV-PAY-' . str_pad(time() . rand(100, 999), 6, '0', STR_PAD_LEFT),
                'order_id' => $order->id,
                'amount' => $data['amount_kobo'], // Store in kobo
                'payment_method' => 'pos',
                'transaction_reference' => $data['transaction_reference'],
                'moniepoint_reference' => $data['transaction_reference'],
                'status' => strtolower($data['transaction_status']) === 'approved' ? 'successful' : 'failed',
                'paid_at' => now(),
                'customer_id' => $order->customer_id ?? $this->getOrCreateCustomerId($order),
                'moniepoint_response' => json_encode($data)
            ]);

            // Update order status
            $order->update([
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'payment_reference' => $data['transaction_reference'],
                'verified_at' => now()
            ]);

            Log::info('✅ PAYMENT PROCESSED SUCCESSFULLY', [
                'order_number' => $order->order_number,
                'payment_id' => $payment->payment_id,
                'amount_naira' => $amountNaira,
                'customer_phone' => $order->customer_phone,
                'source' => $data['source']
            ]);

            return $payment;
        });
    }

    private function getOrCreateCustomerId($order)
    {
        // Try to find existing customer by phone
        if ($order->customer_phone) {
            $customer = Customer::where('phone', $order->customer_phone)->first();
            if ($customer) {
                return $customer->id;
            }
        }

        // Create new customer if not found
        $customer = Customer::create([
            'customer_id' => 'CUST-' . time() . '-' . rand(1000, 9999),
            'name' => $order->customer_name ?? 'Unknown Customer',
            'phone' => $order->customer_phone ?? '08000000000',
            'email' => $order->customer_email ?? null,
            'address' => $order->delivery_address ?? 'No address provided'
        ]);

        // Update order with customer_id
        $order->update(['customer_id' => $customer->id]);

        return $customer->id;
    }

    private function sendOrderOTP($order)
    {
        try {
            // Ensure customer exists
            if (!$order->customer_phone) {
                Log::error('❌ No customer phone for OTP', [
                    'order_number' => $order->order_number
                ]);
                return false;
            }

            // Send OTP using the updated service
            Log::info('🔧 Creating OTP service instance');
            $otpService = app(OtpService::class);
            Log::info('🔧 OTP service created successfully', [
                'class' => get_class($otpService),
                'file' => (new \ReflectionClass($otpService))->getFileName()
            ]);
            
            Log::info('🔧 Calling generateAndSendOtp method');
            $otpSent = $otpService->generateAndSendOtp($order);
            Log::info('🔧 generateAndSendOtp method completed', ['result' => $otpSent]);

            Log::info('🔧 OTP result type check', [
                'otp_sent_type' => gettype($otpSent),
                'otp_sent_value' => $otpSent,
                'is_array' => is_array($otpSent),
                'is_bool' => is_bool($otpSent)
            ]);
            
            if (is_array($otpSent)) {
                // Old OTP service format
                $success = $otpSent['success'] ?? false;
                Log::info('🔧 Old OTP service format detected', [
                    'success' => $success,
                    'message' => $otpSent['message'] ?? 'No message'
                ]);
                return $success;
            } else {
                // New OTP service format (boolean)
                Log::info('🔧 New OTP service format detected', [
                    'success' => $otpSent
                ]);
                return $otpSent;
            }

        } catch (\Exception $e) {
            Log::error('❌ OTP GENERATION FAILED', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function successResponse($message, $order, $payment = null, $otpSent = false)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'order_number' => $order->order_number,
                'order_status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_id' => $payment?->payment_id,
                'amount_naira' => $payment ? $payment->amount / 100 : $order->total_amount,
                'customer_phone' => $order->customer_phone,
                'otp_sent' => $otpSent,
                'processed_at' => now()
            ]
        ]);
    }

    private function errorResponse($message, $statusCode)
    {
        return response()->json([
            'success' => false,
            'error' => $message,
            'timestamp' => now()
        ], $statusCode);
    }

    // Test endpoint for development (remove in production)
    public function testWebhook(Request $request)
    {
        if (app()->environment('production')) {
            return response()->json(['error' => 'Test endpoint disabled in production'], 403);
        }

        Log::info('🧪 TEST WEBHOOK CALLED', $request->all());

        // Use current timestamp for unique test reference
        $testData = [
            'reference' => 'TEST-' . now()->timestamp,
            'amount' => $request->input('amount', 10000), // Default ₦100 in kobo
            'status' => 'successful',
            'order_id' => $request->input('order_id', '100001'), // Use real order
            'customer_phone' => $request->input('customer_phone', '08012345678'),
            'transactionReference' => 'TEST-' . now()->timestamp
        ];

        // Process through the main webhook handler
        $testRequest = new Request($testData);
        return $this->handleWebhook($testRequest);
    }
} 