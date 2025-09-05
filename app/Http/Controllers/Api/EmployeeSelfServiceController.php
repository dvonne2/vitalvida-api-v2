<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\BonusLog;
use App\Models\Payroll;
use App\Models\SalaryDeduction;
use App\Services\TaxCalculationService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EmployeeSelfServiceController extends Controller
{
    public function __construct(
        private TaxCalculationService $taxService
    ) {}

    /**
     * Get employee's bonus and salary summary
     */
    public function getMySalarySummary(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $currentMonth = now();
        $lastPayroll = Payroll::where('employee_id', $user->id)
            ->orderBy('period_start', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'employee_info' => [
                    'name' => $user->name,
                    'employee_id' => $user->employee_id ?? $user->id,
                    'role' => $user->role,
                    'department' => $user->department ?? 'General',
                    'base_salary' => $user->base_salary ?? 0,
                    'hired_date' => $user->created_at->format('M j, Y')
                ],
                'current_month_summary' => $this->getCurrentMonthSummary($user, $currentMonth),
                'last_payroll' => $lastPayroll ? [
                    'month' => $lastPayroll->period_start->format('M Y'),
                    'gross_pay' => $lastPayroll->gross_pay,
                    'net_pay' => $lastPayroll->net_pay,
                    'total_bonuses' => $lastPayroll->total_bonuses ?? 0,
                    'total_deductions' => $lastPayroll->total_deductions
                ] : null,
                'ytd_summary' => $this->getYearToDateSummary($user, $currentMonth->year)
            ]
        ]);
    }

    /**
     * Get employee's bonus history
     */
    public function getMyBonusHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
            'status' => 'nullable|in:pending,approved,paid,rejected'
        ]);

        $user = $request->user();
        
        $query = BonusLog::where('user_id', $user->id);
        
        if ($validated['year'] ?? false) {
            $query->whereYear('created_at', $validated['year']);
        }
        
        if ($validated['status'] ?? false) {
            $query->where('status', $validated['status']);
        }
        
        $bonuses = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'bonuses' => $bonuses->groupBy(function($bonus) {
                    return $bonus->created_at->format('Y-m');
                })->map(function($monthBonuses, $month) {
                    return [
                        'month' => $month,
                        'total_amount' => $monthBonuses->sum('amount'),
                        'bonus_count' => $monthBonuses->count(),
                        'bonuses' => $monthBonuses->map(function($bonus) {
                            return [
                                'type' => $bonus->bonus_type,
                                'description' => $bonus->description,
                                'amount' => $bonus->amount,
                                'status' => $bonus->status,
                                'earned_month' => $bonus->created_at->format('M Y'),
                                'approved_at' => $bonus->approved_at?->format('M j, Y')
                            ];
                        })
                    ];
                }),
                'summary' => [
                    'total_bonuses_earned' => $bonuses->where('status', 'paid')->sum('amount'),
                    'total_bonuses_pending' => $bonuses->where('status', 'approved')->sum('amount'),
                    'total_bonuses_rejected' => $bonuses->where('status', 'rejected')->sum('amount'),
                    'total_bonuses_calculated' => $bonuses->where('status', 'pending')->sum('amount')
                ]
            ]
        ]);
    }

    /**
     * Get employee's payroll history
     */
    public function getMyPayslips(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'nullable|integer|min:1|max:12'
        ]);

        $user = $request->user();
        
        $query = Payroll::where('employee_id', $user->id);
        
        if ($validated['year'] ?? false) {
            $query->whereYear('period_start', $validated['year']);
        }
        
        if ($validated['month'] ?? false) {
            $query->whereMonth('period_start', $validated['month']);
        }
        
        $payrolls = $query->orderBy('period_start', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'payslips' => $payrolls->map(function($payroll) {
                    return [
                        'id' => $payroll->id,
                        'payslip_number' => $payroll->payslip_number ?? 'PS-' . str_pad($payroll->id, 6, '0', STR_PAD_LEFT),
                        'month' => $payroll->period_start->format('M Y'),
                        'gross_pay' => $payroll->gross_pay,
                        'net_pay' => $payroll->net_pay,
                        'total_bonuses' => $payroll->total_bonuses ?? 0,
                        'total_deductions' => $payroll->total_deductions,
                        'working_days' => $payroll->working_days ?? 22,
                        'employee_working_days' => $payroll->employee_working_days ?? 22,
                        'status' => $payroll->status
                    ];
                }),
                'summary' => [
                    'total_gross_earned' => $payrolls->sum('gross_pay'),
                    'total_net_earned' => $payrolls->sum('net_pay'),
                    'total_bonuses_received' => $payrolls->sum('total_bonuses'),
                    'total_deductions' => $payrolls->sum('total_deductions'),
                    'average_monthly_net' => $payrolls->avg('net_pay')
                ]
            ]
        ]);
    }

    /**
     * Get detailed payslip
     */
    public function getPayslipDetails(Request $request, int $payslipId): JsonResponse
    {
        $user = $request->user();
        
        $payroll = Payroll::where('id', $payslipId)
            ->where('employee_id', $user->id)
            ->first();
            
        if (!$payroll) {
            return response()->json(['error' => 'Payslip not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'payslip' => [
                    'id' => $payroll->id,
                    'payslip_number' => $payroll->payslip_number ?? 'PS-' . str_pad($payroll->id, 6, '0', STR_PAD_LEFT),
                    'period' => [
                        'start' => $payroll->period_start->format('M j, Y'),
                        'end' => $payroll->period_end->format('M j, Y'),
                        'month' => $payroll->period_start->format('M Y')
                    ],
                    'earnings' => [
                        'base_salary' => $payroll->base_salary,
                        'prorated_salary' => $payroll->prorated_salary ?? $payroll->base_salary,
                        'total_bonuses' => $payroll->total_bonuses ?? 0,
                        'gross_pay' => $payroll->gross_pay
                    ],
                    'deductions' => [
                        'paye_tax' => $payroll->paye_tax,
                        'pension_contribution' => $payroll->pension_contribution,
                        'nhf_contribution' => $payroll->nhf_contribution,
                        'other_deductions' => $payroll->other_deductions ?? 0,
                        'total_deductions' => $payroll->total_deductions
                    ],
                    'net_pay' => $payroll->net_pay,
                    'working_days' => $payroll->working_days ?? 22,
                    'employee_working_days' => $payroll->employee_working_days ?? 22,
                    'status' => $payroll->status,
                    'processed_at' => $payroll->processed_at?->format('M j, Y g:i A'),
                    'paid_at' => $payroll->paid_at?->format('M j, Y g:i A')
                ]
            ]
        ]);
    }

    /**
     * Get employee's tax summary
     */
    public function getMyTaxSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $currentYear = $request->get('year', now()->year);
        $ytdTax = $this->taxService->calculateYTDTaxLiability($user, Carbon::create($currentYear));
        
        // Get current month tax calculation
        $currentGrossPay = $user->base_salary ?? 50000;
        $currentTaxCalculation = $this->taxService->calculateTaxes($currentGrossPay, $user);

        return response()->json([
            'success' => true,
            'data' => [
                'tax_year' => $currentYear,
                'ytd_summary' => $ytdTax,
                'current_month_tax' => $currentTaxCalculation,
                'tax_certificate' => $this->taxService->generateTaxCertificate($user, $currentYear)
            ]
        ]);
    }

    /**
     * Get employee's salary deductions
     */
    public function getMyDeductions(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
            'status' => 'nullable|in:active,completed,cancelled'
        ]);

        $query = SalaryDeduction::where('user_id', $user->id);
        
        if ($validated['year'] ?? false) {
            $query->whereYear('created_at', $validated['year']);
        }
        
        if ($validated['status'] ?? false) {
            $query->where('status', $validated['status']);
        }
        
        $deductions = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'deductions' => $deductions->map(function($deduction) {
                    return [
                        'id' => $deduction->id,
                        'type' => $deduction->deduction_type,
                        'description' => $deduction->description,
                        'amount' => $deduction->amount,
                        'status' => $deduction->status,
                        'created_at' => $deduction->created_at->format('M j, Y'),
                        'completed_at' => $deduction->completed_at?->format('M j, Y'),
                        'remaining_amount' => $deduction->remaining_amount ?? $deduction->amount
                    ];
                }),
                'summary' => [
                    'total_deductions' => $deductions->sum('amount'),
                    'active_deductions' => $deductions->where('status', 'active')->sum('amount'),
                    'completed_deductions' => $deductions->where('status', 'completed')->sum('amount'),
                    'cancelled_deductions' => $deductions->where('status', 'cancelled')->sum('amount')
                ]
            ]
        ]);
    }

    /**
     * Get employee's dashboard overview
     */
    public function getDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get current month data
        $currentMonth = now();
        $currentPayroll = Payroll::where('employee_id', $user->id)
            ->where('period_start', '>=', $currentMonth->startOfMonth())
            ->first();
            
        // Get pending bonuses
        $pendingBonuses = BonusLog::where('user_id', $user->id)
            ->where('status', 'pending')
            ->get();
            
        // Get YTD summary
        $ytdSummary = $this->getYearToDateSummary($user, $currentMonth->year);
        
        // Get recent activity
        $recentActivity = $this->getRecentActivity($user);

        return response()->json([
            'success' => true,
            'data' => [
                'current_month' => [
                    'payroll_status' => $currentPayroll ? $currentPayroll->status : 'not_processed',
                    'gross_pay' => $currentPayroll?->gross_pay ?? 0,
                    'net_pay' => $currentPayroll?->net_pay ?? 0,
                    'bonuses' => $currentPayroll?->total_bonuses ?? 0,
                    'deductions' => $currentPayroll?->total_deductions ?? 0
                ],
                'pending_bonuses' => [
                    'count' => $pendingBonuses->count(),
                    'total_amount' => $pendingBonuses->sum('amount'),
                    'bonuses' => $pendingBonuses->take(5)->map(function($bonus) {
                        return [
                            'type' => $bonus->bonus_type,
                            'description' => $bonus->description,
                            'amount' => $bonus->amount,
                            'created_at' => $bonus->created_at->format('M j, Y')
                        ];
                    })
                ],
                'ytd_summary' => $ytdSummary,
                'recent_activity' => $recentActivity,
                'quick_stats' => [
                    'months_worked' => $user->created_at->diffInMonths(now()),
                    'total_payrolls' => Payroll::where('employee_id', $user->id)->count(),
                    'total_bonuses' => BonusLog::where('user_id', $user->id)->where('status', 'paid')->sum('amount'),
                    'average_monthly_net' => Payroll::where('employee_id', $user->id)->avg('net_pay')
                ]
            ]
        ]);
    }

    /**
     * Get current month summary
     */
    private function getCurrentMonthSummary(User $user, Carbon $month): array
    {
        $currentPayroll = Payroll::where('employee_id', $user->id)
            ->where('period_start', '>=', $month->startOfMonth())
            ->first();
            
        $currentBonuses = BonusLog::where('user_id', $user->id)
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->get();

        return [
            'payroll_status' => $currentPayroll ? $currentPayroll->status : 'not_processed',
            'gross_pay' => $currentPayroll?->gross_pay ?? 0,
            'net_pay' => $currentPayroll?->net_pay ?? 0,
            'total_bonuses' => $currentBonuses->where('status', 'paid')->sum('amount'),
            'pending_bonuses' => $currentBonuses->where('status', 'pending')->sum('amount'),
            'total_deductions' => $currentPayroll?->total_deductions ?? 0
        ];
    }

    /**
     * Get year-to-date summary
     */
    private function getYearToDateSummary(User $user, int $year): array
    {
        $payrolls = Payroll::where('employee_id', $user->id)
            ->whereYear('period_start', $year)
            ->get();
            
        $bonuses = BonusLog::where('user_id', $user->id)
            ->whereYear('created_at', $year)
            ->get();

        return [
            'total_gross_earned' => $payrolls->sum('gross_pay'),
            'total_net_earned' => $payrolls->sum('net_pay'),
            'total_bonuses_earned' => $bonuses->where('status', 'paid')->sum('amount'),
            'total_deductions' => $payrolls->sum('total_deductions'),
            'average_monthly_net' => $payrolls->avg('net_pay'),
            'months_processed' => $payrolls->count()
        ];
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(User $user): array
    {
        $recentPayrolls = Payroll::where('employee_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get()
            ->map(function($payroll) {
                return [
                    'type' => 'payroll',
                    'description' => 'Payroll processed for ' . $payroll->period_start->format('M Y'),
                    'amount' => $payroll->net_pay,
                    'date' => $payroll->created_at->format('M j, Y'),
                    'status' => $payroll->status
                ];
            });

        $recentBonuses = BonusLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get()
            ->map(function($bonus) {
                return [
                    'type' => 'bonus',
                    'description' => $bonus->description,
                    'amount' => $bonus->amount,
                    'date' => $bonus->created_at->format('M j, Y'),
                    'status' => $bonus->status
                ];
            });

        return $recentPayrolls->concat($recentBonuses)
            ->sortByDesc('date')
            ->take(5)
            ->values()
            ->toArray();
    }
} 