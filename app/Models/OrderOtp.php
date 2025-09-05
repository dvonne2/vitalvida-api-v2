<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OrderOtp extends Model
{
    protected $fillable = [
        'order_number',
        'otp_code',
        'attempt_count',
        'resend_count',
        'expires_at',
        'is_verified',
        'is_locked',
        'locked_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'locked_at' => 'datetime',
        'is_verified' => 'boolean',
        'is_locked' => 'boolean'
    ];

    public function isExpired(): bool
    {
        return Carbon::now()->isAfter($this->expires_at);
    }

    public function isMaxAttemptsReached(): bool
    {
        return $this->attempt_count >= 3;
    }

    public function isMaxResendsReached(): bool
    {
        return $this->resend_count >= 2;
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempt_count');
        
        if ($this->isMaxAttemptsReached()) {
            $this->update([
                'is_locked' => true,
                'locked_at' => Carbon::now()
            ]);
        }
    }

    public function regenerateOtp(): void
    {
        $this->update([
            'otp_code' => str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => Carbon::now()->addMinutes(10),
            'attempt_count' => 0,
        ]);
        
        $this->increment('resend_count');
    }

    public function markAsVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'is_locked' => false
        ]);
    }
}
