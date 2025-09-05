<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Payment;
use App\Models\PaymentMismatch;
use App\Models\Order;
use App\Models\OrderHistory;
use Illuminate\Support\Facades\Log;

class PaymentReconciliationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting automated payment reconciliation job');
        
        $this->reconcilePayments();
        $this->detectMismatches();
        $this->autoFreezeSuspiciousPayments();
        $this->generateReconciliationReport();
        
        Log::info('Automated payment reconciliation job completed');
    }

    /**
     * Reconcile payments with Moniepoint data
     */
    private function reconcilePayments(): void
    {
        // Get payments that need reconciliation
        $payments = Payment::where('status', 'pending')
            ->where('created_at', '>=', now()->subDays(1))
            ->get();

        foreach ($payments as $payment) {
            // Simulate Moniepoint API call
            $moniepointData = $this->getMoniepointData($payment->reference);
            
            if ($moniepointData) {
                $this->processPaymentReconciliation($payment, $moniepointData);
            }
        }
    }

    /**
     * Detect payment mismatches
     */
    private function detectMismatches(): void
    {
        // Find payments where claimed amount doesn't match received amount
        $mismatches = Payment::where('amount_expected', '!=', 'amount_received')
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        foreach ($mismatches as $payment) {
            $difference = $payment->amount_expected - $payment->amount_received;
            
            if ($difference > 0) {
                PaymentMismatch::updateOrCreate(
                    [
                        'payment_id' => $payment->id,
                        'order_id' => $payment->order_id,
                    ],
                    [
                        'staff_claimed_by' => $payment->staff_claimed_by,
                        'amount_expected' => $payment->amount_expected,
                        'amount_received' => $payment->amount_received,
                        'amount_difference' => $difference,
                        'resolution_status' => 'pending',
                        'auto_frozen' => $difference > 50000, // Auto-freeze if difference > ₦50k
                        'detected_at' => now(),
                    ]
                );
            }
        }
    }

    /**
     * Auto-freeze suspicious payments
     */
    private function autoFreezeSuspiciousPayments(): void
    {
        $suspiciousPayments = PaymentMismatch::where('amount_difference', '>', 100000)
            ->where('auto_frozen', false)
            ->get();

        foreach ($suspiciousPayments as $mismatch) {
            $mismatch->update(['auto_frozen' => true]);
            
            // Log the auto-freeze action
            OrderHistory::create([
                'order_id' => $mismatch->order_id,
                'staff_id' => $mismatch->staff_claimed_by,
                'action' => 'payment_auto_frozen',
                'previous_status' => 'pending',
                'new_status' => 'frozen',
                'timestamp' => now(),
                'notes' => "Payment auto-frozen due to ₦" . number_format($mismatch->amount_difference) . " mismatch",
                'auto_action' => true,
            ]);
            
            Log::info("Payment auto-frozen for mismatch ID: {$mismatch->id}");
        }
    }

    /**
     * Generate reconciliation report
     */
    private function generateReconciliationReport(): void
    {
        $today = today();
        
        $report = [
            'total_payments' => Payment::whereDate('created_at', $today)->count(),
            'reconciled_payments' => Payment::whereDate('created_at', $today)
                ->where('status', 'confirmed')->count(),
            'pending_payments' => Payment::whereDate('created_at', $today)
                ->where('status', 'pending')->count(),
            'mismatches_detected' => PaymentMismatch::whereDate('created_at', $today)->count(),
            'auto_frozen_payments' => PaymentMismatch::whereDate('created_at', $today)
                ->where('auto_frozen', true)->count(),
            'total_amount_reconciled' => Payment::whereDate('created_at', $today)
                ->where('status', 'confirmed')->sum('amount_received'),
            'total_mismatch_amount' => PaymentMismatch::whereDate('created_at', $today)
                ->sum('amount_difference'),
        ];
        
        Log::info('Payment reconciliation report', $report);
    }

    /**
     * Process payment reconciliation
     */
    private function processPaymentReconciliation($payment, $moniepointData): void
    {
        $expectedAmount = $payment->amount_expected;
        $receivedAmount = $moniepointData['amount'] ?? 0;
        
        if ($expectedAmount == $receivedAmount) {
            // Payment matches
            $payment->update([
                'status' => 'confirmed',
                'amount_received' => $receivedAmount,
                'confirmed_at' => now(),
                'moniepoint_reference' => $moniepointData['reference'] ?? null,
            ]);
            
            // Update order payment status
            if ($payment->order) {
                $payment->order->update(['payment_status' => 'confirmed']);
            }
            
            Log::info("Payment confirmed: {$payment->reference}");
        } else {
            // Payment mismatch
            $payment->update([
                'status' => 'mismatch',
                'amount_received' => $receivedAmount,
            ]);
            
            // Create mismatch record
            PaymentMismatch::create([
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'staff_claimed_by' => $payment->staff_claimed_by,
                'amount_expected' => $expectedAmount,
                'amount_received' => $receivedAmount,
                'amount_difference' => $expectedAmount - $receivedAmount,
                'resolution_status' => 'pending',
                'auto_frozen' => ($expectedAmount - $receivedAmount) > 50000,
                'detected_at' => now(),
            ]);
            
            Log::warning("Payment mismatch detected: {$payment->reference}");
        }
    }

    /**
     * Simulate Moniepoint API call
     */
    private function getMoniepointData($reference): ?array
    {
        // Simulate API call to Moniepoint
        // In real implementation, this would be an actual API call
        
        $mockData = [
            'reference' => $reference,
            'amount' => rand(0, 100000), // Random amount for testing
            'status' => 'success',
            'timestamp' => now()->toISOString(),
        ];
        
        // Simulate 80% success rate
        return rand(1, 100) <= 80 ? $mockData : null;
    }

    /**
     * Get reconciliation statistics
     */
    public function getReconciliationStats(): array
    {
        $today = today();
        
        return [
            'total_payments' => Payment::whereDate('created_at', $today)->count(),
            'confirmed_payments' => Payment::whereDate('created_at', $today)
                ->where('status', 'confirmed')->count(),
            'pending_payments' => Payment::whereDate('created_at', $today)
                ->where('status', 'pending')->count(),
            'mismatch_payments' => Payment::whereDate('created_at', $today)
                ->where('status', 'mismatch')->count(),
            'total_amount' => Payment::whereDate('created_at', $today)
                ->where('status', 'confirmed')->sum('amount_received'),
            'mismatch_amount' => PaymentMismatch::whereDate('created_at', $today)
                ->sum('amount_difference'),
            'auto_frozen_count' => PaymentMismatch::whereDate('created_at', $today)
                ->where('auto_frozen', true)->count(),
        ];
    }
}
