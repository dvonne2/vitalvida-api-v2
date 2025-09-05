<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BonusApprovalRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'bonus_ids',
        'total_amount',
        'approval_tier',
        'required_approvers',
        'justification',
        'status',
        'expires_at',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'approval_comments',
        'rejection_reason',
        'adjusted_amount'
    ];

    protected $casts = [
        'bonus_ids' => 'array',
        'total_amount' => 'decimal:2',
        'required_approvers' => 'array',
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'adjusted_amount' => 'decimal:2'
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function bonuses(): HasMany
    {
        return $this->hasMany(Bonus::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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