<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'device_info',
        'ip_address',
        'user_agent',
        'last_activity',
        'expires_at',
        'is_active'
    ];

    protected $casts = [
        'last_activity' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('expires_at', '>', now());
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByToken($query, $token)
    {
        return $query->where('token', $token);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    // Business Logic Methods
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function updateActivity(): void
    {
        $this->update([
            'last_activity' => now()
        ]);
    }

    public function deactivate(): void
    {
        $this->update([
            'is_active' => false
        ]);
    }

    public function getDeviceTypeAttribute(): string
    {
        $userAgent = $this->user_agent ?? '';
        
        if (str_contains($userAgent, 'Mobile')) {
            return 'mobile';
        } elseif (str_contains($userAgent, 'Tablet')) {
            return 'tablet';
        } else {
            return 'desktop';
        }
    }

    public function getBrowserAttribute(): string
    {
        $userAgent = $this->user_agent ?? '';
        
        if (str_contains($userAgent, 'Chrome')) {
            return 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox')) {
            return 'Firefox';
        } elseif (str_contains($userAgent, 'Safari')) {
            return 'Safari';
        } elseif (str_contains($userAgent, 'Edge')) {
            return 'Edge';
        } else {
            return 'Unknown';
        }
    }

    public function getLocationAttribute(): string
    {
        // This would integrate with a geolocation service
        return $this->ip_address ?? 'Unknown';
    }

    public function getDurationAttribute(): string
    {
        if (!$this->last_activity) {
            return '0 minutes';
        }

        $duration = now()->diffInMinutes($this->last_activity);
        
        if ($duration < 60) {
            return $duration . ' minutes';
        } elseif ($duration < 1440) {
            return round($duration / 60, 1) . ' hours';
        } else {
            return round($duration / 1440, 1) . ' days';
        }
    }

    public function getStatusColorAttribute(): string
    {
        if (!$this->is_active) {
            return 'danger';
        }
        
        if ($this->isExpired()) {
            return 'warning';
        }
        
        return 'success';
    }

    public function getStatusLabelAttribute(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }
        
        if ($this->isExpired()) {
            return 'Expired';
        }
        
        return 'Active';
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (empty($session->last_activity)) {
                $session->last_activity = now();
            }
            if (empty($session->expires_at)) {
                $session->expires_at = now()->addDays(30);
            }
            if (empty($session->is_active)) {
                $session->is_active = true;
            }
        });
    }
} 