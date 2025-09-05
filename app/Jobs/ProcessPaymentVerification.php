<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\PaymentVerificationService;
use Illuminate\Support\Facades\Log;

class ProcessPaymentVerification implements ShouldQueue
{
    use Queueable;

    protected $paymentData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $paymentData)
    {
        $this->paymentData = $paymentData;
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentVerificationService $paymentService): void
    {
        try {
            Log::info('Processing payment verification job', [
                'order_id' => $this->paymentData['order_id'],
                'amount' => $this->paymentData['amount'],
                'transaction_reference' => $this->paymentData['transaction_reference']
            ]);

            $result = $paymentService->processPayment($this->paymentData);

            Log::info('Payment verification job completed', [
                'order_id' => $this->paymentData['order_id'],
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Payment verification job failed', [
                'error' => $e->getMessage(),
                'payment_data' => $this->paymentData
            ]);
        }
    }
}
