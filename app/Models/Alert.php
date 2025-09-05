<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'type',
        'severity',
        'department_id',
        'alertable_type',
        'alertable_id',
        'status',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
        'priority',
        'source',
        'metadata',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'metadata' => 'array',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    // Relationships
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function alertable(): MorphTo
    {
        return $this->morphTo();
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_at');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('severity', ['critical', 'high']);
    }

    // Helper methods
    public function getSeverityColorAttribute()
    {
        return match($this->severity) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'blue',
            default => 'gray'
        };
    }

    public function getSeverityIconAttribute()
    {
        return match($this->severity) {
            'critical' => 'exclamation-triangle',
            'high' => 'exclamation-circle',
            'medium' => 'exclamation',
            'low' => 'info-circle',
            default => 'info'
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'active' => 'red',
            'acknowledged' => 'yellow',
            'resolved' => 'green',
            default => 'gray'
        };
    }

    public function isAcknowledged()
    {
        return !is_null($this->acknowledged_at);
    }

    public function isResolved()
    {
        return !is_null($this->resolved_at);
    }

    public function acknowledge($userId)
    {
        $this->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
            'status' => 'acknowledged'
        ]);
    }

    public function resolve($userId)
    {
        $this->update([
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'status' => 'resolved'
        ]);
    }

    public function getTimeSinceCreatedAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    public function getTimeSinceAcknowledgedAttribute()
    {
        return $this->acknowledged_at?->diffForHumans();
    }

    public function getTimeSinceResolvedAttribute()
    {
        return $this->resolved_at?->diffForHumans();
    }

    // Static methods
    public static function getActiveAlerts()
    {
        return static::with(['department', 'createdBy'])
            ->active()
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public static function getAlertsByDepartment($departmentId)
    {
        return static::with(['department', 'createdBy'])
            ->byDepartment($departmentId)
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public static function getCriticalAlerts()
    {
        return static::with(['department', 'createdBy'])
            ->bySeverity('critical')
            ->active()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public static function getAlertCounts()
    {
        return [
            'total' => static::count(),
            'active' => static::active()->count(),
            'critical' => static::bySeverity('critical')->active()->count(),
            'high' => static::bySeverity('high')->active()->count(),
            'medium' => static::bySeverity('medium')->active()->count(),
            'low' => static::bySeverity('low')->active()->count(),
            'unacknowledged' => static::unacknowledged()->count(),
            'unresolved' => static::unresolved()->count(),
        ];
    }

    public static function createAlert($data)
    {
        return static::create([
            'title' => $data['title'],
            'message' => $data['message'],
            'type' => $data['type'],
            'severity' => $data['severity'] ?? 'medium',
            'department_id' => $data['department_id'] ?? null,
            'alertable_type' => $data['alertable_type'] ?? null,
            'alertable_id' => $data['alertable_id'] ?? null,
            'status' => 'active',
            'priority' => $data['priority'] ?? 'normal',
            'source' => $data['source'] ?? 'system',
            'metadata' => $data['metadata'] ?? [],
            'created_by' => $data['created_by'] ?? auth()->id(),
        ]);
    }
} 