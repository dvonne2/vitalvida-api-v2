<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDecision extends Model
{
    protected $fillable = [
        'workflow_id',
        'approver_id',
        'decision',
        'comments',
        'decision_at',
        'metadata'
    ];

    protected $casts = [
        'decision_at' => 'datetime',
        'metadata' => 'array'
    ];

    public function approvalWorkflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    // Legacy method for backward compatibility
    public function escalationRequest(): BelongsTo
    {
        // Route through the approval workflow to get the escalation
        return $this->approvalWorkflow->escalationRequest();
    }

    /**
     * Check if decision is approval
     */
    public function isApproval(): bool
    {
        return $this->decision === 'approve';
    }

    /**
     * Check if decision is rejection
     */
    public function isRejection(): bool
    {
        return $this->decision === 'reject';
    }

    /**
     * Get formatted decision time
     */
    public function getDecisionTimeAttribute(): string
    {
        return $this->decision_at ? $this->decision_at->diffForHumans() : 'Pending';
    }
} 