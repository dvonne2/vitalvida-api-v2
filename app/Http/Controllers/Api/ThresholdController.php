<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ThresholdValidationService;
use App\Models\ThresholdViolation;
use App\Models\EscalationRequest;
use App\Models\ApprovalDecision;
use Illuminate\Support\Facades\Log;

class ThresholdController extends Controller
{
    protected $thresholdService;

    public function __construct(ThresholdValidationService $thresholdService)
    {
        $this->thresholdService = $thresholdService;
    }

    /**
     * Health check endpoint
     */
    public function health()
    {
        return response()->json([
            'status' => 'healthy',
            'service' => 'Threshold Enforcement System',
            'version' => '1.0.0',
            'timestamp' => now()
        ]);
    }

    /**
     * Validate cost against thresholds
     */
    public function validateCost(Request $request)
    {
        $request->validate([
            'type' => 'required|in:logistics,expense,bonus',
            'amount' => 'required|numeric|min:0',
            'category' => 'nullable|string',
            'quantity' => 'nullable|numeric|min:1',
            'storekeeper_fee' => 'nullable|numeric|min:0',
            'transport_fare' => 'nullable|numeric|min:0',
            'context' => 'nullable|array'
        ]);

        $costData = [
            'type' => $request->input('type'),
            'amount' => $request->input('amount'),
            'category' => $request->input('category'),
            'quantity' => $request->input('quantity', 1),
            'storekeeper_fee' => $request->input('storekeeper_fee', 0),
            'transport_fare' => $request->input('transport_fare', 0),
            'user_id' => $request->user()->id,
            'reference_type' => 'validation_test',
            'reference_id' => null
        ];

        try {
            $validation = $this->thresholdService->validateCost($costData);
            
            return response()->json([
                'validation_result' => $validation,
                'timestamp' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Threshold validation failed', [
                'error' => $e->getMessage(),
                'cost_data' => $costData
            ]);

            return response()->json([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get threshold violations
     */
    public function getViolations(Request $request)
    {
        $violations = ThresholdViolation::with(['creator', 'escalationRequests'])
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->cost_type, function ($query, $costType) {
                return $query->where('cost_type', $costType);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'violations' => $violations,
            'summary' => [
                'total' => ThresholdViolation::count(),
                'blocked' => ThresholdViolation::where('status', 'blocked')->count(),
                'approved' => ThresholdViolation::where('status', 'approved')->count(),
                'rejected' => ThresholdViolation::where('status', 'rejected')->count(),
                'total_overage' => ThresholdViolation::sum('overage_amount')
            ]
        ]);
    }

    /**
     * Get escalation requests
     */
    public function getEscalations(Request $request)
    {
        $escalations = EscalationRequest::with(['thresholdViolation', 'creator', 'approvalDecisions.approver'])
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->priority, function ($query, $priority) {
                return $query->where('priority', $priority);
            })
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'escalations' => $escalations,
            'summary' => [
                'total' => EscalationRequest::count(),
                'pending' => EscalationRequest::where('status', 'pending_approval')->count(),
                'approved' => EscalationRequest::where('status', 'approved')->count(),
                'rejected' => EscalationRequest::where('status', 'rejected')->count(),
                'expired' => EscalationRequest::where('status', 'expired')->count()
            ]
        ]);
    }

    /**
     * Get pending approvals for current user
     */
    public function getPendingApprovals(Request $request)
    {
        $user = $request->user();
        
        if (!in_array($user->role, ['fc', 'gm', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized - approver role required'], 403);
        }

        $pendingEscalations = EscalationRequest::with(['thresholdViolation', 'creator', 'approvalDecisions.approver'])
            ->where('status', 'pending_approval')
            ->where('expires_at', '>', now())
            ->whereJsonContains('approval_required', $user->role)
            ->whereDoesntHave('approvalDecisions', function ($query) use ($user) {
                $query->where('approver_id', $user->id);
            })
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'pending_escalations' => $pendingEscalations,
            'summary' => [
                'total_pending' => $pendingEscalations->count(),
                'critical' => $pendingEscalations->where('priority', 'critical')->count(),
                'high' => $pendingEscalations->where('priority', 'high')->count(),
                'expiring_soon' => $pendingEscalations->where('expires_at', '<', now()->addHours(12))->count()
            ]
        ]);
    }

    /**
     * Approve or reject escalation
     */
    public function processApproval(Request $request, EscalationRequest $escalation)
    {
        $user = $request->user();
        
        $request->validate([
            'decision' => 'required|in:approved,rejected',
            'reason' => 'nullable|string|max:1000'
        ]);

        if (!in_array($user->role, ['fc', 'gm', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized - approver role required'], 403);
        }

        // Check if escalation is still pending
        if ($escalation->status !== 'pending_approval') {
            return response()->json(['error' => 'Escalation is no longer pending'], 400);
        }

        // Check if escalation has expired
        if ($escalation->expires_at < now()) {
            return response()->json(['error' => 'Escalation has expired'], 400);
        }

        // Check if user is required approver
        if (!in_array($user->role, $escalation->approval_required)) {
            return response()->json(['error' => 'Not authorized to approve this escalation'], 403);
        }

        // Check if user has already decided
        $existingDecision = ApprovalDecision::where('escalation_request_id', $escalation->id)
            ->where('approver_id', $user->id)
            ->first();

        if ($existingDecision) {
            return response()->json(['error' => 'You have already made a decision on this escalation'], 400);
        }

        try {
            // Record the decision
            $decision = ApprovalDecision::create([
                'escalation_request_id' => $escalation->id,
                'approver_id' => $user->id,
                'approver_role' => $user->role,
                'decision' => $request->decision,
                'decision_reason' => $request->reason,
                'decision_at' => now()
            ]);

            // Check if all required approvals are received or if any rejection
            $allDecisions = ApprovalDecision::where('escalation_request_id', $escalation->id)->get();
            $requiredApprovers = $escalation->approval_required;
            $decidedApprovers = $allDecisions->pluck('approver_role')->toArray();
            $rejections = $allDecisions->where('decision', 'rejected');

            if ($rejections->count() > 0) {
                // Any rejection means escalation is rejected
                $escalation->update([
                    'status' => 'rejected',
                    'final_decision_at' => now(),
                    'final_outcome' => 'rejected',
                    'rejection_reason' => $rejections->first()->decision_reason
                ]);

                $escalation->thresholdViolation->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'rejection_reason' => $rejections->first()->decision_reason
                ]);

                return response()->json([
                    'message' => 'Escalation rejected',
                    'decision' => $decision,
                    'escalation_status' => 'rejected'
                ]);
            }

            // Check if all required approvals are received
            $allApproved = count(array_intersect($requiredApprovers, $decidedApprovers)) === count($requiredApprovers);

            if ($allApproved) {
                // All required approvals received
                $escalation->update([
                    'status' => 'approved',
                    'final_decision_at' => now(),
                    'final_outcome' => 'approved'
                ]);

                $escalation->thresholdViolation->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_amount' => $escalation->amount_requested
                ]);

                return response()->json([
                    'message' => 'Escalation fully approved - payment authorized',
                    'decision' => $decision,
                    'escalation_status' => 'approved'
                ]);
            }

            // Still waiting for more approvals
            return response()->json([
                'message' => 'Your approval recorded - waiting for additional approvals',
                'decision' => $decision,
                'escalation_status' => 'pending_approval',
                'pending_approvers' => array_diff($requiredApprovers, $decidedApprovers)
            ]);

        } catch (\Exception $e) {
            Log::error('Approval processing failed', [
                'escalation_id' => $escalation->id,
                'approver_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to process approval',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get threshold statistics
     */
    public function getStatistics()
    {
        $violations = ThresholdViolation::selectRaw('
            COUNT(*) as total_violations,
            SUM(CASE WHEN status = "blocked" THEN 1 ELSE 0 END) as blocked,
            SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected,
            SUM(overage_amount) as total_overage,
            AVG(overage_amount) as avg_overage
        ')->first();

        $escalations = EscalationRequest::selectRaw('
            COUNT(*) as total_escalations,
            SUM(CASE WHEN status = "pending_approval" THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = "expired" THEN 1 ELSE 0 END) as expired
        ')->first();

        $recentViolations = ThresholdViolation::with(['creator'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'violations' => $violations,
            'escalations' => $escalations,
            'recent_violations' => $recentViolations,
            'compliance_rate' => $violations->total_violations > 0 ? 
                round((($violations->approved + $violations->rejected) / $violations->total_violations) * 100, 2) : 100
        ]);
    }

    /**
     * Get urgent items requiring immediate attention
     */
    public function getUrgentItems()
    {
        $urgentEscalations = EscalationRequest::with(['thresholdViolation', 'creator'])
            ->where('status', 'pending_approval')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<', now()->addHours(12))
            ->orderBy('expires_at', 'asc')
            ->get();

        $criticalViolations = ThresholdViolation::with(['creator', 'escalationRequests'])
            ->whereRaw('(overage_amount / threshold_limit) > 1.0')
            ->where('status', 'blocked')
            ->orderBy('overage_amount', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'urgent_escalations' => $urgentEscalations,
            'critical_violations' => $criticalViolations,
            'summary' => [
                'expiring_soon' => $urgentEscalations->count(),
                'critical_overages' => $criticalViolations->count()
            ]
        ]);
    }
}
