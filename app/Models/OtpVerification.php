<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_agent_id',
        'otp_code',
        'action_type',
        'reference_id',
        'reference_type',
        'generated_at',
        'verified_at',
        'expires_at',
        'status',
        'attempts',
        'max_attempts'
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer'
    ];

    // Relationships
    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeByActionType($query, $type)
    {
        return $query->where('action_type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByAgent($query, $agentId)
    {
        return $query->where('delivery_agent_id', $agentId);
    }

    public function scopeValid($query)
    {
        return $query->where('status', 'pending')
                    ->where('expires_at', '>', now())
                    ->where('attempts', '<', 'max_attempts');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'verified');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Business Logic Methods
    public function generateOtp(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isMaxAttemptsReached(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    public function canVerify(): bool
    {
        return $this->status === 'pending' && 
               !$this->isExpired() && 
               !$this->isMaxAttemptsReached();
    }

    public function verify(string $otpCode): bool
    {
        if (!$this->canVerify()) {
            return false;
        }

        $this->increment('attempts');

        if ($this->otp_code === $otpCode) {
            $this->update([
                'status' => 'verified',
                'verified_at' => now()
            ]);
            return true;
        }

        if ($this->isMaxAttemptsReached()) {
            $this->update(['status' => 'failed']);
        }

        return false;
    }

    public function resend(): bool
    {
        if ($this->isMaxAttemptsReached()) {
            return false;
        }

        $this->update([
            'otp_code' => $this->generateOtp(),
            'generated_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0
        ]);

        return true;
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'verified' => 'success',
            'failed' => 'danger',
            'expired' => 'secondary',
            default => 'secondary'
        };
    }

    public function getActionTypeLabelAttribute(): string
    {
        return match($this->action_type) {
            'sale' => 'Sale Transaction',
            'stock_deduction' => 'Stock Deduction',
            'transfer' => 'Inventory Transfer',
            'adjustment' => 'Stock Adjustment',
            'count' => 'Inventory Count',
            default => ucfirst($this->action_type)
        };
    }

    public function getTimeRemainingAttribute(): int
    {
        if ($this->isExpired()) return 0;
        return now()->diffInSeconds($this->expires_at, false);
    }

    public function getAttemptsRemainingAttribute(): int
    {
        return max(0, $this->max_attempts - $this->attempts);
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($otp) {
            if (empty($otp->otp_code)) {
                $otp->otp_code = $otp->generateOtp();
            }
            if (empty($otp->generated_at)) {
                $otp->generated_at = now();
            }
            if (empty($otp->expires_at)) {
                $otp->expires_at = now()->addMinutes(10);
            }
            if (empty($otp->max_attempts)) {
                $otp->max_attempts = 3;
            }
            if (empty($otp->status)) {
                $otp->status = 'pending';
            }
        });
    }
}
