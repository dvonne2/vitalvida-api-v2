<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\PayrollIntegrationService;
use App\Notifications\PayslipGenerated;

class ProcessMonthlyPayroll implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private \Carbon\Carbon $month
    ) {}

    public function handle(PayrollIntegrationService $payrollService): void
    {
        try {
            Log::info('Starting automated payroll processing', [
                'month' => $this->month->format('Y-m')
            ]);

            // Process payroll for the month
            $results = $payrollService->processMonthlyPayroll($this->month);

            // Generate and send payslips
            $this->distributePayslips($results);

            Log::info('Automated payroll processing completed', [
                'month' => $this->month->format('Y-m'),
                'employees_processed' => $results['summary']['total_employees'],
                'total_net_pay' => $results['summary']['total_net_pay']
            ]);
        } catch (\Exception $e) {
            Log::error('Automated payroll processing failed', [
                'month' => $this->month->format('Y-m'),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function distributePayslips(array $results): void
    {
        foreach ($results['employee_results'] as $employeeResult) {
            $employee = $employeeResult['employee'];
            $payslip = $employeeResult['payslip'];

            // Send payslip notification
            if ($employee) {
                $employee->notify(new PayslipGenerated($payslip));
            }
        }
    }
} 