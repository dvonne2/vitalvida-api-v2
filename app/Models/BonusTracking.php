<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusTracking extends Model
{
    protected $table = 'bonus_tracking';

    protected $fillable = [
        'accountant_id', 'week_start_date', 'week_end_date', 'goal_amount',
        'criteria_met', 'total_criteria', 'payment_matching_accuracy',
        'escalation_discipline_score', 'documentation_integrity_score',
        'bonus_log_accuracy', 'bonus_amount', 'bonus_status',
        'fc_approved', 'fc_approved_at', 'paid_at'
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'goal_amount' => 'decimal:2',
        'criteria_met' => 'integer',
        'total_criteria' => 'integer',
        'payment_matching_accuracy' => 'decimal:2',
        'escalation_discipline_score' => 'decimal:2',
        'documentation_integrity_score' => 'decimal:2',
        'bonus_log_accuracy' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'fc_approved' => 'boolean',
        'fc_approved_at' => 'datetime',
        'paid_at' => 'datetime'
    ];

    public function accountant()
    {
        return $this->belongsTo(Accountant::class, 'accountant_id');
    }

    public function scopeEligible($query)
    {
        return $query->where('bonus_status', 'eligible');
    }

    public function scopePaid($query)
    {
        return $query->where('bonus_status', 'paid');
    }

    public function scopePending($query)
    {
        return $query->where('bonus_status', 'pending');
    }

    public function scopeThisWeek($query)
    {
        return $query->where('week_start_date', now()->startOfWeek()->toDateString())
                    ->where('week_end_date', now()->endOfWeek()->toDateString());
    }

    public function scopeByAccountant($query, $accountantId)
    {
        return $query->where('accountant_id', $accountantId);
    }

    public function approveByFC($approvedBy = null)
    {
        $this->update([
            'fc_approved' => true,
            'fc_approved_at' => now()
        ]);

        // If all criteria are met and FC approved, mark as eligible
        if ($this->criteria_met >= $this->total_criteria) {
            $this->update(['bonus_status' => 'eligible']);
        }
    }

    public function markAsPaid()
    {
        $this->update([
            'bonus_status' => 'paid',
            'paid_at' => now()
        ]);
    }

    public function calculateBonusAmount()
    {
        // Base bonus amount is ₦10,000 if all criteria are met
        if ($this->criteria_met >= $this->total_criteria) {
            $this->update(['bonus_amount' => 10000.00]);
            return 10000.00;
        }
        
        // Partial bonus based on criteria met
        $partialAmount = ($this->criteria_met / $this->total_criteria) * 10000.00;
        $this->update(['bonus_amount' => $partialAmount]);
        return $partialAmount;
    }

    public function getBonusStatusColor()
    {
        return match($this->bonus_status) {
            'eligible' => 'green',
            'paid' => 'blue',
            'not_eligible' => 'red',
            default => 'yellow'
        };
    }

    public function getBonusStatusText()
    {
        return match($this->bonus_status) {
            'eligible' => 'Eligible',
            'paid' => 'Paid',
            'not_eligible' => 'Not Eligible',
            default => 'Pending'
        };
    }

    public function getFormattedBonusAmount()
    {
        return '₦' . number_format($this->bonus_amount, 2);
    }

    public function getFormattedWeekRange()
    {
        return $this->week_start_date->format('M d') . ' - ' . $this->week_end_date->format('M d, Y');
    }

    public function getCriteriaProgress()
    {
        return [
            'met' => $this->criteria_met,
            'total' => $this->total_criteria,
            'percentage' => round(($this->criteria_met / $this->total_criteria) * 100, 1)
        ];
    }

    public function getCriteriaDetails()
    {
        return [
            [
                'name' => 'Payment Matching Accuracy',
                'score' => $this->payment_matching_accuracy,
                'target' => 98,
                'met' => $this->payment_matching_accuracy >= 98,
                'description' => 'Maintain 98%+ payment matching accuracy'
            ],
            [
                'name' => 'Escalation Discipline',
                'score' => $this->escalation_discipline_score,
                'target' => 100,
                'met' => $this->escalation_discipline_score >= 100,
                'description' => 'No unauthorized payments over thresholds'
            ],
            [
                'name' => 'Documentation Integrity',
                'score' => $this->documentation_integrity_score,
                'target' => 100,
                'met' => $this->documentation_integrity_score >= 100,
                'description' => '100% receipt upload compliance'
            ],
            [
                'name' => 'Bonus Log Accuracy',
                'score' => $this->bonus_log_accuracy,
                'target' => 100,
                'met' => $this->bonus_log_accuracy >= 100,
                'description' => 'All bonuses pre-approved by FC'
            ]
        ];
    }

    public function isEligible()
    {
        return $this->bonus_status === 'eligible';
    }

    public function isPaid()
    {
        return $this->bonus_status === 'paid';
    }

    public function isPending()
    {
        return $this->bonus_status === 'pending';
    }

    public function isNotEligible()
    {
        return $this->bonus_status === 'not_eligible';
    }

    public function needsFCApproval()
    {
        return $this->criteria_met >= $this->total_criteria && !$this->fc_approved;
    }

    public function canBePaid()
    {
        return $this->bonus_status === 'eligible' && $this->fc_approved;
    }
} 