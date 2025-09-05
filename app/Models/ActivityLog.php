<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'timestamp',
        'details',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'details' => 'array',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
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

    // Static methods for logging
    public static function logActivity($action, $userId = null, $details = [])
    {
        return self::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
            'details' => $details,
        ]);
    }

    public static function logLogin($userId)
    {
        return self::logActivity('login', $userId, [
            'session_id' => session()->getId(),
            'login_time' => now()->toISOString(),
        ]);
    }

    public static function logLogout($userId)
    {
        return self::logActivity('logout', $userId, [
            'session_id' => session()->getId(),
            'logout_time' => now()->toISOString(),
        ]);
    }

    public static function logFailedLogin($email, $reason = 'invalid_credentials')
    {
        return self::create([
            'action' => 'login_failed',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
            'details' => [
                'email' => $email,
                'reason' => $reason,
                'attempt_time' => now()->toISOString(),
            ],
        ]);
    }

    public static function logDataAccess($userId, $model, $action, $recordId = null)
    {
        return self::logActivity($action, $userId, [
            'model' => $model,
            'record_id' => $recordId,
            'access_time' => now()->toISOString(),
        ]);
    }

    public static function logDataModification($userId, $model, $action, $recordId, $changes = [])
    {
        return self::logActivity($action, $userId, [
            'model' => $model,
            'record_id' => $recordId,
            'changes' => $changes,
            'modification_time' => now()->toISOString(),
        ]);
    }

    // Helper methods
    public function getActionDisplayAttribute(): string
    {
        return str_replace('_', ' ', ucfirst($this->action));
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->timestamp->diffForHumans();
    }

    public function getFormattedTimestampAttribute(): string
    {
        return $this->timestamp->format('Y-m-d H:i:s');
    }
} 