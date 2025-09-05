<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_type',
        'ip_address',
        'user_agent',
        'request_method',
        'request_path',
        'status_code',
        'request_data',
        'response_data',
        'session_id',
        'request_id',
        'duration_ms',
        'error_message',
        'risk_level',
        'is_suspicious',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'is_suspicious' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the security log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for suspicious activities
     */
    public function scopeSuspicious($query)
    {
        return $query->where('is_suspicious', true);
    }

    /**
     * Scope for high risk activities
     */
    public function scopeHighRisk($query)
    {
        return $query->whereIn('risk_level', ['high', 'critical']);
    }

    /**
     * Scope for failed requests
     */
    public function scopeFailed($query)
    {
        return $query->where('status_code', '>=', 400);
    }

    /**
     * Scope for authentication events
     */
    public function scopeAuthEvents($query)
    {
        return $query->whereIn('event_type', ['login', 'logout', 'failed_login', 'password_change']);
    }

    /**
     * Get recent security events for a user
     */
    public static function getRecentUserEvents($userId, $hours = 24)
    {
        return static::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get suspicious activities from an IP address
     */
    public static function getSuspiciousFromIP($ipAddress, $hours = 24)
    {
        return static::where('ip_address', $ipAddress)
            ->where('created_at', '>=', now()->subHours($hours))
            ->where(function ($query) {
                $query->where('is_suspicious', true)
                    ->orWhereIn('risk_level', ['high', 'critical'])
                    ->orWhere('status_code', '>=', 400);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get security statistics
     */
    public static function getSecurityStats($days = 7)
    {
        $startDate = now()->subDays($days);

        return [
            'total_events' => static::where('created_at', '>=', $startDate)->count(),
            'suspicious_events' => static::where('created_at', '>=', $startDate)->suspicious()->count(),
            'failed_requests' => static::where('created_at', '>=', $startDate)->failed()->count(),
            'auth_events' => static::where('created_at', '>=', $startDate)->authEvents()->count(),
            'high_risk_events' => static::where('created_at', '>=', $startDate)->highRisk()->count(),
            'unique_ips' => static::where('created_at', '>=', $startDate)->distinct('ip_address')->count(),
            'unique_users' => static::where('created_at', '>=', $startDate)->distinct('user_id')->count(),
        ];
    }

    /**
     * Clean old security logs
     */
    public static function cleanOldLogs($days = 90)
    {
        return static::where('created_at', '<', now()->subDays($days))->delete();
    }
} 