<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bonus extends Model
{
    protected $fillable = [
        'employee_id',
        'bonus_type',
        'description',
        'amount',
        'earned_month',
        'calculation_basis',
        'requires_approval',
        'status',
        'calculated_by',
        'calculated_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'approval_comments',
        'rejection_reason',
        'paid_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'earned_month' => 'date',
        'calculation_basis' => 'array',
        'requires_approval' => 'boolean',
        'calculated_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'paid_at' => 'datetime'
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(BonusApprovalRequest::class);
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
} 