<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    // Scopes
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeInfo($query)
    {
        return $query->where('level', 'info');
    }

    public function scopeWarning($query)
    {
        return $query->where('level', 'warning');
    }

    public function scopeError($query)
    {
        return $query->where('level', 'error');
    }

    public function scopeCritical($query)
    {
        return $query->where('level', 'critical');
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // Static methods for logging
    public static function logInfo($message, $context = [])
    {
        return self::create([
            'level' => 'info',
            'message' => $message,
            'context' => $context,
        ]);
    }

    public static function logWarning($message, $context = [])
    {
        return self::create([
            'level' => 'warning',
            'message' => $message,
            'context' => $context,
        ]);
    }

    public static function logError($message, $context = [])
    {
        return self::create([
            'level' => 'error',
            'message' => $message,
            'context' => $context,
        ]);
    }

    public static function logCritical($message, $context = [])
    {
        return self::create([
            'level' => 'critical',
            'message' => $message,
            'context' => $context,
        ]);
    }

    public static function logSystemEvent($event, $details = [])
    {
        return self::logInfo("System event: {$event}", $details);
    }

    public static function logSecurityEvent($event, $details = [])
    {
        return self::logWarning("Security event: {$event}", $details);
    }

    public static function logDatabaseEvent($event, $details = [])
    {
        return self::logInfo("Database event: {$event}", $details);
    }

    public static function logApplicationError($error, $context = [])
    {
        return self::logError("Application error: {$error}", $context);
    }

    // Helper methods
    public function getLevelColorAttribute(): string
    {
        return match($this->level) {
            'info' => 'text-blue-600',
            'warning' => 'text-yellow-600',
            'error' => 'text-red-600',
            'critical' => 'text-red-800',
            default => 'text-gray-600',
        };
    }

    public function getLevelIconAttribute(): string
    {
        return match($this->level) {
            'info' => 'ℹ️',
            'warning' => '⚠️',
            'error' => '❌',
            'critical' => '🚨',
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
} 