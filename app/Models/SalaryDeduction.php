<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryDeduction extends Model
{
    protected $fillable = [
        'user_id',
        'violation_id',
        'amount',
        'reason',
        'status',
        'deduction_date',
        'processed_date',
        'metadata'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deduction_date' => 'datetime',
        'processed_date' => 'datetime',
        'metadata' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(ThresholdViolation::class, 'violation_id');
    }

    /**
     * Check if deduction is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if deduction has been processed
     */
    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return '₦' . number_format($this->amount, 2);
    }

    /**
     * Get time until deduction
     */
    public function getTimeUntilDeductionAttribute(): string
    {
        if ($this->isPending() && $this->deduction_date) {
            return $this->deduction_date->diffForHumans();
        }
        return 'N/A';
    }
} 