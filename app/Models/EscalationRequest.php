<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EscalationRequest extends Model
{
    protected $fillable = [
        'threshold_violation_id', 'escalation_type', 'reference_id', 'location',
        'amount_requested', 'threshold_limit', 'overage_amount', 'approval_required',
        'escalation_reason', 'business_justification', 'status', 'priority',
        'expires_at', 'created_by', 'final_decision_at', 'final_outcome',
        'rejection_reason', 'contact_info', 'fc_decision', 'gm_decision',
        'submitted_by', 'fc_reviewed_at', 'gm_reviewed_at'
    ];

    protected $casts = [
        'amount_requested' => 'decimal:2',
        'threshold_limit' => 'decimal:2',
        'overage_amount' => 'decimal:2',
        'approval_required' => 'array',
        'contact_info' => 'array',
        'expires_at' => 'datetime',
        'final_decision_at' => 'datetime',
        'fc_reviewed_at' => 'datetime',
        'gm_reviewed_at' => 'datetime'
    ];

    public function submitter()
    {
        return $this->belongsTo(Accountant::class, 'submitted_by');
    }

    public function scopePendingFC($query)
    {
        return $query->where('status', 'pending_approval')->where('fc_decision', 'pending');
    }

    public function scopePendingGM($query)
    {
        return $query->where('status', 'pending_approval')->where('gm_decision', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('escalation_type', $type);
    }

    public function approveByFC()
    {
        $this->update([
            'fc_decision' => 'approved',
            'escalation_status' => 'pending_gm',
            'fc_reviewed_at' => now()
        ]);
    }

    public function approveByGM()
    {
        $this->update([
            'gm_decision' => 'approved',
            'escalation_status' => 'approved',
            'gm_reviewed_at' => now()
        ]);
    }

    public function rejectByFC($reason = null)
    {
        $this->update([
            'fc_decision' => 'rejected',
            'escalation_status' => 'rejected',
            'fc_reviewed_at' => now()
        ]);
    }

    public function rejectByGM($reason = null)
    {
        $this->update([
            'gm_decision' => 'rejected',
            'escalation_status' => 'rejected',
            'gm_reviewed_at' => now()
        ]);
    }

    public function getFormattedRequestedAmount()
    {
        return '₦' . number_format($this->amount_requested, 2);
    }

    public function getFormattedOverageAmount()
    {
        return '₦' . number_format($this->overage_amount, 2);
    }

    public function getFormattedOurLimit()
    {
        return '₦' . number_format($this->threshold_limit, 2);
    }

    public function getEscalationTypeDisplayName()
    {
        return match($this->escalation_type) {
            'storekeeper_fee' => 'Storekeeper Fee',
            'transport_cost' => 'Transport Cost',
            'other_expense' => 'Other Expense',
            default => 'Unknown'
        };
    }

    public function getEscalationTypeIcon()
    {
        return match($this->escalation_type) {
            'storekeeper_fee' => '🏪',
            'transport_cost' => '🚚',
            'other_expense' => '💰',
            default => '📋'
        };
    }

    public function getEscalationStatusColor()
    {
        return match($this->status) {
            'approved' => 'green',
            'rejected' => 'red',
            'pending_approval' => 'yellow',
            default => 'gray'
        };
    }

    public function getEscalationStatusText()
    {
        return match($this->status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'pending_approval' => 'Pending Approval',
            default => 'Unknown'
        };
    }

    public function getOveragePercentage()
    {
        if ($this->threshold_limit == 0) return 0;
        return round(($this->overage_amount / $this->threshold_limit) * 100, 1);
    }

    public function getContactInfoSummary()
    {
        if (!$this->contact_info) return 'No contact info provided';
        
        $summary = [];
        if (isset($this->contact_info['transport_phone'])) {
            $summary[] = 'Transport: ' . $this->contact_info['transport_phone'];
        }
        if (isset($this->contact_info['storekeeper_phone'])) {
            $summary[] = 'Storekeeper: ' . $this->contact_info['storekeeper_phone'];
        }
        
        return implode(', ', $summary);
    }

    public function isPendingFC()
    {
        return $this->status === 'pending_approval' && $this->fc_decision === 'pending';
    }

    public function isPendingGM()
    {
        return $this->status === 'pending_approval' && $this->gm_decision === 'pending';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    public function needsFCApproval()
    {
        return $this->status === 'pending_approval' && $this->fc_decision === 'pending';
    }

    public function needsGMApproval()
    {
        return $this->status === 'pending_approval' && $this->gm_decision === 'pending';
    }

    public function getFormattedSubmittedDate()
    {
        return $this->created_at->format('M d, Y H:i');
    }

    public function getProcessingTime()
    {
        if ($this->isApproved() || $this->isRejected()) {
            $endTime = $this->final_decision_at ?? $this->gm_reviewed_at ?? $this->fc_reviewed_at;
            return $this->created_at->diffInHours($endTime);
        }
        return $this->created_at->diffInHours(now());
    }

    public function isOverdue()
    {
        return $this->getProcessingTime() > 24; // 24 hours
    }
} 