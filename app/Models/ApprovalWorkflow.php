<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class ApprovalWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'violation_id',
        'workflow_type',
        'required_approvers',
        'timeout_hours',
        'auto_reject_on_timeout',
        'status',
        'expires_at',
        'approvals_received',
        'rejections_received',
        'completed_at',
        'metadata'
    ];

    protected $casts = [
        'required_approvers' => 'array',
        'approvals_received' => 'array',
        'rejections_received' => 'array',
        'auto_reject_on_timeout' => 'boolean',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_TIMEOUT_REJECTED = 'timeout_rejected';

    // Workflow type constants
    const TYPE_FC_GM_DUAL = 'fc_gm_dual';
    const TYPE_GM_CEO_DUAL = 'gm_ceo_dual';
    const TYPE_FC_ONLY = 'fc_only';
    const TYPE_GM_ONLY = 'gm_only';

    /**
     * Get the threshold violation this workflow belongs to
     */
    public function violation(): BelongsTo
    {
        return $this->belongsTo(ThresholdViolation::class);
    }

    /**
     * Get approval decisions for this workflow
     */
    public function approvalDecisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class);
    }

    /**
     * Scope for pending workflows
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for completed workflows
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_TIMEOUT_REJECTED]);
    }

    /**
     * Scope for expired workflows
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
                    ->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for workflows expiring soon
     */
    public function scopeExpiringSoon($query, int $hours = 12)
    {
        return $query->where('expires_at', '<=', now()->addHours($hours))
                    ->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope by workflow type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('workflow_type', $type);
    }

    /**
     * Check if workflow is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if workflow is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if workflow is rejected
     */
    public function isRejected(): bool
    {
        return in_array($this->status, [self::STATUS_REJECTED, self::STATUS_TIMEOUT_REJECTED]);
    }

    /**
     * Check if workflow is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if workflow expires soon
     */
    public function expiresSoon(int $hours = 12): bool
    {
        return $this->expires_at && $this->expires_at->diffInHours(now()) <= $hours;
    }

    /**
     * Get remaining time until expiry
     */
    public function getRemainingTimeAttribute(): ?string
    {
        if (!$this->expires_at) {
            return null;
        }

        if ($this->isExpired()) {
            return 'Expired';
        }

        return $this->expires_at->diffForHumans();
    }

    /**
     * Get approval progress
     */
    public function getApprovalProgressAttribute(): array
    {
        $totalRequired = count($this->required_approvers);
        $approvalsReceived = count($this->approvals_received);
        $rejectionsReceived = count($this->rejections_received);

        return [
            'total_required' => $totalRequired,
            'approvals_received' => $approvalsReceived,
            'rejections_received' => $rejectionsReceived,
            'pending_approvals' => $totalRequired - $approvalsReceived - $rejectionsReceived,
            'progress_percentage' => $totalRequired > 0 ? 
                round((($approvalsReceived + $rejectionsReceived) / $totalRequired) * 100, 2) : 0
        ];
    }

    /**
     * Get workflow description
     */
    public function getWorkflowDescriptionAttribute(): string
    {
        $descriptions = [
            self::TYPE_FC_GM_DUAL => 'FC + GM Dual Approval Required',
            self::TYPE_GM_CEO_DUAL => 'GM + CEO Dual Approval Required',
            self::TYPE_FC_ONLY => 'FC Approval Required',
            self::TYPE_GM_ONLY => 'GM Approval Required'
        ];

        return $descriptions[$this->workflow_type] ?? 'Unknown workflow type';
    }

    /**
     * Get status badge color
     */
    public function getStatusBadgeColorAttribute(): string
    {
        $colors = [
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_TIMEOUT_REJECTED => 'secondary'
        ];

        return $colors[$this->status] ?? 'info';
    }

    /**
     * Get workflow age
     */
    public function getWorkflowAgeAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get completion time (if completed)
     */
    public function getCompletionTimeAttribute(): ?string
    {
        if ($this->completed_at) {
            return $this->completed_at->diffForHumans();
        }
        return null;
    }

    /**
     * Check if all required approvals received
     */
    public function hasAllApprovals(): bool
    {
        return count($this->approvals_received) === count($this->required_approvers);
    }

    /**
     * Check if any rejections received
     */
    public function hasAnyRejections(): bool
    {
        return count($this->rejections_received) > 0;
    }

    /**
     * Get pending approvers
     */
    public function getPendingApproversAttribute(): array
    {
        $allApprovers = $this->required_approvers;
        $respondedApprovers = array_merge($this->approvals_received, $this->rejections_received);
        
        return array_diff($allApprovers, $respondedApprovers);
    }

    /**
     * Add approval decision
     */
    public function addApproval(int $approverId, ?string $comments = null): void
    {
        if (!in_array($approverId, $this->approvals_received)) {
            $this->approvals_received[] = $approverId;
            $this->save();

            // Log approval decision
            ApprovalDecision::create([
                'workflow_id' => $this->id,
                'approver_id' => $approverId,
                'decision' => 'approve',
                'comments' => $comments,
                'decision_at' => now()
            ]);
        }
    }

    /**
     * Add rejection decision
     */
    public function addRejection(int $approverId, ?string $comments = null): void
    {
        if (!in_array($approverId, $this->rejections_received)) {
            $this->rejections_received[] = $approverId;
            $this->save();

            // Log rejection decision
            ApprovalDecision::create([
                'workflow_id' => $this->id,
                'approver_id' => $approverId,
                'decision' => 'reject',
                'comments' => $comments,
                'decision_at' => now()
            ]);
        }
    }

    /**
     * Mark workflow as approved
     */
    public function markAsApproved(): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'completed_at' => now()
        ]);
    }

    /**
     * Mark workflow as rejected
     */
    public function markAsRejected(string $reason = 'Manual rejection'): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'completed_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], ['rejection_reason' => $reason])
        ]);
    }

    /**
     * Mark workflow as timeout rejected
     */
    public function markAsTimeoutRejected(): void
    {
        $this->update([
            'status' => self::STATUS_TIMEOUT_REJECTED,
            'completed_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], ['timeout_reason' => 'Auto-rejected due to timeout'])
        ]);
    }

    /**
     * Get workflow statistics
     */
    public static function getWorkflowStatistics(): array
    {
        $totalWorkflows = self::count();
        $pendingWorkflows = self::pending()->count();
        $approvedWorkflows = self::where('status', self::STATUS_APPROVED)->count();
        $rejectedWorkflows = self::whereIn('status', [self::STATUS_REJECTED, self::STATUS_TIMEOUT_REJECTED])->count();
        $expiredWorkflows = self::expired()->count();

        return [
            'total_workflows' => $totalWorkflows,
            'pending_workflows' => $pendingWorkflows,
            'approved_workflows' => $approvedWorkflows,
            'rejected_workflows' => $rejectedWorkflows,
            'expired_workflows' => $expiredWorkflows,
            'approval_rate' => $totalWorkflows > 0 ? 
                round(($approvedWorkflows / $totalWorkflows) * 100, 2) : 0,
            'rejection_rate' => $totalWorkflows > 0 ? 
                round(($rejectedWorkflows / $totalWorkflows) * 100, 2) : 0
        ];
    }

    /**
     * Get workflows by type
     */
    public static function getWorkflowsByType(): array
    {
        return self::selectRaw('workflow_type, COUNT(*) as count')
            ->groupBy('workflow_type')
            ->get()
            ->toArray();
    }

    /**
     * Get recent workflows
     */
    public static function getRecentWorkflows(int $limit = 10): array
    {
        return self::with(['violation'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get urgent workflows (expiring soon)
     */
    public static function getUrgentWorkflows(int $hours = 12): array
    {
        return self::with(['violation'])
            ->expiringSoon($hours)
            ->get()
            ->toArray();
    }

    /**
     * Process timeout rejections
     */
    public static function processTimeoutRejections(): int
    {
        $expiredWorkflows = self::expired()->get();
        $processedCount = 0;

        foreach ($expiredWorkflows as $workflow) {
            if ($workflow->auto_reject_on_timeout) {
                $workflow->markAsTimeoutRejected();
                
                // Update violation status
                $workflow->violation->update([
                    'status' => ThresholdViolation::STATUS_TIMEOUT_REJECTED,
                    'rejected_at' => now()
                ]);

                $processedCount++;
            }
        }

        return $processedCount;
    }
} 