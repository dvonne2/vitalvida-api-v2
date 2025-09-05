<?php

namespace App\Services;

use App\Models\User;
use App\Models\BonusLog;
use App\Models\SalaryDeduction;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Services\TaxCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PayrollIntegrationService
{
    public function __construct(
        private TaxCalculationService $taxService
    ) {}

    /**
     * Process monthly payroll with bonuses and deductions
     */
    public function processMonthlyPayroll(Carbon $month): array
    {
        Log::info('Starting monthly payroll processing', ['month' => $month->format('Y-m')]);

        try {
            DB::beginTransaction();

            // Get all active users
            $users = User::where('is_active', true)->get();
            $payrollResults = [];

            foreach ($users as $user) {
                $payrollData = $this->calculateUserPayroll($user, $month);
                $payslip = $this->generatePayslip($user, $payrollData, $month);
                
                $payrollResults[$user->id] = [
                    'user' => $user,
                    'payroll_data' => $payrollData,
                    'payslip' => $payslip
                ];
            }

            // Create payroll batch record
            $payrollBatch = Payroll::create([
                'month' => $month,
                'total_users' => count($payrollResults),
                'total_gross_pay' => collect($payrollResults)->sum('payroll_data.gross_pay'),
                'total_deductions' => collect($payrollResults)->sum('payroll_data.total_deductions'),
                'total_net_pay' => collect($payrollResults)->sum('payroll_data.net_pay'),
                'status' => 'calculated',
                'processed_by' => auth()->id(),
                'processed_at' => now()
            ]);

            DB::commit();

            Log::info('Monthly payroll processing completed', [
                'month' => $month->format('Y-m'),
                'payroll_batch_id' => $payrollBatch->id,
                'users_processed' => count($payrollResults)
            ]);

            return [
                'success' => true,
                'payroll_batch' => $payrollBatch,
                'user_results' => $payrollResults,
                'summary' => $this->generatePayrollSummary($payrollResults)
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Payroll processing failed', [
                'month' => $month->format('Y-m'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Calculate complete payroll for a user
     */
    public function calculateUserPayroll(User $user, Carbon $month): array
    {
        // Base salary calculation
        $baseSalary = $user->base_salary ?? 0;
        $workingDays = $this->getWorkingDaysInMonth($month);
        $userWorkingDays = $this->getUserWorkingDays($user, $month);

        // Prorate salary if user didn't work full month
        $proratedSalary = ($userWorkingDays / $workingDays) * $baseSalary;

        // Get approved bonuses for the month
        $bonuses = BonusLog::where('user_id', $user->id)
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->where('status', 'approved')
            ->get();

        $totalBonuses = $bonuses->sum('amount');

        // Get salary deductions
        $deductions = SalaryDeduction::where('user_id', $user->id)
            ->where('effective_date', '<=', $month->endOfMonth())
            ->whereIn('status', ['approved', 'pending_payroll'])
            ->get();

        $totalDeductions = $deductions->sum('deduction_amount');

        // Calculate gross pay
        $grossPay = $proratedSalary + $totalBonuses;

        // Calculate taxes and statutory deductions
        $taxCalculations = $this->taxService->calculateTaxes($grossPay, $user);

        // Calculate total deductions
        $totalAllDeductions = $totalDeductions + $taxCalculations['total_tax'];

        // Calculate net pay
        $netPay = $grossPay - $totalAllDeductions;

        return [
            'user_id' => $user->id,
            'month' => $month,
            'base_salary' => $baseSalary,
            'prorated_salary' => $proratedSalary,
            'working_days' => $workingDays,
            'user_working_days' => $userWorkingDays,
            'bonuses' => [
                'details' => $bonuses->map(function($bonus) {
                    return [
                        'type' => $bonus->bonus_type,
                        'description' => $bonus->description,
                        'amount' => $bonus->amount
                    ];
                }),
                'total' => $totalBonuses
            ],
            'deductions' => [
                'salary_deductions' => $deductions->map(function($deduction) {
                    return [
                        'reason' => $deduction->reason,
                        'amount' => $deduction->deduction_amount,
                        'type' => $deduction->deduction_type
                    ];
                }),
                'tax_deductions' => $taxCalculations,
                'total_salary_deductions' => $totalDeductions,
                'total_tax_deductions' => $taxCalculations['total_tax'],
                'total_deductions' => $totalAllDeductions
            ],
            'gross_pay' => $grossPay,
            'net_pay' => $netPay,
            'payroll_date' => now()
        ];
    }

    /**
     * Generate payslip for user
     */
    public function generatePayslip(User $user, array $payrollData, Carbon $month): Payslip
    {
        return Payslip::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'user_department' => $user->department ?? 'General',
            'pay_period_month' => $month,
            'base_salary' => $payrollData['base_salary'],
            'prorated_salary' => $payrollData['prorated_salary'],
            'total_bonuses' => $payrollData['bonuses']['total'],
            'bonus_details' => json_encode($payrollData['bonuses']['details']),
            'total_deductions' => $payrollData['deductions']['total_deductions'],
            'deduction_details' => json_encode($payrollData['deductions']),
            'gross_pay' => $payrollData['gross_pay'],
            'net_pay' => $payrollData['net_pay'],
            'working_days' => $payrollData['working_days'],
            'user_working_days' => $payrollData['user_working_days'],
            'generated_by' => auth()->id(),
            'generated_at' => now(),
            'payslip_number' => $this->generatePayslipNumber($user, $month)
        ]);
    }

    /**
     * Get working days in a month
     */
    private function getWorkingDaysInMonth(Carbon $month): int
    {
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();
        $workingDays = 0;

        for ($date = $startOfMonth; $date <= $endOfMonth; $date->addDay()) {
            if ($date->isWeekday()) { // Monday to Friday
                $workingDays++;
            }
        }

        return $workingDays;
    }

    /**
     * Get user working days in a month
     */
    private function getUserWorkingDays(User $user, Carbon $month): int
    {
        // This would integrate with attendance system
        // For now, assume full month unless user started mid-month
        $userStartDate = $user->created_at;
        $monthStart = $month->copy()->startOfMonth();

        if ($userStartDate > $monthStart) {
            return $this->getWorkingDaysInMonth($month) - $userStartDate->diffInWeekdays($monthStart);
        }

        return $this->getWorkingDaysInMonth($month);
    }

    /**
     * Generate payslip number
     */
    private function generatePayslipNumber(User $user, Carbon $month): string
    {
        return sprintf(
            'PS-%s-%s-%04d',
            $month->format('Ym'),
            strtoupper(substr($user->name, 0, 3)),
            $user->id
        );
    }

    /**
     * Generate payroll summary
     */
    private function generatePayrollSummary(array $payrollResults): array
    {
        return [
            'total_users' => count($payrollResults),
            'total_gross_pay' => collect($payrollResults)->sum('payroll_data.gross_pay'),
            'total_base_salary' => collect($payrollResults)->sum('payroll_data.prorated_salary'),
            'total_bonuses' => collect($payrollResults)->sum('payroll_data.bonuses.total'),
            'total_deductions' => collect($payrollResults)->sum('payroll_data.deductions.total_deductions'),
            'total_net_pay' => collect($payrollResults)->sum('payroll_data.net_pay'),
            'average_net_pay' => collect($payrollResults)->avg('payroll_data.net_pay'),
            'bonus_statistics' => [
                'users_with_bonuses' => collect($payrollResults)->filter(function($result) {
                    return $result['payroll_data']['bonuses']['total'] > 0;
                })->count(),
                'average_bonus' => collect($payrollResults)->where('payroll_data.bonuses.total', '>', 0)->avg('payroll_data.bonuses.total')
            ]
        ];
    }

    /**
     * Process bonus approval for payroll integration
     */
    public function processBonusApproval(BonusLog $bonus, User $approver): array
    {
        try {
            DB::beginTransaction();

            // Update bonus status
            $bonus->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now()
            ]);

            // Calculate tax impact
            $taxImpact = $this->taxService->calculateBonusTaxImpact($bonus->amount, $bonus->user);

            // Update bonus with tax information
            $bonus->update([
                'tax_impact' => json_encode($taxImpact),
                'net_bonus_after_tax' => $taxImpact['bonus_after_tax']
            ]);

            DB::commit();

            Log::info('Bonus approved and integrated with payroll', [
                'bonus_id' => $bonus->id,
                'user_id' => $bonus->user_id,
                'amount' => $bonus->amount,
                'approved_by' => $approver->id,
                'tax_impact' => $taxImpact
            ]);

            return [
                'success' => true,
                'bonus' => $bonus,
                'tax_impact' => $taxImpact,
                'message' => 'Bonus approved and integrated with payroll system'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to process bonus approval', [
                'bonus_id' => $bonus->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get payroll analytics for management
     */
    public function getPayrollAnalytics(Carbon $startDate, Carbon $endDate): array
    {
        $payrolls = Payroll::whereBetween('month', [$startDate, $endDate])->get();
        $payslips = Payslip::whereBetween('pay_period_month', [$startDate, $endDate])->get();

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'months' => $startDate->diffInMonths($endDate) + 1
            ],
            'overview' => [
                'total_payroll_batches' => $payrolls->count(),
                'total_payslips_generated' => $payslips->count(),
                'total_gross_pay' => $payslips->sum('gross_pay'),
                'total_net_pay' => $payslips->sum('net_pay'),
                'total_bonuses_paid' => $payslips->sum('total_bonuses'),
                'total_deductions' => $payslips->sum('total_deductions')
            ],
            'monthly_trends' => $this->getMonthlyPayrollTrends($startDate, $endDate),
            'bonus_analysis' => $this->getBonusAnalysis($payslips),
            'department_analysis' => $this->getDepartmentAnalysis($payslips),
            'tax_analysis' => $this->getTaxAnalysis($payslips)
        ];
    }

    /**
     * Get monthly payroll trends
     */
    private function getMonthlyPayrollTrends(Carbon $startDate, Carbon $endDate): array
    {
        $trends = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $monthStart = $current->copy()->startOfMonth();
            $monthEnd = $current->copy()->endOfMonth();

            $monthlyPayslips = Payslip::whereBetween('pay_period_month', [$monthStart, $monthEnd])->get();

            $trends[] = [
                'month' => $monthStart->format('M Y'),
                'total_gross_pay' => $monthlyPayslips->sum('gross_pay'),
                'total_net_pay' => $monthlyPayslips->sum('net_pay'),
                'total_bonuses' => $monthlyPayslips->sum('total_bonuses'),
                'total_deductions' => $monthlyPayslips->sum('total_deductions'),
                'payslip_count' => $monthlyPayslips->count()
            ];

            $current->addMonth();
        }

        return $trends;
    }

    /**
     * Get bonus analysis
     */
    private function getBonusAnalysis($payslips): array
    {
        $usersWithBonuses = $payslips->where('total_bonuses', '>', 0);
        
        return [
            'users_with_bonuses' => $usersWithBonuses->count(),
            'total_bonus_amount' => $payslips->sum('total_bonuses'),
            'average_bonus_per_user' => $usersWithBonuses->count() > 0 ? $usersWithBonuses->avg('total_bonuses') : 0,
            'bonus_distribution' => [
                'high_bonuses' => $usersWithBonuses->where('total_bonuses', '>', 50000)->count(),
                'medium_bonuses' => $usersWithBonuses->whereBetween('total_bonuses', [15000, 50000])->count(),
                'low_bonuses' => $usersWithBonuses->where('total_bonuses', '<', 15000)->count()
            ]
        ];
    }

    /**
     * Get department analysis
     */
    private function getDepartmentAnalysis($payslips): array
    {
        return $payslips->groupBy('user_department')->map(function($departmentPayslips) {
            return [
                'total_gross_pay' => $departmentPayslips->sum('gross_pay'),
                'total_net_pay' => $departmentPayslips->sum('net_pay'),
                'total_bonuses' => $departmentPayslips->sum('total_bonuses'),
                'total_deductions' => $departmentPayslips->sum('total_deductions'),
                'user_count' => $departmentPayslips->pluck('user_id')->unique()->count(),
                'average_net_pay' => $departmentPayslips->avg('net_pay')
            ];
        })->toArray();
    }

    /**
     * Get tax analysis
     */
    private function getTaxAnalysis($payslips): array
    {
        $totalTaxDeductions = 0;
        $taxBreakdown = [];

        foreach ($payslips as $payslip) {
            $deductionDetails = json_decode($payslip->deduction_details, true);
            if (isset($deductionDetails['tax_deductions'])) {
                $taxDeductions = $deductionDetails['tax_deductions'];
                $totalTaxDeductions += $taxDeductions['total_tax'] ?? 0;
                
                // Aggregate tax breakdown
                if (isset($taxDeductions['statutory_deductions'])) {
                    foreach ($taxDeductions['statutory_deductions'] as $type => $amount) {
                        if ($type !== 'total') {
                            $taxBreakdown[$type] = ($taxBreakdown[$type] ?? 0) + $amount;
                        }
                    }
                }
            }
        }

        return [
            'total_tax_deductions' => $totalTaxDeductions,
            'average_tax_per_user' => $payslips->count() > 0 ? $totalTaxDeductions / $payslips->count() : 0,
            'tax_breakdown' => $taxBreakdown,
            'tax_efficiency' => $payslips->sum('gross_pay') > 0 ? 
                ($totalTaxDeductions / $payslips->sum('gross_pay')) * 100 : 0
        ];
    }

    /**
     * Validate payroll data before processing
     */
    public function validatePayrollData(Carbon $month): array
    {
        $validationResults = [
            'is_valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        // Check for duplicate payroll processing
        $existingPayroll = Payroll::whereYear('month', $month->year)
            ->whereMonth('month', $month->month)
            ->first();

        if ($existingPayroll) {
            $validationResults['errors'][] = "Payroll already processed for {$month->format('M Y')}";
            $validationResults['is_valid'] = false;
        }

        // Check for pending bonus approvals
        $pendingBonuses = BonusLog::whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->where('status', 'pending')
            ->count();

        if ($pendingBonuses > 0) {
            $validationResults['warnings'][] = "{$pendingBonuses} bonus(es) still pending approval";
        }

        // Check for active users without base salary
        $usersWithoutSalary = User::where('is_active', true)
            ->whereNull('base_salary')
            ->count();

        if ($usersWithoutSalary > 0) {
            $validationResults['warnings'][] = "{$usersWithoutSalary} active user(s) without base salary";
        }

        return $validationResults;
    }

    /**
     * Get payroll processing status
     */
    public function getPayrollStatus(Carbon $month): array
    {
        $payroll = Payroll::whereYear('month', $month->year)
            ->whereMonth('month', $month->month)
            ->first();

        if (!$payroll) {
            return [
                'status' => 'not_processed',
                'message' => 'Payroll not yet processed for this month'
            ];
        }

        $payslips = Payslip::whereYear('pay_period_month', $month->year)
            ->whereMonth('pay_period_month', $month->month)
            ->get();

        return [
            'status' => $payroll->status,
            'payroll_id' => $payroll->id,
            'processed_at' => $payroll->processed_at,
            'processed_by' => $payroll->processed_by,
            'summary' => [
                'total_users' => $payroll->total_users,
                'total_gross_pay' => $payroll->total_gross_pay,
                'total_net_pay' => $payroll->total_net_pay,
                'payslips_generated' => $payslips->count()
            ]
        ];
    }
} 