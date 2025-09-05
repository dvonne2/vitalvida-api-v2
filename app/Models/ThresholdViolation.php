<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThresholdViolation extends Model
{
    protected $fillable = [
        'cost_type',
        'cost_category', 
        'amount',
        'threshold_limit',
        'overage_amount',
        'violation_details',
        'status',
        'created_by',
        'reference_id',
        'reference_type',
        'approved_at',
        'rejected_at',
        'approved_amount',
        'rejection_reason'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'threshold_limit' => 'decimal:2',
        'overage_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'violation_details' => 'array'
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function escalationRequests(): HasMany
    {
        return $this->hasMany(EscalationRequest::class);
    }

    public function salaryDeductions(): HasMany
    {
        return $this->hasMany(SalaryDeduction::class, 'violation_id');
    }

    /**
     * Check if violation is currently blocked
     */
    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    /**
     * Check if violation has been approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Get violation severity based on overage percentage
     */
    public function getSeverity(): string
    {
        if ($this->threshold_limit <= 0) return 'unknown';
        
        $percentage = ($this->overage_amount / $this->threshold_limit) * 100;
        
        return match(true) {
            $percentage > 100 => 'critical',
            $percentage > 50 => 'high',
            $percentage > 25 => 'medium',
            default => 'low'
        };
    }
} 