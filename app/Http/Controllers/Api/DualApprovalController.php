<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EscalationRequest;
use App\Models\ApprovalDecision;
use App\Models\User;
use App\Services\SalaryDeductionService;
use App\Notifications\ApprovalDecisionNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DualApprovalController extends Controller
{
    protected $salaryService;

    public function __construct(SalaryDeductionService $salaryService)
    {
        $this->salaryService = $salaryService;
    }

    /**
     * Get pending escalations for FC/GM approval
     */
    public function getPendingEscalations(Request $request)
    {
        $user = $request->user();

        // Verify user can approve
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Approval access required'], 403);
        }

        $pendingEscalations = EscalationRequest::with([
            'thresholdViolation',
            'approvalDecisions.approver'
        ])
        ->where('status', 'pending_approval')
        ->where('expires_at', '>', now())
        ->whereJsonContains('approval_required', $user->role)
        ->orderBy('priority', 'desc')
        ->orderBy('created_at', 'asc')
        ->get()
        ->map(function($escalation) use ($user) {
            return [
                'id' => $escalation->id,
                'type' => $escalation->escalation_type,
                'amount_requested' => $escalation->amount_requested,
                'threshold_limit' => $escalation->threshold_limit,
                'overage_amount' => $escalation->overage_amount,
                'overage_percentage' => $escalation->threshold_limit > 0 ?
                    round(($escalation->overage_amount / $escalation->threshold_limit) * 100, 1) : 0,
                'escalation_reason' => $escalation->escalation_reason,
                'business_justification' => $escalation->business_justification,
                'priority' => $escalation->priority,
                'expires_at' => $escalation->expires_at,
                'time_remaining' => $escalation->expires_at->diffForHumans(),
                'hours_remaining' => $escalation->expires_at->diffInHours(now()),
                'approval_status' => $this->getApprovalStatus($escalation, $user),
                'other_approvals' => $this->getOtherApprovals($escalation, $user),
                'violation_details' => $escalation->thresholdViolation,
                'urgency_level' => $this->calculateUrgencyLevel($escalation),
                'approval_instructions' => $this->getApprovalInstructions($escalation),
                'created_at' => $escalation->created_at,
                'requester' => $escalation->creator->name ?? 'Unknown'
            ];
        });

        return response()->json([
            'success' => true,
            'pending_escalations' => $pendingEscalations,
            'total_pending' => $pendingEscalations->count(),
            'urgent_count' => $pendingEscalations->where('urgency_level', 'urgent')->count(),
            'your_role' => $user->role,
            'approval_summary' => [
                'total_pending' => $pendingEscalations->count(),
                'expires_within_2h' => $pendingEscalations->where('hours_remaining', '<=', 2)->count(),
                'expires_within_24h' => $pendingEscalations->where('hours_remaining', '<=', 24)->count(),
                'high_priority' => $pendingEscalations->where('priority', 'high')->count(),
                'critical_priority' => $pendingEscalations->where('priority', 'critical')->count()
            ]
        ]);
    }

    /**
     * Submit approval or rejection decision
     */
    public function submitApprovalDecision(Request $request, int $escalationId)
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'decision' => 'required|in:approve,reject',
                'comments' => 'nullable|string|max:1000',
                'business_justification' => 'required_if:decision,approve|string|max:1000'
            ]);

            $escalation = EscalationRequest::with(['thresholdViolation', 'approvalDecisions'])
                ->findOrFail($escalationId);

            // Verify user can approve this escalation
            if (!$this->canUserApprove($escalation, $user)) {
                return response()->json([
                    'error' => 'You are not authorized to approve this escalation'
                ], 403);
            }

            // Check if user already made a decision
            $existingDecision = $escalation->approvalDecisions()
                ->where('approver_id', $user->id)
                ->first();

            if ($existingDecision) {
                return response()->json([
                    'error' => 'You have already made a decision on this escalation',
                    'previous_decision' => $existingDecision->decision,
                    'decided_at' => $existingDecision->created_at
                ], 422);
            }

            // Check if escalation has expired
            if ($escalation->expires_at < now()) {
                return response()->json([
                    'error' => 'This escalation has expired and can no longer be approved'
                ], 422);
            }

            // Create approval decision
            $decision = ApprovalDecision::create([
                'escalation_request_id' => $escalation->id,
                'approver_id' => $user->id,
                'approver_role' => $user->role,
                'decision' => $validated['decision'],
                'decision_reason' => $validated['comments'],
                'decision_at' => now(),
                'metadata' => [
                    'business_justification' => $validated['business_justification'] ?? null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]
            ]);

            // Check if all required approvals are now complete
            $finalStatus = $this->evaluateFinalApprovalStatus($escalation);

            // Update escalation status
            $escalation->update(['status' => $finalStatus['status']]);

            // Handle final decision
            if ($finalStatus['is_final']) {
                $this->handleFinalApprovalDecision($escalation, $finalStatus);
            }

            // Log decision
            Log::info('Approval decision submitted', [
                'escalation_id' => $escalation->id,
                'approver' => $user->name,
                'role' => $user->role,
                'decision' => $validated['decision'],
                'amount' => $escalation->amount_requested,
                'final_status' => $finalStatus['status']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Approval decision submitted successfully',
                'decision_id' => $decision->id,
                'your_decision' => $validated['decision'],
                'escalation_status' => $finalStatus['status'],
                'is_final_decision' => $finalStatus['is_final'],
                'final_outcome' => $finalStatus['is_final'] ? $finalStatus['outcome'] : null,
                'next_steps' => $this->getNextSteps($escalation, $finalStatus),
                'other_approvals_needed' => $finalStatus['remaining_approvals'] ?? []
            ]);

        } catch (\Exception $e) {
            Log::error('Approval decision failed', [
                'escalation_id' => $escalationId,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to process approval decision',
                'message' => 'Please try again or contact support'
            ], 500);
        }
    }

    /**
     * Get detailed escalation information
     */
    public function getEscalationDetails(Request $request, int $escalationId)
    {
        $user = $request->user();
        
        $escalation = EscalationRequest::with([
            'thresholdViolation',
            'approvalDecisions.approver',
            'creator'
        ])->findOrFail($escalationId);

        // Check if user can view this escalation
        if (!$this->canUserApprove($escalation, $user) && $escalation->created_by !== $user->id) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        return response()->json([
            'success' => true,
            'escalation' => [
                'id' => $escalation->id,
                'type' => $escalation->escalation_type,
                'amount_requested' => $escalation->amount_requested,
                'threshold_limit' => $escalation->threshold_limit,
                'overage_amount' => $escalation->overage_amount,
                'overage_percentage' => $escalation->threshold_limit > 0 ?
                    round(($escalation->overage_amount / $escalation->threshold_limit) * 100, 1) : 0,
                'escalation_reason' => $escalation->escalation_reason,
                'business_justification' => $escalation->business_justification,
                'priority' => $escalation->priority,
                'status' => $escalation->status,
                'expires_at' => $escalation->expires_at,
                'time_remaining' => $escalation->expires_at > now() ? $escalation->expires_at->diffForHumans() : 'EXPIRED',
                'hours_remaining' => max(0, $escalation->expires_at->diffInHours(now())),
                'created_at' => $escalation->created_at,
                'requester' => [
                    'id' => $escalation->creator->id,
                    'name' => $escalation->creator->name,
                    'email' => $escalation->creator->email,
                    'role' => $escalation->creator->role
                ],
                'violation_details' => $escalation->thresholdViolation,
                'approval_required' => $escalation->approval_required,
                'approval_decisions' => $escalation->approvalDecisions->map(function($decision) {
                    return [
                        'id' => $decision->id,
                        'approver' => $decision->approver->name,
                        'approver_role' => $decision->approver_role,
                        'decision' => $decision->decision,
                        'decision_reason' => $decision->decision_reason,
                        'decided_at' => $decision->decision_at,
                        'business_justification' => $decision->metadata['business_justification'] ?? null
                    ];
                }),
                'detailed_approval_status' => $this->getDetailedApprovalStatus($escalation),
                'can_user_approve' => $this->canUserApprove($escalation, $user),
                'user_decision' => $escalation->approvalDecisions
                    ->where('approver_id', $user->id)
                    ->first()?->decision,
                'urgency_level' => $this->calculateUrgencyLevel($escalation),
                'approval_instructions' => $this->getApprovalInstructions($escalation)
            ]
        ]);
    }

    /**
     * Get all escalations with filtering (for admin view)
     */
    public function getAllEscalations(Request $request)
    {
        $user = $request->user();

        // Admin-only access
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Administrative access required'], 403);
        }

        $validated = $request->validate([
            'status' => 'nullable|in:pending_approval,approved,rejected,expired',
            'priority' => 'nullable|in:normal,medium,high,critical',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0'
        ]);

        $query = EscalationRequest::with([
            'thresholdViolation',
            'approvalDecisions.approver',
            'creator'
        ]);

        // Apply filters
        if ($validated['status'] ?? null) {
            $query->where('status', $validated['status']);
        }
        if ($validated['priority'] ?? null) {
            $query->where('priority', $validated['priority']);
        }
        if ($validated['date_from'] ?? null) {
            $query->where('created_at', '>=', $validated['date_from']);
        }
        if ($validated['date_to'] ?? null) {
            $query->where('created_at', '<=', $validated['date_to']);
        }

        $limit = $validated['limit'] ?? 50;
        $offset = $validated['offset'] ?? 0;

        $escalations = $query
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(function($escalation) {
                return [
                    'id' => $escalation->id,
                    'type' => $escalation->escalation_type,
                    'amount_requested' => $escalation->amount_requested,
                    'overage_amount' => $escalation->overage_amount,
                    'priority' => $escalation->priority,
                    'status' => $escalation->status,
                    'created_at' => $escalation->created_at,
                    'expires_at' => $escalation->expires_at,
                    'requester' => $escalation->creator->name ?? 'Unknown',
                    'approval_count' => $escalation->approvalDecisions->count(),
                    'required_approvals' => count($escalation->approval_required),
                    'final_decision_at' => $escalation->final_decision_at,
                    'final_outcome' => $escalation->final_outcome
                ];
            });

        // Get total count for pagination
        $totalCount = EscalationRequest::when($validated['status'] ?? null, function($query, $status) {
            $query->where('status', $status);
        })->when($validated['priority'] ?? null, function($query, $priority) {
            $query->where('priority', $priority);
        })->when($validated['date_from'] ?? null, function($query, $dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        })->when($validated['date_to'] ?? null, function($query, $dateTo) {
            $query->where('created_at', '<=', $dateTo);
        })->count();

        return response()->json([
            'success' => true,
            'escalations' => $escalations,
            'pagination' => [
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount
            ]
        ]);
    }

    /**
     * Get approval analytics and metrics
     */
    public function getApprovalAnalytics(Request $request)
    {
        $user = $request->user();

        // Admin-only access
        if (!in_array($user->role, ['CFO', 'CEO', 'superadmin'])) {
            return response()->json(['error' => 'Administrative access required'], 403);
        }

        $validated = $request->validate([
            'period' => 'nullable|in:today,week,month,quarter,year',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date'
        ]);

        $period = $validated['period'] ?? 'month';
        $dateFrom = $validated['date_from'] ?? now()->startOf($period);
        $dateTo = $validated['date_to'] ?? now()->endOf($period);

        // Get escalation statistics
        $totalEscalations = EscalationRequest::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $pendingEscalations = EscalationRequest::where('status', 'pending_approval')
            ->whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $approvedEscalations = EscalationRequest::where('status', 'approved')
            ->whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $rejectedEscalations = EscalationRequest::where('status', 'rejected')
            ->whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $expiredEscalations = EscalationRequest::where('status', 'expired')
            ->whereBetween('created_at', [$dateFrom, $dateTo])->count();

        // Get approval times
        $approvalTimes = EscalationRequest::whereNotNull('final_decision_at')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get()
            ->map(function($escalation) {
                return $escalation->created_at->diffInHours($escalation->final_decision_at);
            });

        $averageApprovalTime = $approvalTimes->avg();
        $medianApprovalTime = $approvalTimes->median();

        // Get amount statistics
        $totalAmount = EscalationRequest::whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('amount_requested');
        $approvedAmount = EscalationRequest::where('status', 'approved')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('amount_requested');
        $rejectedAmount = EscalationRequest::where('status', 'rejected')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('amount_requested');

        // Get approval rates by role
        $approvalsByRole = ApprovalDecision::select('approver_role', 'decision', DB::raw('count(*) as count'))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('approver_role', 'decision')
            ->get()
            ->groupBy('approver_role');

        return response()->json([
            'success' => true,
            'period' => $period,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'escalation_metrics' => [
                'total_escalations' => $totalEscalations,
                'pending_escalations' => $pendingEscalations,
                'approved_escalations' => $approvedEscalations,
                'rejected_escalations' => $rejectedEscalations,
                'expired_escalations' => $expiredEscalations,
                'approval_rate' => $totalEscalations > 0 ? round(($approvedEscalations / $totalEscalations) * 100, 1) : 0,
                'rejection_rate' => $totalEscalations > 0 ? round(($rejectedEscalations / $totalEscalations) * 100, 1) : 0,
                'expiration_rate' => $totalEscalations > 0 ? round(($expiredEscalations / $totalEscalations) * 100, 1) : 0
            ],
            'approval_timing' => [
                'average_approval_time_hours' => round($averageApprovalTime, 1),
                'median_approval_time_hours' => round($medianApprovalTime, 1),
                'fastest_approval_hours' => $approvalTimes->min(),
                'slowest_approval_hours' => $approvalTimes->max()
            ],
            'amount_metrics' => [
                'total_amount_requested' => $totalAmount,
                'approved_amount' => $approvedAmount,
                'rejected_amount' => $rejectedAmount,
                'average_escalation_amount' => $totalEscalations > 0 ? round($totalAmount / $totalEscalations, 2) : 0
            ],
            'approval_rates_by_role' => $approvalsByRole->map(function($decisions, $role) {
                $total = $decisions->sum('count');
                $approved = $decisions->where('decision', 'approved')->sum('count');
                $rejected = $decisions->where('decision', 'rejected')->sum('count');
                
                return [
                    'role' => $role,
                    'total_decisions' => $total,
                    'approved_count' => $approved,
                    'rejected_count' => $rejected,
                    'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 1) : 0
                ];
            })->values()
        ]);
    }

    // Helper methods
    private function evaluateFinalApprovalStatus(EscalationRequest $escalation): array
    {
        $requiredApprovers = $escalation->approval_required;
        $decisions = $escalation->approvalDecisions;
        
        $approvedRoles = $decisions->where('decision', 'approved')->pluck('approver_role')->toArray();
        $rejectedRoles = $decisions->where('decision', 'rejected')->pluck('approver_role')->toArray();
        
        // If any required approver rejected, the entire request is rejected
        if (count($rejectedRoles) > 0) {
            return [
                'status' => 'rejected',
                'is_final' => true,
                'outcome' => 'rejected',
                'reason' => 'At least one required approver rejected the request'
            ];
        }
        
        // Check if all required approvers have approved
        $allApproved = collect($requiredApprovers)->every(function($role) use ($approvedRoles) {
            return in_array($role, $approvedRoles);
        });
        
        if ($allApproved) {
            return [
                'status' => 'approved',
                'is_final' => true,
                'outcome' => 'approved',
                'reason' => 'All required approvers have approved the request'
            ];
        }
        
        // Still waiting for approvals
        $remainingApprovals = collect($requiredApprovers)->reject(function($role) use ($approvedRoles) {
            return in_array($role, $approvedRoles);
        })->values()->toArray();
        
        return [
            'status' => 'pending_approval',
            'is_final' => false,
            'remaining_approvals' => $remainingApprovals
        ];
    }

    private function handleFinalApprovalDecision(EscalationRequest $escalation, array $finalStatus): void
    {
        $escalation->update([
            'final_decision_at' => now(),
            'final_outcome' => $finalStatus['outcome']
        ]);

        // Update the related threshold violation
        $escalation->thresholdViolation()->update([
            'status' => $finalStatus['outcome'] === 'approved' ? 'approved' : 'rejected'
        ]);

        // If rejected, create salary deduction
        if ($finalStatus['outcome'] === 'rejected') {
            $this->salaryService->createDeductionForRejectedEscalation($escalation);
        }

        // Notify the original requester
        if ($escalation->creator) {
            $escalation->creator->notify(new ApprovalDecisionNotification($escalation));
        }

        Log::info('Final approval decision processed', [
            'escalation_id' => $escalation->id,
            'final_outcome' => $finalStatus['outcome'],
            'amount' => $escalation->amount_requested,
            'requester' => $escalation->creator->name ?? 'Unknown'
        ]);
    }

    private function canUserApprove(EscalationRequest $escalation, User $user): bool
    {
        return in_array($user->role, $escalation->approval_required) 
            && $escalation->status === 'pending_approval'
            && $escalation->expires_at > now();
    }

    private function getApprovalStatus(EscalationRequest $escalation, User $user): array
    {
        $userDecision = $escalation->approvalDecisions()
            ->where('approver_id', $user->id)
            ->first();

        return [
            'user_decided' => $userDecision !== null,
            'user_decision' => $userDecision?->decision,
            'decided_at' => $userDecision?->decision_at,
            'can_decide' => $this->canUserApprove($escalation, $user),
            'required_approvals' => count($escalation->approval_required),
            'received_approvals' => $escalation->approvalDecisions()->where('decision', 'approved')->count(),
            'received_rejections' => $escalation->approvalDecisions()->where('decision', 'rejected')->count()
        ];
    }

    private function getOtherApprovals(EscalationRequest $escalation, User $user): array
    {
        return $escalation->approvalDecisions()
            ->where('approver_id', '!=', $user->id)
            ->get()
            ->map(function($decision) {
                return [
                    'approver' => $decision->approver->name,
                    'role' => $decision->approver_role,
                    'decision' => $decision->decision,
                    'decided_at' => $decision->decision_at
                ];
            })
            ->toArray();
    }

    private function calculateUrgencyLevel(EscalationRequest $escalation): string
    {
        $hoursRemaining = $escalation->expires_at->diffInHours(now());
        
        if ($hoursRemaining <= 2) {
            return 'critical';
        } elseif ($hoursRemaining <= 6) {
            return 'urgent';
        } elseif ($hoursRemaining <= 24) {
            return 'moderate';
        } else {
            return 'normal';
        }
    }

    private function getApprovalInstructions(EscalationRequest $escalation): array
    {
        $instructions = [];
        
        if ($escalation->overage_amount > 50000) {
            $instructions[] = "⚠️ HIGH VALUE: This request exceeds ₦50,000 overage - requires careful review";
        }
        
        if ($escalation->priority === 'critical') {
            $instructions[] = "🚨 CRITICAL: This request has been marked as critical priority";
        }
        
        if ($escalation->expires_at->diffInHours(now()) <= 6) {
            $instructions[] = "⏰ URGENT: This request expires within 6 hours";
        }
        
        $instructions[] = "📋 REVIEW: Verify business justification and supporting documentation";
        $instructions[] = "💰 IMPACT: Consider the financial impact and precedent this approval sets";
        $instructions[] = "🔍 VERIFY: Ensure the expense aligns with company policies and budget";
        
        return $instructions;
    }

    private function getDetailedApprovalStatus(EscalationRequest $escalation): array
    {
        $requiredApprovers = $escalation->approval_required;
        $decisions = $escalation->approvalDecisions;
        
        $status = [];
        
        foreach ($requiredApprovers as $role) {
            $decision = $decisions->where('approver_role', $role)->first();
            
            $status[$role] = [
                'required' => true,
                'decided' => $decision !== null,
                'decision' => $decision?->decision,
                'decided_at' => $decision?->decision_at,
                'approver' => $decision?->approver->name,
                'decision_reason' => $decision?->decision_reason
            ];
        }
        
        return $status;
    }

    private function getNextSteps(EscalationRequest $escalation, array $finalStatus): array
    {
        if ($finalStatus['is_final']) {
            if ($finalStatus['outcome'] === 'approved') {
                return [
                    'The escalation has been fully approved',
                    'Payment can now be processed',
                    'The original requester will be notified',
                    'The expense is now authorized'
                ];
            } else {
                return [
                    'The escalation has been rejected',
                    'Payment will remain blocked',
                    'Salary deduction will be applied',
                    'The original requester will be notified'
                ];
            }
        } else {
            $remaining = $finalStatus['remaining_approvals'];
            return [
                'Awaiting approval from: ' . implode(', ', $remaining),
                'Decision will be final once all approvers respond',
                'Escalation expires at: ' . $escalation->expires_at->format('M j, Y g:i A'),
                'Auto-rejection if not approved before expiration'
            ];
        }
    }
} 