<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudPattern extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'staff_id',
        'order_id',
        'confidence_score',
        'risk_amount',
        'detected_at',
        'status',
        'evidence',
        'auto_action_taken',
        'gm_notified',
    ];

    protected $casts = [
        'evidence' => 'array',
        'detected_at' => 'datetime',
        'gm_notified' => 'boolean',
        'risk_amount' => 'decimal:2',
    ];

    /**
     * Get the staff member associated with this fraud pattern
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Get the order associated with this fraud pattern
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Scope to get high confidence patterns
     */
    public function scopeHighConfidence($query)
    {
        return $query->where('confidence_score', '>=', 90);
    }

    /**
     * Scope to get active patterns
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['FALSE_ALARM', 'RESOLVED']);
    }

    /**
     * Scope to get patterns that need GM notification
     */
    public function scopeNeedsGMNotification($query)
    {
        return $query->where('gm_notified', false)
                    ->where('confidence_score', '>=', 85);
    }

    /**
     * Get the fraud description
     */
    public function getDescriptionAttribute(): string
    {
        switch ($this->type) {
            case 'PAYMENT_FRAUD':
                $staffName = $this->staff?->name ?? 'Unknown';
                $orderCount = $this->evidence['payment_claims'] ?? 0;
                return "{$staffName}: {$orderCount} orders marked paid, ₦0 Moniepoint match";
            
            case 'DELIVERY_FRAUD':
                $staffName = $this->staff?->name ?? 'Unknown';
                return "{$staffName}: Stock decreasing without OTP confirmations";
            
            case 'GHOST_ORDER_PATTERN':
                $phone = $this->evidence['phone'] ?? 'unknown';
                return "Phone number {$phone} placed multiple orders, different names";
            
            default:
                return 'Suspicious pattern detected';
        }
    }

    /**
     * Get the risk level
     */
    public function getRiskLevelAttribute(): string
    {
        if ($this->confidence_score >= 90) return 'CRITICAL';
        if ($this->confidence_score >= 75) return 'HIGH';
        if ($this->confidence_score >= 60) return 'MEDIUM';
        return 'LOW';
    }

    /**
     * Get the formatted risk amount
     */
    public function getFormattedRiskAmountAttribute(): string
    {
        return '₦' . number_format($this->risk_amount, 2);
    }

    /**
     * Check if pattern requires immediate action
     */
    public function requiresImmediateAction(): bool
    {
        return $this->confidence_score >= 90 && $this->status === 'INVESTIGATING';
    }

    /**
     * Mark as GM notified
     */
    public function markAsGMNotified(): void
    {
        $this->update(['gm_notified' => true]);
    }

    /**
     * Get the auto action description
     */
    public function getAutoActionDescriptionAttribute(): string
    {
        if ($this->confidence_score >= 90) {
            return 'Payouts frozen automatically';
        }
        return 'Enhanced monitoring activated';
    }
}
