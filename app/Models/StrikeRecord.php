<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StrikeRecord extends Model
{
    protected $fillable = [
        'accountant_id', 'strike_number', 'violation_type', 'violation_description',
        'penalty_amount', 'order_id', 'evidence', 'status', 'issued_date',
        'resolved_date', 'issued_by'
    ];

    protected $casts = [
        'penalty_amount' => 'decimal:2',
        'evidence' => 'array',
        'issued_date' => 'date',
        'resolved_date' => 'date'
    ];

    public function accountant()
    {
        return $this->belongsTo(Accountant::class, 'accountant_id');
    }

    public function issuedBy()
    {
        return $this->belongsTo(Accountant::class, 'issued_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('issued_date', [now()->startOfMonth(), now()->endOfMonth()]);
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('issued_date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeByViolationType($query, $type)
    {
        return $query->where('violation_type', $type);
    }

    public function resolve($resolvedBy = null)
    {
        $this->update([
            'status' => 'resolved',
            'resolved_date' => now()->toDateString()
        ]);

        // Potentially reduce accountant's strike count
        $this->accountant->decrement('current_strikes');
        $this->accountant->decrement('total_penalties', $this->penalty_amount);
    }

    public function dispute($reason)
    {
        $this->update([
            'status' => 'disputed',
            'evidence' => array_merge($this->evidence ?? [], ['dispute_reason' => $reason])
        ]);
    }

    public function getViolationDisplayName()
    {
        return match($this->violation_type) {
            'payment_mismatch' => 'Payment Mismatch',
            'late_reconciliation' => 'Late Reconciliation',
            'missing_receipt' => 'Missing Receipt',
            'documentation_integrity' => 'Documentation Integrity',
            'bonus_log_error' => 'Bonus Log Error',
            default => 'Unknown Violation'
        };
    }

    public function getPenaltyColor()
    {
        return match($this->violation_type) {
            'payment_mismatch' => 'red',
            'late_reconciliation' => 'orange',
            'missing_receipt' => 'yellow',
            'documentation_integrity' => 'purple',
            'bonus_log_error' => 'pink',
            default => 'gray'
        };
    }

    public function getStatusColor()
    {
        return match($this->status) {
            'active' => 'red',
            'resolved' => 'green',
            'disputed' => 'orange',
            default => 'gray'
        };
    }

    public function getStatusText()
    {
        return match($this->status) {
            'active' => 'Active',
            'resolved' => 'Resolved',
            'disputed' => 'Disputed',
            default => 'Unknown'
        };
    }

    public function getFormattedPenaltyAmount()
    {
        return '₦' . number_format($this->penalty_amount, 2);
    }

    public function getFormattedIssuedDate()
    {
        return $this->issued_date->format('M d, Y');
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isResolved()
    {
        return $this->status === 'resolved';
    }

    public function isDisputed()
    {
        return $this->status === 'disputed';
    }

    public function getEvidenceSummary()
    {
        if (!$this->evidence) return 'No evidence provided';
        
        $summary = [];
        foreach ($this->evidence as $key => $value) {
            if ($key !== 'dispute_reason') {
                $summary[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
            }
        }
        
        return implode(', ', $summary);
    }

    public function getDisputeReason()
    {
        return $this->evidence['dispute_reason'] ?? null;
    }
} 