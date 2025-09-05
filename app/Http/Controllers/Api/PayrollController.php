<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\User;
use App\Services\PayrollIntegrationService;
use App\Services\TaxCalculationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PayrollController extends Controller
{
    protected $payrollService;
    protected $taxService;

    public function __construct(PayrollIntegrationService $payrollService, TaxCalculationService $taxService)
    {
        $this->payrollService = $payrollService;
        $this->taxService = $taxService;
    }

    /**
     * Get all payroll records with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'employee_id' => 'nullable|exists:users,id',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'status' => 'nullable|in:draft,processed,paid,cancelled',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1'
        ]);

        $query = Payroll::with(['employee', 'processor', 'approver'])
            ->orderBy('created_at', 'desc');

        // Role-based filtering
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            $query->where('employee_id', $user->id);
        }

        // Apply filters
        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->period_start && $request->period_end) {
            $query->forPeriod($request->period_start, $request->period_end);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $payrolls = $query->paginate($request->limit ?? 25);

        return response()->json([
            'success' => true,
            'data' => $payrolls->items(),
            'pagination' => [
                'current_page' => $payrolls->currentPage(),
                'per_page' => $payrolls->perPage(),
                'total' => $payrolls->total(),
                'last_page' => $payrolls->lastPage()
            ],
            'summary' => [
                'total_gross_pay' => $payrolls->sum('gross_pay'),
                'total_net_pay' => $payrolls->sum('net_pay'),
                'total_deductions' => $payrolls->sum('total_deductions'),
                'pending_count' => Payroll::where('status', Payroll::STATUS_DRAFT)->count(),
                'processed_count' => Payroll::where('status', Payroll::STATUS_PROCESSED)->count(),
                'paid_count' => Payroll::where('status', Payroll::STATUS_PAID)->count()
            ]
        ]);
    }

    /**
     * Process monthly payroll
     */
    public function processPayroll(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
            'dry_run' => 'boolean'
        ]);

        try {
            $month = Carbon::createFromFormat('Y-m', $validated['month']);
            
            // Validate payroll data
            $validation = $this->payrollService->validatePayrollData($month);
            
            if (!$validation['is_valid']) {
                return response()->json([
                    'error' => 'Payroll validation failed',
                    'validation_errors' => $validation['errors'],
                    'warnings' => $validation['warnings']
                ], 422);
            }

            // If dry run, return validation results
            if ($validated['dry_run'] ?? false) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payroll validation completed',
                    'validation' => $validation,
                    'dry_run' => true
                ]);
            }

            // Process payroll
            $results = $this->payrollService->processMonthlyPayroll($month);

            Log::info('Monthly payroll processing initiated', [
                'month' => $month->format('Y-m'),
                'initiated_by' => $user->id,
                'users_processed' => $results['summary']['total_users'] ?? 0
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Monthly payroll processed successfully',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Monthly payroll processing failed', [
                'month' => $validated['month'] ?? 'unknown',
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to process monthly payroll',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payroll analytics
     */
    public function getPayrollAnalytics(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date'
        ]);

        try {
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);

            $analytics = $this->payrollService->getPayrollAnalytics($startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get payroll analytics', [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get payroll analytics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payroll status for a specific month
     */
    public function getPayrollStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'month' => 'required|date_format:Y-m'
        ]);

        try {
            $month = Carbon::createFromFormat('Y-m', $validated['month']);
            $status = $this->payrollService->getPayrollStatus($month);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get payroll status', [
                'month' => $validated['month'],
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get payroll status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific payroll details
     */
    public function show(Payroll $payroll, Request $request): JsonResponse
    {
        $user = $request->user();

        // Check permissions
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin']) && $payroll->employee_id !== $user->id) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'payroll' => $payroll->load(['employee', 'processor', 'approver']),
                'detailed_breakdown' => $payroll->getDetailedBreakdown(),
                'can_approve' => $this->canUserApprove($user, $payroll),
                'can_mark_paid' => $this->canUserMarkPaid($user, $payroll)
            ]
        ]);
    }

    /**
     * Generate payslip for employee
     */
    public function generatePayslip(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'employee_id' => 'nullable|exists:users,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start'
        ]);

        // Determine employee
        $employeeId = $request->employee_id ?? $user->id;
        $employee = User::findOrFail($employeeId);

        // Check permissions
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin']) && $employee->id !== $user->id) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $periodStart = Carbon::parse($request->period_start);
            $periodEnd = Carbon::parse($request->period_end);

            $payslip = $this->payrollService->generatePayslip($employee, $periodStart, $periodEnd);

            return response()->json([
                'success' => true,
                'data' => $payslip
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payslip',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get employee payroll history
     */
    public function employeeHistory(User $employee, Request $request): JsonResponse
    {
        $user = $request->user();

        // Check permissions
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin']) && $employee->id !== $user->id) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $request->validate([
            'months' => 'nullable|integer|min:1|max:24',
            'year' => 'nullable|integer|min:2020|max:2030'
        ]);

        $months = $request->months ?? 12;
        $history = $this->payrollService->getEmployeePayrollHistory($employee, $months);

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * Get payroll summary for management
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['CFO', 'CEO', 'GM', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start'
        ]);

        try {
            $periodStart = Carbon::parse($request->period_start);
            $periodEnd = Carbon::parse($request->period_end);

            $summary = $this->payrollService->getPayrollSummary($periodStart, $periodEnd);

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payroll summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve payroll
     */
    public function approve(Payroll $payroll, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canUserApprove($user, $payroll)) {
            return response()->json(['error' => 'Insufficient permissions to approve payroll'], 403);
        }

        $request->validate([
            'notes' => 'nullable|string|max:500'
        ]);

        try {
            $approved = $payroll->approve($user, $request->notes);

            if ($approved) {
                Log::info('Payroll approved', [
                    'payroll_id' => $payroll->id,
                    'approved_by' => $user->id,
                    'employee_id' => $payroll->employee_id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payroll approved successfully',
                    'data' => $payroll->fresh(['employee', 'approver'])
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve payroll'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Payroll approval failed', [
                'payroll_id' => $payroll->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark payroll as paid
     */
    public function markAsPaid(Payroll $payroll, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canUserMarkPaid($user, $payroll)) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $request->validate([
            'payment_reference' => 'required|string|max:100',
            'payment_method' => 'required|string|max:50'
        ]);

        try {
            $marked = $payroll->markAsPaid($request->payment_reference, $request->payment_method);

            if ($marked) {
                Log::info('Payroll marked as paid', [
                    'payroll_id' => $payroll->id,
                    'marked_by' => $user->id,
                    'payment_reference' => $request->payment_reference
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payroll marked as paid successfully',
                    'data' => $payroll->fresh(['employee'])
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark payroll as paid'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Failed to mark payroll as paid', [
                'payroll_id' => $payroll->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark payroll as paid',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tax calculation for employee
     */
    public function taxCalculation(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'employee_id' => 'nullable|exists:users,id',
            'gross_pay' => 'required|numeric|min:0|max:10000000'
        ]);

        $employeeId = $request->employee_id ?? $user->id;
        $employee = User::findOrFail($employeeId);

        // Check permissions
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin']) && $employee->id !== $user->id) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $taxSummary = $this->taxService->getTaxSummary($employee, $request->gross_pay);

            return response()->json([
                'success' => true,
                'data' => $taxSummary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate tax',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tax certificate for employee
     */
    public function taxCertificate(User $employee, Request $request): JsonResponse
    {
        $user = $request->user();

        // Check permissions
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin']) && $employee->id !== $user->id) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $request->validate([
            'year' => 'required|integer|min:2020|max:2030'
        ]);

        try {
            $certificate = $this->taxService->generateTaxCertificate($employee, $request->year);

            return response()->json([
                'success' => true,
                'data' => $certificate
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate tax certificate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current tax rates and thresholds
     */
    public function taxRates(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->taxService->getCurrentTaxRates()
        ]);
    }

    /**
     * Calculate enhanced tax impact for bonus payments
     */
    public function calculateBonusTaxImpact(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['CFO', 'CEO', 'GM', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $request->validate([
            'employee_id' => 'required|exists:users,id',
            'bonus_amount' => 'required|numeric|min:0'
        ]);

        try {
            $employee = User::findOrFail($request->employee_id);
            $bonusAmount = $request->bonus_amount;

            $taxImpact = $this->taxService->calculateBonusTaxImpact($bonusAmount, $employee);

            return response()->json([
                'success' => true,
                'data' => [
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'base_salary' => $employee->base_salary
                    ],
                    'bonus_tax_impact' => $taxImpact,
                    'summary' => [
                        'bonus_amount' => $bonusAmount,
                        'net_bonus_after_tax' => $taxImpact['bonus_after_tax'],
                        'effective_tax_rate' => $taxImpact['effective_tax_rate_on_bonus'],
                        'tax_impact_percentage' => $taxImpact['effective_tax_rate_on_bonus']
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bonus tax impact calculation failed', [
                'user_id' => $user->id,
                'employee_id' => $request->employee_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bonus tax impact calculation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comprehensive tax calculation for employee
     */
    public function getComprehensiveTaxCalculation(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'employee_id' => 'nullable|exists:users,id',
            'gross_pay' => 'required|numeric|min:0'
        ]);

        // Determine employee
        $employeeId = $request->employee_id ?? $user->id;
        $employee = User::findOrFail($employeeId);

        // Check permissions
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin']) && $employee->id !== $user->id) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $grossPay = $request->gross_pay;
            $taxCalculation = $this->taxService->calculateTaxes($grossPay, $employee);

            return response()->json([
                'success' => true,
                'data' => [
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'base_salary' => $employee->base_salary
                    ],
                    'tax_calculation' => $taxCalculation,
                    'summary' => [
                        'gross_pay' => $grossPay,
                        'total_tax' => $taxCalculation['total_tax'],
                        'net_pay' => $grossPay - $taxCalculation['total_tax'],
                        'effective_tax_rate' => $taxCalculation['tax_rate']
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Comprehensive tax calculation failed', [
                'user_id' => $user->id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Tax calculation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payroll history for all employees
     */
    public function getPayrollHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $request->validate([
            'months' => 'nullable|integer|min:1|max:24',
            'year' => 'nullable|integer|min:2020|max:2030',
            'employee_id' => 'nullable|exists:users,id'
        ]);

        $months = $request->months ?? 12;
        $year = $request->year ?? now()->year;
        $employeeId = $request->employee_id;

        try {
            $startDate = Carbon::create($year, 1, 1);
            $endDate = $startDate->copy()->addMonths($months)->subDay();

            $query = Payroll::with(['employee', 'processor', 'approver'])
                ->whereBetween('period_start', [$startDate, $endDate]);

            if ($employeeId) {
                $query->where('employee_id', $employeeId);
            }

            $payrolls = $query->orderBy('period_start', 'desc')->get();

            $summary = [
                'total_payrolls' => $payrolls->count(),
                'total_gross_pay' => $payrolls->sum('gross_pay'),
                'total_net_pay' => $payrolls->sum('net_pay'),
                'total_bonuses' => $payrolls->sum('total_bonuses'),
                'total_deductions' => $payrolls->sum('total_deductions'),
                'average_gross_pay' => $payrolls->avg('gross_pay'),
                'average_net_pay' => $payrolls->avg('net_pay')
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'payrolls' => $payrolls,
                    'summary' => $summary,
                    'period' => [
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                        'months' => $months
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get payroll history', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get payroll history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payslips for all employees
     */
    public function getPayslips(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $request->validate([
            'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'employee_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:draft,processed,paid,cancelled'
        ]);

        try {
            $query = Payroll::with(['employee']);

            if ($request->year) {
                $query->whereYear('period_start', $request->year);
            }

            if ($request->month) {
                $query->whereMonth('period_start', $request->month);
            }

            if ($request->employee_id) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            $payslips = $query->orderBy('period_start', 'desc')->get();

            $payslipsData = $payslips->map(function($payroll) {
                return [
                    'id' => $payroll->id,
                    'payslip_number' => $payroll->payslip_number ?? 'PS-' . str_pad($payroll->id, 6, '0', STR_PAD_LEFT),
                    'employee' => [
                        'id' => $payroll->employee->id,
                        'name' => $payroll->employee->name,
                        'email' => $payroll->employee->email
                    ],
                    'period' => [
                        'start' => $payroll->period_start->format('Y-m-d'),
                        'end' => $payroll->period_end->format('Y-m-d'),
                        'month' => $payroll->period_start->format('M Y')
                    ],
                    'earnings' => [
                        'gross_pay' => $payroll->gross_pay,
                        'net_pay' => $payroll->net_pay,
                        'total_bonuses' => $payroll->total_bonuses ?? 0,
                        'total_deductions' => $payroll->total_deductions
                    ],
                    'working_days' => [
                        'total' => $payroll->working_days ?? 22,
                        'employee' => $payroll->employee_working_days ?? 22
                    ],
                    'status' => $payroll->status,
                    'created_at' => $payroll->created_at->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'payslips' => $payslipsData,
                    'summary' => [
                        'total_payslips' => $payslips->count(),
                        'total_gross_pay' => $payslips->sum('gross_pay'),
                        'total_net_pay' => $payslips->sum('net_pay'),
                        'total_bonuses' => $payslips->sum('total_bonuses'),
                        'total_deductions' => $payslips->sum('total_deductions')
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get payslips', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get payslips',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payroll analytics for management
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['CFO', 'CEO', 'GM', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $request->validate([
            'period' => 'nullable|in:month,quarter,year',
            'year' => 'nullable|integer|min:2020|max:2030'
        ]);

        $period = $request->period ?? 'year';
        $year = $request->year ?? now()->year;

        $startDate = Carbon::create($year, 1, 1);
        $endDate = match($period) {
            'month' => $startDate->copy()->addMonth()->subDay(),
            'quarter' => $startDate->copy()->addQuarter()->subDay(),
            'year' => $startDate->copy()->addYear()->subDay(),
            default => $startDate->copy()->addYear()->subDay()
        };

        $payrolls = Payroll::with('employee')
            ->whereBetween('period_start', [$startDate, $endDate])
            ->get();

        $analytics = [
            'period' => [
                'type' => $period,
                'year' => $year,
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString()
            ],
            'summary' => [
                'total_payrolls' => $payrolls->count(),
                'total_gross_pay' => $payrolls->sum('gross_pay'),
                'total_net_pay' => $payrolls->sum('net_pay'),
                'total_deductions' => $payrolls->sum('total_deductions'),
                'total_bonuses' => $payrolls->sum('total_bonuses'),
                'total_employer_costs' => $payrolls->sum('total_employer_contributions'),
                'average_gross_pay' => $payrolls->avg('gross_pay'),
                'average_net_pay' => $payrolls->avg('net_pay')
            ],
            'by_role' => $payrolls->groupBy('employee.role')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'total_gross_pay' => $group->sum('gross_pay'),
                    'total_net_pay' => $group->sum('net_pay'),
                    'average_gross_pay' => $group->avg('gross_pay')
                ];
            }),
            'monthly_trends' => $this->getMonthlyPayrollTrends($startDate, $endDate),
            'cost_breakdown' => [
                'base_salaries' => $payrolls->sum('base_salary'),
                'bonuses' => $payrolls->sum('total_bonuses'),
                'deductions' => $payrolls->sum('total_deductions'),
                'taxes' => $payrolls->sum('paye_tax'),
                'employer_contributions' => $payrolls->sum('total_employer_contributions')
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Employee self-service dashboard
     */
    public function selfServiceDashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get current month payroll
        $currentMonth = now()->startOfMonth();
        $currentPayroll = Payroll::forEmployee($user->id)
            ->where('period_start', '>=', $currentMonth)
            ->first();

        // Get last 6 months history
        $history = $this->payrollService->getEmployeePayrollHistory($user, 6);

        // Get YTD tax liability
        $ytdTax = $this->taxService->calculateYTDTaxLiability($user, now());

        // Get pending bonuses
        $pendingBonuses = $user->bonusLogs()->pending()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'current_payroll' => $currentPayroll,
                'payroll_history' => $history,
                'ytd_tax_liability' => $ytdTax,
                'pending_bonuses' => $pendingBonuses,
                'quick_stats' => [
                    'ytd_gross_earnings' => $history['summary']['total_gross_earned'] ?? 0,
                    'ytd_net_earnings' => $history['summary']['total_net_earned'] ?? 0,
                    'ytd_bonuses' => $history['summary']['total_bonuses_earned'] ?? 0,
                    'ytd_deductions' => $history['summary']['total_deductions'] ?? 0
                ]
            ]
        ]);
    }

    /**
     * Check if user can approve payroll
     */
    private function canUserApprove(User $user, Payroll $payroll): bool
    {
        if (!in_array($user->role, ['CFO', 'CEO', 'GM', 'superadmin'])) {
            return false;
        }

        if ($payroll->shouldRequireApproval() && !in_array($user->role, ['CEO', 'superadmin'])) {
            return false;
        }

        return true;
    }

    /**
     * Check if user can mark payroll as paid
     */
    private function canUserMarkPaid(User $user, Payroll $payroll): bool
    {
        return in_array($user->role, ['CFO', 'CEO', 'superadmin']) && $payroll->isProcessed();
    }

    /**
     * Get monthly payroll trends
     */
    private function getMonthlyPayrollTrends(Carbon $startDate, Carbon $endDate): array
    {
        $months = [];
        $current = $startDate->copy()->startOfMonth();
        
        while ($current <= $endDate) {
            $monthStart = $current->copy();
            $monthEnd = $current->copy()->endOfMonth();
            
            $monthlyPayrolls = Payroll::whereBetween('period_start', [$monthStart, $monthEnd])->get();
            
            $months[] = [
                'month' => $monthStart->format('M Y'),
                'total_payrolls' => $monthlyPayrolls->count(),
                'total_gross_pay' => $monthlyPayrolls->sum('gross_pay'),
                'total_net_pay' => $monthlyPayrolls->sum('net_pay'),
                'total_deductions' => $monthlyPayrolls->sum('total_deductions')
            ];
            
            $current->addMonth();
        }
        
        return $months;
    }
} 