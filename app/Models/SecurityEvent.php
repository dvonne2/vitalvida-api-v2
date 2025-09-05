<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'user_id',
        'ip_address',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByEventType($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByIp($query, $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeSuspicious($query)
    {
        return $query->whereIn('event_type', [
            'login_failed',
            'suspicious_activity',
            'unauthorized_access',
            'multiple_failed_attempts',
            'unusual_activity',
        ]);
    }

    // Static methods for logging
    public static function logEvent($eventType, $userId = null, $details = [])
    {
        return self::create([
            'event_type' => $eventType,
            'user_id' => $userId,
            'ip_address' => request()->ip(),
            'details' => $details,
        ]);
    }

    public static function logLoginFailed($email, $reason = 'invalid_credentials')
    {
        return self::logEvent('login_failed', null, [
            'email' => $email,
            'reason' => $reason,
            'attempt_time' => now()->toISOString(),
        ]);
    }

    public static function logSuspiciousActivity($userId, $activity, $details = [])
    {
        return self::logEvent('suspicious_activity', $userId, [
            'activity' => $activity,
            'details' => $details,
            'detected_at' => now()->toISOString(),
        ]);
    }

    public static function logUnauthorizedAccess($userId, $resource, $details = [])
    {
        return self::logEvent('unauthorized_access', $userId, [
            'resource' => $resource,
            'details' => $details,
            'attempt_time' => now()->toISOString(),
        ]);
    }

    public static function logMultipleFailedAttempts($ipAddress, $attempts, $details = [])
    {
        return self::logEvent('multiple_failed_attempts', null, [
            'ip_address' => $ipAddress,
            'attempts' => $attempts,
            'details' => $details,
            'detected_at' => now()->toISOString(),
        ]);
    }

    public static function logUnusualActivity($userId, $activity, $details = [])
    {
        return self::logEvent('unusual_activity', $userId, [
            'activity' => $activity,
            'details' => $details,
            'detected_at' => now()->toISOString(),
        ]);
    }

    public static function logPasswordChange($userId, $success = true)
    {
        return self::logEvent('password_change', $userId, [
            'success' => $success,
            'change_time' => now()->toISOString(),
        ]);
    }

    public static function logAccountLocked($userId, $reason = 'multiple_failed_attempts')
    {
        return self::logEvent('account_locked', $userId, [
            'reason' => $reason,
            'locked_at' => now()->toISOString(),
        ]);
    }

    public static function logAccountUnlocked($userId, $unlockedBy = null)
    {
        return self::logEvent('account_unlocked', $userId, [
            'unlocked_by' => $unlockedBy,
            'unlocked_at' => now()->toISOString(),
        ]);
    }

    // Helper methods
    public function getEventTypeDisplayAttribute(): string
    {
        return str_replace('_', ' ', ucfirst($this->event_type));
    }

    public function getEventTypeColorAttribute(): string
    {
        return match($this->event_type) {
            'login_failed' => 'text-red-600',
            'suspicious_activity' => 'text-orange-600',
            'unauthorized_access' => 'text-red-800',
            'multiple_failed_attempts' => 'text-red-700',
            'unusual_activity' => 'text-yellow-600',
            'password_change' => 'text-blue-600',
            'account_locked' => 'text-red-900',
            'account_unlocked' => 'text-green-600',
            default => 'text-gray-600',
        };
    }

    public function getEventTypeIconAttribute(): string
    {
        return match($this->event_type) {
            'login_failed' => '🔑',
            'suspicious_activity' => '⚠️',
            'unauthorized_access' => '🚫',
            'multiple_failed_attempts' => '🔒',
            'unusual_activity' => '👁️',
            'password_change' => '🔐',
            'account_locked' => '🔒',
            'account_unlocked' => '🔓',
            default => '📄',
        };
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    public function getFormattedTimestampAttribute(): string
    {
        return $this->created_at->format('Y-m-d H:i:s');
    }

    public function isSuspicious(): bool
    {
        return in_array($this->event_type, [
            'login_failed',
            'suspicious_activity',
            'unauthorized_access',
            'multiple_failed_attempts',
            'unusual_activity',
        ]);
    }
} 