<?php

namespace App\Jobs;

use App\Services\SalaryDeductionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSalaryDeductions implements ShouldQueue
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
        Log::info('Processing salary deductions job started');

        try {
            $salaryService = app(SalaryDeductionService::class);
            $results = $salaryService->processPendingDeductions();

            Log::info('Salary deductions processed successfully', [
                'total_processed' => $results['total_processed'],
                'total_amount' => $results['total_amount'],
                'successful_count' => count($results['successful']),
                'failed_count' => count($results['failed'])
            ]);

            // Log successful deductions
            foreach ($results['successful'] as $success) {
                Log::info('Salary deduction processed', [
                    'deduction_id' => $success['deduction_id'],
                    'user_name' => $success['user_name'],
                    'amount' => $success['amount']
                ]);
            }

            // Log failed deductions
            foreach ($results['failed'] as $failure) {
                Log::error('Salary deduction failed', [
                    'deduction_id' => $failure['deduction_id'],
                    'user_name' => $failure['user_name'],
                    'amount' => $failure['amount'],
                    'error' => $failure['error']
                ]);
            }

            $this->generateProcessingReport($results);

        } catch (\Exception $e) {
            Log::error('ProcessSalaryDeductions job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Generate a processing report
     */
    private function generateProcessingReport(array $results): void
    {
        $report = [
            'timestamp' => now()->toDateTimeString(),
            'summary' => [
                'total_processed' => $results['total_processed'],
                'total_amount' => $results['total_amount'],
                'successful_count' => count($results['successful']),
                'failed_count' => count($results['failed']),
                'success_rate' => $results['total_processed'] > 0 ? 
                    round((count($results['successful']) / $results['total_processed']) * 100, 2) : 0
            ],
            'successful_deductions' => $results['successful'],
            'failed_deductions' => $results['failed']
        ];

        Log::info('Salary deductions processing report', $report);

        // In a production system, you might want to:
        // 1. Email the report to administrators
        // 2. Store in a separate reporting table
        // 3. Send to external systems (e.g., payroll system)
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessSalaryDeductions job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
