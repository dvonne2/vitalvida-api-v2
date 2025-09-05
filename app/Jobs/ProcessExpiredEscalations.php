<?php

namespace App\Jobs;

use App\Models\EscalationRequest;
use App\Services\SalaryDeductionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessExpiredEscalations implements ShouldQueue
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
        Log::info('Processing expired escalations job started');

        $expiredEscalations = EscalationRequest::with(['thresholdViolation', 'creator'])
            ->where('status', 'pending_approval')
            ->where('expires_at', '<', now())
            ->get();

        $processedCount = 0;
        $failedCount = 0;
        $totalAmount = 0;

        foreach ($expiredEscalations as $escalation) {
            try {
                $this->processExpiredEscalation($escalation);
                $processedCount++;
                $totalAmount += $escalation->overage_amount;

                Log::info('Expired escalation processed', [
                    'escalation_id' => $escalation->id,
                    'amount' => $escalation->amount_requested,
                    'overage' => $escalation->overage_amount,
                    'created_by' => $escalation->creator->name ?? 'Unknown'
                ]);

            } catch (\Exception $e) {
                $failedCount++;
                Log::error('Failed to process expired escalation', [
                    'escalation_id' => $escalation->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::info('Processing expired escalations job completed', [
            'total_found' => $expiredEscalations->count(),
            'processed' => $processedCount,
            'failed' => $failedCount,
            'total_amount' => $totalAmount
        ]);
    }

    /**
     * Process a single expired escalation
     */
    private function processExpiredEscalation(EscalationRequest $escalation): void
    {
        // Update escalation status to expired
        $escalation->update([
            'status' => 'expired',
            'final_decision_at' => now(),
            'final_outcome' => 'expired'
        ]);

        // Update the related threshold violation
        $escalation->thresholdViolation()->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => 'Escalation expired without approval'
        ]);

        // Create salary deduction for expired escalation
        $salaryService = app(SalaryDeductionService::class);
        $salaryService->createDeductionForExpiredEscalation($escalation);

        Log::info('Expired escalation processed successfully', [
            'escalation_id' => $escalation->id,
            'amount' => $escalation->amount_requested,
            'overage' => $escalation->overage_amount,
            'expires_at' => $escalation->expires_at,
            'created_by' => $escalation->creator->name ?? 'Unknown'
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessExpiredEscalations job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
