<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SalaryDeduction;
use App\Models\User;
use App\Services\SalaryDeductionService;
use Illuminate\Support\Facades\Log;

class SalaryDeductionController extends Controller
{
    protected $salaryService;

    public function __construct(SalaryDeductionService $salaryService)
    {
        $this->salaryService = $salaryService;
    }

    /**
     * Get all salary deductions with filtering
     */
    public function getDeductions(Request $request)
    {
        $user = $request->user();

        // Check if user has permission to view all deductions
        $canViewAll = in_array($user->role, ['CFO', 'CEO', 'superadmin']);

        if (!$canViewAll) {
            // Regular users can only view their own deductions
            return $this->getUserDeductions($request, $user->id);
        }

        $validated = $request->validate([
            'status' => 'nullable|in:pending,processed,cancelled',
            'reason' => 'nullable|in:unauthorized_payment,rejected_escalation,expired_escalation',
            'user_id' => 'nullable|integer|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0'
        ]);

        $query = SalaryDeduction::with(['user', 'violation']);

        // Apply filters
        if ($validated['status'] ?? null) {
            $query->where('status', $validated['status']);
        }

        if ($validated['reason'] ?? null) {
            $query->where('reason', $validated['reason']);
        }

        if ($validated['user_id'] ?? null) {
            $query->where('user_id', $validated['user_id']);
        }

        if ($validated['date_from'] ?? null) {
            $query->where('created_at', '>=', $validated['date_from']);
        }

        if ($validated['date_to'] ?? null) {
            $query->where('created_at', '<=', $validated['date_to']);
        }

        $limit = $validated['limit'] ?? 50;
        $offset = $validated['offset'] ?? 0;

        $deductions = $query
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(function($deduction) {
                return [
                    'id' => $deduction->id,
                    'user' => [
                        'id' => $deduction->user->id,
                        'name' => $deduction->user->name,
                        'email' => $deduction->user->email,
                        'role' => $deduction->user->role
                    ],
                    'amount' => $deduction->amount,
                    'reason' => $deduction->reason,
                    'description' => $deduction->description,
                    'status' => $deduction->status,
                    'deduction_date' => $deduction->deduction_date,
                    'processed_date' => $deduction->processed_date,
                    'created_at' => $deduction->created_at,
                    'days_until_deduction' => $deduction->status === 'pending' ? 
                        max(0, now()->diffInDays($deduction->deduction_date, false)) : null,
                    'violation_details' => $deduction->violation ? [
                        'cost_type' => $deduction->violation->cost_type,
                        'cost_category' => $deduction->violation->cost_category,
                        'original_amount' => $deduction->violation->amount,
                        'threshold_limit' => $deduction->violation->threshold_limit,
                        'overage_amount' => $deduction->violation->overage_amount
                    ] : null
                ];
            });

        // Get total count for pagination
        $totalCount = SalaryDeduction::when($validated['status'] ?? null, function($query, $status) {
            $query->where('status', $status);
        })->when($validated['reason'] ?? null, function($query, $reason) {
            $query->where('reason', $reason);
        })->when($validated['user_id'] ?? null, function($query, $userId) {
            $query->where('user_id', $userId);
        })->when($validated['date_from'] ?? null, function($query, $dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        })->when($validated['date_to'] ?? null, function($query, $dateTo) {
            $query->where('created_at', '<=', $dateTo);
        })->count();

        return response()->json([
            'success' => true,
            'deductions' => $deductions,
            'pagination' => [
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount
            ]
        ]);
    }

    /**
     * Get salary deduction statistics
     */
    public function getStatistics(Request $request)
    {
        $user = $request->user();

        // Check permissions
        $canViewAll = in_array($user->role, ['CFO', 'CEO', 'superadmin']);
        
        if (!$canViewAll) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'period' => 'nullable|in:today,week,month,quarter,year'
        ]);

        $filters = [];
        
        if ($validated['user_id'] ?? null) {
            $filters['user_id'] = $validated['user_id'];
        }
        
        if ($validated['date_from'] ?? null) {
            $filters['date_from'] = $validated['date_from'];
        }
        
        if ($validated['date_to'] ?? null) {
            $filters['date_to'] = $validated['date_to'];
        }
        
        // Set period-based date range if specified
        if ($validated['period'] ?? null) {
            $period = $validated['period'];
            $filters['date_from'] = now()->startOf($period);
            $filters['date_to'] = now()->endOf($period);
        }

        $statistics = $this->salaryService->getDeductionStatistics($filters);

        return response()->json([
            'success' => true,
            'statistics' => $statistics,
            'filters_applied' => $filters
        ]);
    }

    /**
     * Get user-specific salary deductions
     */
    public function getUserDeductions(Request $request, int $userId)
    {
        $user = $request->user();

        // Check permissions - users can only view their own deductions unless they're admin
        $canViewAll = in_array($user->role, ['CFO', 'CEO', 'superadmin']);
        
        if (!$canViewAll && $user->id !== $userId) {
            return response()->json(['error' => 'Cannot view other user\'s deductions'], 403);
        }

        $validated = $request->validate([
            'status' => 'nullable|in:pending,processed,cancelled',
            'reason' => 'nullable|in:unauthorized_payment,rejected_escalation,expired_escalation',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date'
        ]);

        $filters = [];
        
        if ($validated['status'] ?? null) {
            $filters['status'] = $validated['status'];
        }
        
        if ($validated['reason'] ?? null) {
            $filters['reason'] = $validated['reason'];
        }
        
        if ($validated['date_from'] ?? null) {
            $filters['date_from'] = $validated['date_from'];
        }
        
        if ($validated['date_to'] ?? null) {
            $filters['date_to'] = $validated['date_to'];
        }

        $result = $this->salaryService->getUserDeductions($userId, $filters);

        // Get user details
        $targetUser = User::find($userId);
        if (!$targetUser) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'role' => $targetUser->role
            ],
            'deductions' => $result['deductions'],
            'summary' => $result['summary']
        ]);
    }

    /**
     * Cancel a salary deduction (admin only)
     */
    public function cancelDeduction(Request $request, int $deductionId)
    {
        $user = $request->user();

        // Check permissions
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        try {
            $deduction = SalaryDeduction::findOrFail($deductionId);

            if ($deduction->status !== 'pending') {
                return response()->json([
                    'error' => 'Only pending deductions can be cancelled',
                    'current_status' => $deduction->status
                ], 422);
            }

            $success = $this->salaryService->cancelDeduction($deductionId, $validated['reason']);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Salary deduction cancelled successfully',
                    'deduction_id' => $deductionId,
                    'cancelled_by' => $user->name,
                    'cancellation_reason' => $validated['reason']
                ]);
            } else {
                return response()->json([
                    'error' => 'Failed to cancel salary deduction',
                    'message' => 'Please try again or contact support'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to cancel salary deduction', [
                'deduction_id' => $deductionId,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to cancel salary deduction',
                'message' => 'Please try again or contact support'
            ], 500);
        }
    }

    /**
     * Process pending salary deductions (admin only)
     */
    public function processPendingDeductions(Request $request)
    {
        $user = $request->user();

        // Check permissions
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        try {
            $results = $this->salaryService->processPendingDeductions();

            return response()->json([
                'success' => true,
                'message' => 'Pending salary deductions processed',
                'results' => $results,
                'summary' => [
                    'total_processed' => $results['total_processed'],
                    'total_amount' => $results['total_amount'],
                    'successful_count' => count($results['successful']),
                    'failed_count' => count($results['failed'])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process pending salary deductions', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to process pending salary deductions',
                'message' => 'Please try again or contact support'
            ], 500);
        }
    }

    /**
     * Get detailed deduction information
     */
    public function getDeductionDetails(Request $request, int $deductionId)
    {
        $user = $request->user();

        try {
            $deduction = SalaryDeduction::with(['user', 'violation'])
                ->findOrFail($deductionId);

            // Check permissions - users can only view their own deductions unless they're admin
            $canViewAll = in_array($user->role, ['CFO', 'CEO', 'superadmin']);
            
            if (!$canViewAll && $user->id !== $deduction->user_id) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            return response()->json([
                'success' => true,
                'deduction' => [
                    'id' => $deduction->id,
                    'user' => [
                        'id' => $deduction->user->id,
                        'name' => $deduction->user->name,
                        'email' => $deduction->user->email,
                        'role' => $deduction->user->role
                    ],
                    'amount' => $deduction->amount,
                    'reason' => $deduction->reason,
                    'description' => $deduction->description,
                    'status' => $deduction->status,
                    'deduction_date' => $deduction->deduction_date,
                    'processed_date' => $deduction->processed_date,
                    'created_at' => $deduction->created_at,
                    'days_until_deduction' => $deduction->status === 'pending' ? 
                        max(0, now()->diffInDays($deduction->deduction_date, false)) : null,
                    'violation_details' => $deduction->violation ? [
                        'id' => $deduction->violation->id,
                        'cost_type' => $deduction->violation->cost_type,
                        'cost_category' => $deduction->violation->cost_category,
                        'original_amount' => $deduction->violation->amount,
                        'threshold_limit' => $deduction->violation->threshold_limit,
                        'overage_amount' => $deduction->violation->overage_amount,
                        'overage_percentage' => $deduction->violation->threshold_limit > 0 ?
                            round(($deduction->violation->overage_amount / $deduction->violation->threshold_limit) * 100, 1) : 0,
                        'violation_details' => $deduction->violation->violation_details,
                        'created_at' => $deduction->violation->created_at
                    ] : null,
                    'metadata' => $deduction->metadata,
                    'can_be_cancelled' => $deduction->status === 'pending' && $canViewAll
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Deduction not found'], 404);
        }
    }

    /**
     * Get upcoming deductions for the next 30 days
     */
    public function getUpcomingDeductions(Request $request)
    {
        $user = $request->user();

        // Check permissions
        $canViewAll = in_array($user->role, ['CFO', 'CEO', 'superadmin']);
        
        if (!$canViewAll) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $upcomingDeductions = SalaryDeduction::with(['user', 'violation'])
            ->where('status', 'pending')
            ->where('deduction_date', '>', now())
            ->where('deduction_date', '<=', now()->addDays(30))
            ->orderBy('deduction_date', 'asc')
            ->get()
            ->map(function($deduction) {
                return [
                    'id' => $deduction->id,
                    'user' => [
                        'id' => $deduction->user->id,
                        'name' => $deduction->user->name,
                        'email' => $deduction->user->email,
                        'role' => $deduction->user->role
                    ],
                    'amount' => $deduction->amount,
                    'reason' => $deduction->reason,
                    'description' => $deduction->description,
                    'deduction_date' => $deduction->deduction_date,
                    'days_until_deduction' => now()->diffInDays($deduction->deduction_date, false),
                    'urgency' => $this->getDeductionUrgency($deduction->deduction_date),
                    'violation_summary' => $deduction->violation ? [
                        'cost_type' => $deduction->violation->cost_type,
                        'overage_amount' => $deduction->violation->overage_amount
                    ] : null
                ];
            });

        // Group by urgency
        $groupedDeductions = $upcomingDeductions->groupBy('urgency');

        return response()->json([
            'success' => true,
            'upcoming_deductions' => $upcomingDeductions,
            'grouped_by_urgency' => [
                'immediate' => $groupedDeductions->get('immediate', collect()),
                'this_week' => $groupedDeductions->get('this_week', collect()),
                'next_week' => $groupedDeductions->get('next_week', collect()),
                'later' => $groupedDeductions->get('later', collect())
            ],
            'summary' => [
                'total_upcoming' => $upcomingDeductions->count(),
                'total_amount' => $upcomingDeductions->sum('amount'),
                'immediate_count' => $groupedDeductions->get('immediate', collect())->count(),
                'this_week_count' => $groupedDeductions->get('this_week', collect())->count()
            ]
        ]);
    }

    /**
     * Get deduction urgency level
     */
    private function getDeductionUrgency($deductionDate): string
    {
        $daysUntil = now()->diffInDays($deductionDate, false);

        if ($daysUntil <= 1) {
            return 'immediate';
        } elseif ($daysUntil <= 7) {
            return 'this_week';
        } elseif ($daysUntil <= 14) {
            return 'next_week';
        } else {
            return 'later';
        }
    }
} 