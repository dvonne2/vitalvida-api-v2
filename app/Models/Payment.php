<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'customer_id',
        'order_id',
        'amount',
        'payment_method',
        'transaction_reference',
        'moniepoint_reference',
        'status',
        'paid_at',
        'verified_at',
        'verified_by',
        'moniepoint_response'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
        'moniepoint_response' => 'array'
    ];

    protected $appends = [
        'is_verified',
        'processing_time_seconds'
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function mismatches(): HasMany
    {
        return $this->hasMany(PaymentMismatch::class);
    }

    // Accessors
    public function getIsVerifiedAttribute(): bool
    {
        return $this->status === 'confirmed';
    }

    public function getProcessingTimeSecondsAttribute(): ?int
    {
        if ($this->verified_at && $this->created_at) {
            return $this->created_at->diffInSeconds($this->verified_at);
        }
        return null;
    }

    // Amount conversion accessors
    public function getAmountNairaAttribute()
    {
        return $this->amount / 100;
    }

    public function getFormattedAmountAttribute()
    {
        return '₦' . number_format($this->amount_naira, 2);
    }

    // Scopes
    public function scopeVerified($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }

    // Business Methods
    public function markAsVerified(User $user): void
    {
        $this->update([
            'status' => 'confirmed',
            'verified_at' => now(),
            'verified_by' => $user->id
        ]);
    }

    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason
        ]);
    }

    public function hasMismatch(): bool
    {
        return $this->mismatches()->exists();
    }

    public function getLatestMismatch(): ?PaymentMismatch
    {
        return $this->mismatches()->latest()->first();
    }

    public function isProcessingTimeExcessive(): bool
    {
        return $this->processing_time_seconds > 300; // 5 minutes
    }

    public function getPaymentSummary(): array
    {
        return [
            'payment_id' => $this->payment_id,
            'order_number' => $this->order?->order_number,
            'customer_name' => $this->customer?->name,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'transaction_reference' => $this->transaction_reference,
            'paid_at' => $this->paid_at,
            'verified_at' => $this->verified_at,
            'verified_by' => $this->verifiedBy?->name,
            'processing_time_seconds' => $this->processing_time_seconds,
            'has_mismatch' => $this->hasMismatch(),
            'is_verified' => $this->is_verified
        ];
    }
}
