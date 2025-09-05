<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\PayrollRecord;
use App\Services\AIAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PayrollManagementController extends Controller
{
    protected $aiAttendanceService;

    public function __construct(AIAttendanceService $aiAttendanceService)
    {
        $this->aiAttendanceService = $aiAttendanceService;
    }

    /**
     * Get payroll dashboard
     */
    public function getPayrollDashboard(): JsonResponse
    {
        try {
            $employees = Employee::with(['department', 'position'])
                ->where('status', 'active')
                ->get();

            $overview = $this->calculatePayrollOverview($employees);
            $monthlyPayroll = $this->getMonthlyPayrollSummary($employees);
            $attendanceOverview = $this->getAttendanceOverview($employees);
            $leaveRequests = $this->getLeaveRequests();

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => $overview,
                    'monthly_payroll_summary' => $monthlyPayroll,
                    'attendance_overview' => $attendanceOverview,
                    'leave_requests' => $leaveRequests
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Payroll Dashboard Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load payroll dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee payroll details
     */
    public function getEmployeePayrollDetails(int $employeeId): JsonResponse
    {
        try {
            $employee = Employee::with(['department', 'position'])->findOrFail($employeeId);
            
            $attendancePatterns = $this->aiAttendanceService->detectAttendancePatterns($employee);
            $attendanceRisk = $this->aiAttendanceService->flagAbsenteeismRisk($employee);
            
            $payrollHistory = PayrollRecord::where('employee_id', $employeeId)
                ->orderBy('payroll_period', 'desc')
                ->take(6)
                ->get();

            $attendanceRecords = AttendanceRecord::where('employee_id', $employeeId)
                ->where('date', '>=', now()->subMonth())
                ->orderBy('date')
                ->get();

            $leaveHistory = LeaveRequest::where('employee_id', $employeeId)
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->first_name . ' ' . $employee->last_name,
                        'position' => $employee->position->title ?? 'Unknown',
                        'department' => $employee->department->name ?? 'Unknown',
                        'base_salary' => $employee->base_salary,
                        'hire_date' => $employee->hire_date->format('M j, Y')
                    ],
                    'current_month_payroll' => $this->calculateCurrentMonthPayroll($employee),
                    'attendance_patterns' => $attendancePatterns,
                    'attendance_risk' => $attendanceRisk,
                    'payroll_history' => $payrollHistory,
                    'attendance_records' => $attendanceRecords,
                    'leave_history' => $leaveHistory,
                    'recommendations' => $this->generatePayrollRecommendations($employee)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Employee Payroll Details Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load employee payroll details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process payroll for current month
     */
    public function processPayroll(Request $request): JsonResponse
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'payroll_period' => 'required|date',
                'include_bonuses' => 'boolean',
                'include_penalties' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $employees = Employee::where('status', 'active')->get();
            $processedRecords = [];

            foreach ($employees as $employee) {
                $payrollRecord = $this->calculateEmployeePayroll($employee, $request->payroll_period);
                $processedRecords[] = $payrollRecord;
            }

            return response()->json([
                'success' => true,
                'message' => 'Payroll processed successfully',
                'data' => [
                    'payroll_period' => $request->payroll_period,
                    'total_employees' => count($processedRecords),
                    'total_payroll' => array_sum(array_column($processedRecords, 'net_pay')),
                    'processed_records' => $processedRecords
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Process Payroll Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve/reject leave request
     */
    public function processLeaveRequest(Request $request, int $leaveId): JsonResponse
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'action' => 'required|in:approve,reject',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $leaveRequest = LeaveRequest::findOrFail($leaveId);
            
            $leaveRequest->update([
                'status' => $request->action === 'approve' ? 'approved' : 'rejected',
                'processed_by' => $request->user()->name ?? 'System',
                'processed_at' => now(),
                'notes' => $request->notes
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Leave request ' . $request->action . 'ed successfully',
                'data' => [
                    'leave_id' => $leaveRequest->id,
                    'status' => $leaveRequest->status,
                    'processed_date' => now()->format('M j, Y')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Process Leave Request Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate payroll overview
     */
    private function calculatePayrollOverview($employees): array
    {
        $totalPayroll = $employees->sum('base_salary');
        $avgAttendance = $employees->avg('attendance_rate') ?? 0;
        $pendingLeaves = LeaveRequest::where('status', 'pending')->count();
        $aiFlags = $employees->filter(function ($employee) {
            $risk = $this->aiAttendanceService->flagAbsenteeismRisk($employee);
            return $risk['risk_score'] >= 7.0;
        })->count();

        return [
            'total_payroll' => $totalPayroll,
            'avg_attendance' => round($avgAttendance, 1) . '%',
            'pending_leaves' => $pendingLeaves,
            'ai_flags' => $aiFlags
        ];
    }

    /**
     * Get monthly payroll summary
     */
    private function getMonthlyPayrollSummary($employees): array
    {
        $period = now()->format('F Y');
        $payrollRecords = [];

        foreach ($employees as $employee) {
            $payrollRecord = $this->calculateEmployeePayroll($employee, now());
            $payrollRecords[] = [
                'name' => $employee->first_name . ' ' . $employee->last_name,
                'department' => $employee->department->name ?? 'Unknown',
                'base_salary' => $employee->base_salary,
                'performance_bonus' => $payrollRecord['performance_bonus'],
                'penalties' => $payrollRecord['penalties'],
                'net_pay' => $payrollRecord['net_pay'],
                'status' => 'pending'
            ];
        }

        return [
            'period' => $period,
            'employees' => $payrollRecords
        ];
    }

    /**
     * Get attendance overview
     */
    private function getAttendanceOverview($employees): array
    {
        return $employees->map(function ($employee) {
            $attendanceRisk = $this->aiAttendanceService->flagAbsenteeismRisk($employee);
            $attendancePatterns = $this->aiAttendanceService->detectAttendancePatterns($employee);
            
            return [
                'employee' => $employee->first_name . ' ' . $employee->last_name,
                'attendance_rate' => round($employee->attendance_rate ?? 100, 1) . '%',
                'wfh_days' => $this->calculateWFHDays($employee),
                'late_days' => $this->calculateLateDays($employee),
                'absent_days' => $this->calculateAbsentDays($employee),
                'calendar_data' => 'monthly_attendance_matrix',
                'ai_patterns' => $this->extractAIPatterns($attendancePatterns)
            ];
        })->toArray();
    }

    /**
     * Get leave requests
     */
    private function getLeaveRequests(): array
    {
        $leaveRequests = LeaveRequest::with('employee')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return $leaveRequests->map(function ($request) {
            return [
                'employee' => $request->employee->first_name . ' ' . $request->employee->last_name,
                'leave_type' => ucfirst($request->leave_type),
                'period' => $request->start_date->format('d/m/Y') . ' - ' . $request->end_date->format('d/m/Y'),
                'days' => $request->days,
                'reason' => $request->reason,
                'status' => $request->status,
                'actions' => ['approve', 'reject']
            ];
        })->toArray();
    }

    /**
     * Calculate current month payroll for employee
     */
    private function calculateCurrentMonthPayroll(Employee $employee): array
    {
        $baseSalary = $employee->base_salary;
        $performanceBonus = $this->calculatePerformanceBonus($employee);
        $penalties = $this->calculatePenalties($employee);
        $netPay = $baseSalary + $performanceBonus + $penalties;

        return [
            'base_salary' => $baseSalary,
            'performance_bonus' => $performanceBonus,
            'penalties' => $penalties,
            'net_pay' => $netPay,
            'payroll_period' => now()->format('F Y')
        ];
    }

    /**
     * Calculate employee payroll
     */
    private function calculateEmployeePayroll(Employee $employee, $period): array
    {
        $baseSalary = $employee->base_salary;
        $performanceBonus = $this->calculatePerformanceBonus($employee);
        $penalties = $this->calculatePenalties($employee);
        $netPay = $baseSalary + $performanceBonus + $penalties;

        return [
            'employee_id' => $employee->id,
            'base_salary' => $baseSalary,
            'performance_bonus' => $performanceBonus,
            'penalties' => $penalties,
            'net_pay' => $netPay,
            'payroll_period' => $period
        ];
    }

    /**
     * Calculate performance bonus
     */
    private function calculatePerformanceBonus(Employee $employee): float
    {
        $performanceRating = $employee->performance_rating ?? 5.0;
        $aiScore = $employee->ai_score ?? 5.0;
        
        if ($performanceRating >= 4.5 && $aiScore >= 8.0) {
            return $employee->base_salary * 0.15; // 15% bonus
        } elseif ($performanceRating >= 4.0 && $aiScore >= 7.0) {
            return $employee->base_salary * 0.10; // 10% bonus
        } elseif ($performanceRating >= 3.5 && $aiScore >= 6.0) {
            return $employee->base_salary * 0.05; // 5% bonus
        }
        
        return 0;
    }

    /**
     * Calculate penalties
     */
    private function calculatePenalties(Employee $employee): float
    {
        $penalties = 0;
        
        // Attendance penalties
        $attendanceRate = $employee->attendance_rate ?? 100;
        if ($attendanceRate < 90) {
            $penalties -= $employee->base_salary * 0.05; // 5% penalty
        }
        if ($attendanceRate < 80) {
            $penalties -= $employee->base_salary * 0.10; // 10% penalty
        }
        
        // Performance penalties
        $performanceRating = $employee->performance_rating ?? 5.0;
        if ($performanceRating < 3.0) {
            $penalties -= $employee->base_salary * 0.15; // 15% penalty
        }
        
        return $penalties;
    }

    /**
     * Calculate WFH days
     */
    private function calculateWFHDays(Employee $employee): int
    {
        return AttendanceRecord::where('employee_id', $employee->id)
            ->where('work_mode', 'wfh')
            ->where('date', '>=', now()->startOfMonth())
            ->count();
    }

    /**
     * Calculate late days
     */
    private function calculateLateDays(Employee $employee): int
    {
        return AttendanceRecord::where('employee_id', $employee->id)
            ->where('status', 'late')
            ->where('date', '>=', now()->startOfMonth())
            ->count();
    }

    /**
     * Calculate absent days
     */
    private function calculateAbsentDays(Employee $employee): int
    {
        return AttendanceRecord::where('employee_id', $employee->id)
            ->where('status', 'absent')
            ->where('date', '>=', now()->startOfMonth())
            ->count();
    }

    /**
     * Extract AI patterns from attendance analysis
     */
    private function extractAIPatterns(array $patterns): array
    {
        $aiPatterns = [];
        
        if (isset($patterns['weekly_pattern']['monday_absence_rate']) && $patterns['weekly_pattern']['monday_absence_rate'] > 30) {
            $aiPatterns[] = 'frequent_monday_wfh_requests';
        }
        
        if (isset($patterns['absenteeism_pattern']['absent_rate']) && $patterns['absenteeism_pattern']['absent_rate'] > 10) {
            $aiPatterns[] = 'excessive_wfh_pattern';
        }
        
        if (isset($patterns['punctuality_pattern']['late_rate']) && $patterns['punctuality_pattern']['late_rate'] > 20) {
            $aiPatterns[] = 'chronic_lateness_pattern';
        }
        
        return $aiPatterns;
    }

    /**
     * Generate payroll recommendations
     */
    private function generatePayrollRecommendations(Employee $employee): array
    {
        $recommendations = [];
        
        if ($employee->attendance_rate < 85) {
            $recommendations[] = 'Consider flexible work arrangements';
            $recommendations[] = 'Address attendance concerns';
        }
        
        if ($employee->performance_rating < 3.5) {
            $recommendations[] = 'Provide performance improvement support';
            $recommendations[] = 'Consider performance-based incentives';
        }
        
        if ($employee->ai_score >= 8.5) {
            $recommendations[] = 'Consider performance bonus for exceptional work';
        }
        
        return $recommendations;
    }
}
