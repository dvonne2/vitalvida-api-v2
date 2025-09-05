<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\PaymentVerificationService;
use App\Models\Payment;
use App\Models\Order;
use App\Models\PaymentMismatch;
use App\Jobs\ProcessPaymentVerification;
use App\Events\PaymentReceived;
use Illuminate\Support\Facades\Validator;

class MoneyPointWebhookController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentVerificationService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Receive and process Moniepoint payment webhooks
     */
    public function receivePayment(Request $request)
    {
        try {
            // Log incoming webhook for audit
            Log::info('Moniepoint webhook received', [
                'payload' => $request->all(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
                'headers' => $request->headers->all()
            ]);

            // Validate webhook signature (security)
            if (!$this->validateWebhookSignature($request)) {
                Log::warning('Invalid webhook signature', [
                    'ip' => $request->ip(),
                    'signature' => $request->header('X-Moniepoint-Signature'),
                    'payload' => $request->all()
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Extract payment data from webhook
            $paymentData = $this->extractPaymentData($request);

            // Validate required fields
            $validator = Validator::make($paymentData, [
                'amount' => 'required|numeric|min:0',
                'transaction_reference' => 'required|string|max:100',
                'order_id' => 'required|string|max:50',
                'customer_phone' => 'required|string|max:20',
                'payment_date' => 'required|date',
                'status' => 'required|in:successful,failed,pending'
            ]);

            if ($validator->fails()) {
                Log::error('Invalid webhook payload', [
                    'errors' => $validator->errors(),
                    'payload' => $paymentData
                ]);
                return response()->json([
                    'error' => 'Invalid payload',
                    'details' => $validator->errors()
                ], 400);
            }

            // CRITICAL: Validate order exists before processing
            $order = Order::where('order_number', $paymentData['order_id'])->first();
            if (!$order) {
                Log::error('🛑 SECURITY: Payment for non-existent order', [
                    'order_id' => $paymentData['order_id'],
                    'ip' => $request->ip(),
                    'payload' => $paymentData,
                    'user_agent' => $request->userAgent()
                ]);
                return response()->json(['error' => 'Invalid order'], 400);
            }

            // Only process successful payments
            if ($paymentData['status'] !== 'successful') {
                Log::info('Non-successful payment received', [
                    'status' => $paymentData['status'],
                    'order_id' => $paymentData['order_id'],
                    'amount' => $paymentData['amount']
                ]);
                
                return response()->json([
                    'status' => 'acknowledged',
                    'message' => 'Payment status noted but not processed'
                ]);
            }

            // Process payment verification asynchronously
            ProcessPaymentVerification::dispatch($paymentData);

            // Fire payment received event
            event(new PaymentReceived($paymentData));

            Log::info('Payment webhook processed successfully', [
                'order_id' => $paymentData['order_id'],
                'amount' => $paymentData['amount'],
                'transaction_reference' => $paymentData['transaction_reference']
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment received and queued for processing',
                'order_id' => $paymentData['order_id'],
                'reference' => $paymentData['transaction_reference']
            ]);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'error' => 'Processing failed',
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Test webhook endpoint for development
     */
    public function testWebhook(Request $request)
    {
        // Only available in development
        if (!app()->environment('local')) {
            return response()->json(['error' => 'Not available in production'], 403);
        }

        // Create test payment data
        $testPaymentData = [
            'amount' => $request->input('amount', 1000),
            'transaction_reference' => $request->input('transaction_reference', 'TEST-' . time()),
            'order_id' => $request->input('order_id', 'TEST-' . time()),
            'customer_phone' => $request->input('customer_phone', '08000000000'),
            'payment_date' => now()->toISOString(),
            'status' => 'successful',
            'raw_payload' => json_encode($request->all())
        ];

        // Process test payment
        $result = $this->paymentService->processPayment($testPaymentData);

        return response()->json([
            'status' => 'test_processed',
            'result' => $result,
            'test_data' => $testPaymentData
        ]);
    }

    /**
     * Get webhook processing statistics
     */
    public function getWebhookStats(Request $request)
    {
        $period = $request->get('period', 'today');
        $startDate = $this->getStartDate($period);

        $stats = [
            'total_webhooks' => Payment::where('created_at', '>=', $startDate)->count(),
            'successful_verifications' => Payment::where('created_at', '>=', $startDate)
                ->where('status', 'confirmed')->count(),
            'failed_verifications' => Payment::where('created_at', '>=', $startDate)
                ->where('status', 'verification_failed')->count(),
            'pending_verifications' => Payment::where('created_at', '>=', $startDate)
                ->where('status', 'pending')->count(),
            'payment_mismatches' => PaymentMismatch::where('created_at', '>=', $startDate)->count(),
            'total_amount_processed' => Payment::where('created_at', '>=', $startDate)
                ->where('status', 'confirmed')->sum('amount'),
            'average_processing_time' => $this->calculateAverageProcessingTime($startDate)
        ];

        return response()->json([
            'success' => true,
            'period' => $period,
            'stats' => $stats
        ]);
    }

    /**
     * Validate webhook signature for security
     */
    private function validateWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Moniepoint-Signature');
        $payload = $request->getContent();
        $secret = config('services.moniepoint.webhook_secret');

        // If no secret configured, skip validation in development
        if (!$secret) {
            if (app()->environment('local')) {
                Log::warning('Webhook signature validation skipped - no secret configured');
                return true;
            }
            return false;
        }

        if (!$signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($signature, $expectedSignature);
    }

    /**
     * Extract standardized payment data from webhook
     */
    private function extractPaymentData(Request $request): array
    {
        $data = $request->all();

        // Handle real Moniepoint webhook format (nested structure)
        if (isset($data['data'])) {
            $webhookData = $data['data'];
            $customFields = $webhookData['customFields'] ?? [];
            
            return [
                'amount' => $webhookData['amount'] ?? 0,
                'transaction_reference' => $webhookData['transactionReference'] ?? '',
                'order_id' => $customFields['Order number'] ?? $webhookData['merchantReference'] ?? '',
                'customer_phone' => $customFields['Phone Number'] ?? '',
                'payment_date' => $webhookData['transactionTime'] ?? now()->toISOString(),
                'status' => $webhookData['transactionStatus'] === 'APPROVED' ? 'successful' : 'failed',
                'merchant_id' => $webhookData['businessId'] ?? null,
                'terminal_id' => $webhookData['terminalSerial'] ?? null,
                'raw_payload' => json_encode($data)
            ];
        }

        // Handle test webhook format (flat structure)
        return [
            'amount' => $data['amount'] ?? $data['transaction_amount'] ?? 0,
            'transaction_reference' => $data['transaction_reference'] ?? $data['reference'] ?? '',
            'order_id' => $data['order_id'] ?? $data['order_number'] ?? $data['merchant_reference'] ?? '',
            'customer_phone' => $data['customer_phone'] ?? $data['phone'] ?? $data['customer_mobile'] ?? '',
            'payment_date' => $data['payment_date'] ?? $data['transaction_date'] ?? now()->toISOString(),
            'status' => $data['status'] ?? $data['transaction_status'] ?? 'pending',
            'merchant_id' => $data['merchant_id'] ?? null,
            'terminal_id' => $data['terminal_id'] ?? null,
            'raw_payload' => json_encode($data)
        ];
    }

    /**
     * Calculate average processing time for webhooks
     */
    private function calculateAverageProcessingTime($startDate): float
    {
        $payments = Payment::where('created_at', '>=', $startDate)
            ->whereNotNull('verified_at')
            ->get();

        if ($payments->isEmpty()) {
            return 0;
        }

        $totalSeconds = $payments->sum(function ($payment) {
            return $payment->created_at->diffInSeconds($payment->verified_at);
        });

        return round($totalSeconds / $payments->count(), 2);
    }

    /**
     * Get start date for statistics period
     */
    private function getStartDate(string $period): \Carbon\Carbon
    {
        return match($period) {
            'today' => now()->startOfDay(),
            'yesterday' => now()->subDay()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfDay()
        };
    }
}
