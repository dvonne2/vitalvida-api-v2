<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseRequest extends Model
{
    protected $fillable = [
        'expense_id', 'requested_by', 'department', 'expense_type', 'amount',
        'vendor_supplier', 'vendor_phone', 'description', 'business_justification',
        'urgency_level', 'approval_status', 'fc_decision', 'gm_decision', 'ceo_decision',
        'final_status', 'submitted_at', 'approved_at', 'rejected_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime'
    ];

    public function requester()
    {
        return $this->belongsTo(Accountant::class, 'requested_by');
    }

    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('approval_status', 'rejected');
    }

    public function scopeByUrgency($query, $urgency)
    {
        return $query->where('urgency_level', $urgency);
    }

    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    public function generateExpenseId()
    {
        $lastExpense = self::orderBy('id', 'desc')->first();
        $number = $lastExpense ? intval(substr($lastExpense->expense_id, 4)) + 1 : 1;
        return 'EXP-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    public function approveByFC()
    {
        $this->update([
            'fc_decision' => 'approved',
            'approval_status' => 'approved',
            'approved_at' => now()
        ]);
    }

    public function approveByGM()
    {
        $this->update([
            'gm_decision' => 'approved',
            'approval_status' => 'approved',
            'approved_at' => now()
        ]);
    }

    public function approveByCEO()
    {
        $this->update([
            'ceo_decision' => 'approved',
            'approval_status' => 'approved',
            'approved_at' => now()
        ]);
    }

    public function reject($reason = null)
    {
        $this->update([
            'approval_status' => 'rejected',
            'rejected_at' => now()
        ]);
    }

    public function escalate()
    {
        $this->update([
            'approval_status' => 'escalated'
        ]);
    }

    public function getFormattedAmount()
    {
        return '₦' . number_format($this->amount, 2);
    }

    public function getUrgencyColor()
    {
        return match($this->urgency_level) {
            'critical' => 'red',
            'urgent' => 'orange',
            default => 'green'
        };
    }

    public function getUrgencyIcon()
    {
        return match($this->urgency_level) {
            'critical' => '🚨',
            'urgent' => '⚠️',
            default => '📋'
        };
    }

    public function getApprovalStatusColor()
    {
        return match($this->approval_status) {
            'approved' => 'green',
            'rejected' => 'red',
            'escalated' => 'orange',
            default => 'yellow'
        };
    }

    public function getApprovalStatusText()
    {
        return match($this->approval_status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'escalated' => 'Escalated',
            default => 'Pending'
        };
    }

    public function getFinalStatusColor()
    {
        return match($this->final_status) {
            'auto_approve' => 'green',
            'manager_review' => 'blue',
            'escalation' => 'orange',
            default => 'red'
        };
    }

    public function getFinalStatusText()
    {
        return match($this->final_status) {
            'auto_approve' => 'Auto Approved',
            'manager_review' => 'Manager Review',
            'escalation' => 'Escalation Required',
            default => 'Auto Blocked'
        };
    }

    public function isPending()
    {
        return $this->approval_status === 'pending';
    }

    public function isApproved()
    {
        return $this->approval_status === 'approved';
    }

    public function isRejected()
    {
        return $this->approval_status === 'rejected';
    }

    public function isEscalated()
    {
        return $this->approval_status === 'escalated';
    }

    public function isCritical()
    {
        return $this->urgency_level === 'critical';
    }

    public function isUrgent()
    {
        return $this->urgency_level === 'urgent';
    }

    public function needsFCApproval()
    {
        return $this->amount <= 5000 && $this->approval_status === 'pending';
    }

    public function needsGMApproval()
    {
        return $this->amount > 5000 && $this->amount <= 10000 && $this->approval_status === 'pending';
    }

    public function needsCEOApproval()
    {
        return $this->amount > 10000 && $this->approval_status === 'pending';
    }

    public function getApprovalTier()
    {
        return match(true) {
            $this->amount <= 5000 => 'fc',
            $this->amount <= 10000 => 'gm',
            default => 'ceo'
        };
    }

    public function getFormattedSubmittedDate()
    {
        return $this->submitted_at->format('M d, Y H:i');
    }
} 