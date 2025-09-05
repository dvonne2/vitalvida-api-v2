<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ThresholdValidationService;
use App\Models\ThresholdViolation;
use App\Models\ApprovalWorkflow;
use App\Models\SalaryDeduction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ThresholdEnforcementController extends Controller
{
    protected $thresholdService;

    public function __construct(ThresholdValidationService $thresholdService)
    {
        $this->thresholdService = $thresholdService;
    }

    /**
     * Validate expense against thresholds
     */
    public function validateExpense(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'category' => 'required|string|max:50',
            'subcategory' => 'nullable|string|max:50',
            'context' => 'nullable|array',
            'user_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $expenseData = $request->all();
            $expenseData['user_id'] = $expenseData['user_id'] ?? Auth::id();

            $result = $this->thresholdService->validateExpense($expenseData);

            return response()->json([
                'success' => $result['status'] !== 'error',
                'message' => $result['message'],
                'data' => $result,
                'timestamp' => now()
            ], $result['status'] === 'error' ? 500 : 200);

        } catch (\Exception $e) {
            Log::error('Expense validation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Expense validation failed',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Process approval decision
     */
    public function processApproval(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'workflow_id' => 'required|integer|exists:approval_workflows,id',
            'decision' => 'required|in:approve,reject',
            'comments' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->thresholdService->processApproval(
                $request->workflow_id,
                Auth::id(),
                $request->decision,
                $request->comments
            );

            return response()->json([
                'success' => $result['status'] !== 'error',
                'message' => $result['message'],
                'data' => $result,
                'timestamp' => now()
            ], $result['status'] === 'error' ? 500 : 200);

        } catch (\Exception $e) {
            Log::error('Approval processing failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Approval processing failed',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Handle unauthorized payment
     */
    public function handleUnauthorizedPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'expense_id' => 'required|integer',
            'user_id' => 'required|exists:users,id',
            'payment_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->thresholdService->handleUnauthorizedPayment($request->all());

            return response()->json([
                'success' => $result['status'] !== 'error',
                'message' => $result['message'],
                'data' => $result,
                'timestamp' => now()
            ], $result['status'] === 'error' ? 500 : 200);

        } catch (\Exception $e) {
            Log::error('Unauthorized payment handling failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized payment handling failed',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get threshold violations
     */
    public function getViolations(Request $request): JsonResponse
    {
        try {
            $query = ThresholdViolation::with(['createdBy', 'approvalWorkflow', 'salaryDeduction']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            if ($request->has('date_from') && $request->has('date_to')) {
                $query->byDateRange($request->date_from, $request->date_to);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $violations = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Threshold violations retrieved successfully',
                'data' => $violations,
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get threshold violations', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get threshold violations',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get approval workflows
     */
    public function getApprovalWorkflows(Request $request): JsonResponse
    {
        try {
            $query = ApprovalWorkflow::with(['violation']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('workflow_type')) {
                $query->where('workflow_type', $request->workflow_type);
            }

            if ($request->has('urgent') && $request->urgent) {
                $query->expiringSoon(12); // 12 hours
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $workflows = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Approval workflows retrieved successfully',
                'data' => $workflows,
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get approval workflows', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get approval workflows',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get salary deductions
     */
    public function getSalaryDeductions(Request $request): JsonResponse
    {
        try {
            $query = SalaryDeduction::with(['user', 'violation']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('overdue') && $request->overdue) {
                $query->overdue();
            }

            if ($request->has('upcoming') && $request->upcoming) {
                $query->upcoming(30); // 30 days
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $deductions = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Salary deductions retrieved successfully',
                'data' => $deductions,
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get salary deductions', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get salary deductions',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get threshold statistics
     */
    public function getThresholdStatistics(): JsonResponse
    {
        try {
            $violationStats = ThresholdViolation::getViolationsByStatus();
            $workflowStats = ApprovalWorkflow::getWorkflowStatistics();
            $deductionStats = SalaryDeduction::getDeductionStatistics();
            $overallStats = $this->thresholdService->getThresholdStatistics();

            return response()->json([
                'success' => true,
                'message' => 'Threshold statistics retrieved successfully',
                'data' => [
                    'violations' => $violationStats,
                    'workflows' => $workflowStats,
                    'deductions' => $deductionStats,
                    'overall' => $overallStats
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get threshold statistics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get threshold statistics',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get urgent items requiring attention
     */
    public function getUrgentItems(): JsonResponse
    {
        try {
            $urgentViolations = ThresholdViolation::getUrgentViolations();
            $urgentWorkflows = ApprovalWorkflow::getUrgentWorkflows();
            $overdueDeductions = SalaryDeduction::getOverdueDeductions();

            return response()->json([
                'success' => true,
                'message' => 'Urgent items retrieved successfully',
                'data' => [
                    'urgent_violations' => $urgentViolations,
                    'urgent_workflows' => $urgentWorkflows,
                    'overdue_deductions' => $overdueDeductions,
                    'summary' => [
                        'urgent_violations_count' => count($urgentViolations),
                        'urgent_workflows_count' => count($urgentWorkflows),
                        'overdue_deductions_count' => count($overdueDeductions)
                    ]
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get urgent items', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get urgent items',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Process salary deduction
     */
    public function processSalaryDeduction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'deduction_id' => 'required|integer|exists:salary_deductions,id',
            'action' => 'required|in:process,cancel',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deduction = SalaryDeduction::findOrFail($request->deduction_id);

            if ($request->action === 'process') {
                $deduction->markAsProcessed(Auth::id(), $request->notes);
                $message = 'Salary deduction processed successfully';
            } else {
                $deduction->markAsCancelled(Auth::id(), $request->notes ?? 'Cancelled by user');
                $message = 'Salary deduction cancelled successfully';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $deduction->fresh(),
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to process salary deduction', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process salary deduction',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Health check endpoint
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $stats = $this->thresholdService->getThresholdStatistics();
            
            return response()->json([
                'success' => true,
                'message' => 'Threshold Enforcement System is healthy',
                'data' => [
                    'system_status' => 'operational',
                    'total_violations' => $stats['total_violations'],
                    'pending_approvals' => $stats['pending_approvals'],
                    'compliance_rate' => $stats['compliance_rate'],
                    'unauthorized_payments' => $stats['unauthorized_payments']
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Threshold Enforcement System health check failed',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }
} 