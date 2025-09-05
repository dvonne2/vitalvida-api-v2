<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class BonusLog extends Model
{
    use HasFactory;

    protected $table = 'bonus_logs';

    protected $fillable = [
        'user_id',
        'bonus_type',
        'amount',
        'currency',
        'status',
        'description',
        'calculation_data',
        'period_start',
        'period_end',
        'approved_by',
        'approved_at',
        'paid_at',
        'payment_reference'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'calculation_data' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime'
    ];

    // Bonus type constants
    const TYPE_DELIVERY = 'delivery';
    const TYPE_SALES = 'sales';
    const TYPE_PERFORMANCE = 'performance';
    const TYPE_REFERRAL = 'referral';
    const TYPE_SPECIAL = 'special';

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the user that owns the bonus
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who approved the bonus
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope for pending bonuses
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for approved bonuses
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for paid bonuses
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Scope by bonus type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('bonus_type', $type);
    }

    /**
     * Scope by period
     */
    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->where(function($q) use ($startDate, $endDate) {
            $q->whereBetween('period_start', [$startDate, $endDate])
              ->orWhereBetween('period_end', [$startDate, $endDate])
              ->orWhere(function($q2) use ($startDate, $endDate) {
                  $q2->where('period_start', '<=', $startDate)
                     ->where('period_end', '>=', $endDate);
              });
        });
    }

    /**
     * Check if bonus is pending approval
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if bonus is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if bonus has been paid
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmountAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }

    /**
     * Get period description
     */
    public function getPeriodDescriptionAttribute(): string
    {
        if (!$this->period_start || !$this->period_end) {
            return 'One-time bonus';
        }

        return $this->period_start->format('M j') . ' - ' . $this->period_end->format('M j, Y');
    }

    /**
     * Get days since created
     */
    public function getDaysOldAttribute(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Check if bonus requires approval
     */
    public function requiresApproval(): bool
    {
        // Performance bonuses >₦15,000 require GM approval
        if ($this->bonus_type === self::TYPE_PERFORMANCE && $this->amount > 15000) {
            return true;
        }

        // Special bonuses >₦10,000 require approval
        if ($this->bonus_type === self::TYPE_SPECIAL && $this->amount > 10000) {
            return true;
        }

        // All bonuses >₦25,000 require approval
        return $this->amount > 25000;
    }

    /**
     * Get required approval level
     */
    public function getRequiredApprovalLevel(): string
    {
        if ($this->amount > 50000) {
            return 'CEO'; // Bonuses >₦50,000 require CEO
        } elseif ($this->amount > 15000) {
            return 'GM'; // Bonuses ₦15,001-₦50,000 require GM
        } else {
            return 'FC'; // Bonuses ≤₦15,000 require FC
        }
    }

    /**
     * Mark bonus as approved
     */
    public function approve(User $approver): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now()
        ]);

        return true;
    }

    /**
     * Mark bonus as paid
     */
    public function markAsPaid(string $paymentReference = null): bool
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'payment_reference' => $paymentReference
        ]);

        return true;
    }

    /**
     * Cancel bonus
     */
    public function cancel(): bool
    {
        if (in_array($this->status, [self::STATUS_PAID])) {
            return false; // Cannot cancel paid bonuses
        }

        $this->update(['status' => self::STATUS_CANCELLED]);
        return true;
    }

    /**
     * Get bonus calculation breakdown
     */
    public function getCalculationBreakdown(): array
    {
        return $this->calculation_data ?? [];
    }

    /**
     * Get bonus type description
     */
    public function getTypeDescriptionAttribute(): string
    {
        return match($this->bonus_type) {
            self::TYPE_DELIVERY => 'Delivery Efficiency Bonus',
            self::TYPE_SALES => 'Sales Performance Bonus',
            self::TYPE_PERFORMANCE => 'Performance Excellence Bonus',
            self::TYPE_REFERRAL => 'Referral Bonus',
            self::TYPE_SPECIAL => 'Special Achievement Bonus',
            default => 'Unknown Bonus Type'
        };
    }
} 