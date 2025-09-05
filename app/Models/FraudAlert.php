<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_id',
        'type',
        'status',
        'description',
        'severity',
        'consignment_id',
        'da_id',
        'escalated_to',
        'auto_actions',
        'resolved_at',
        'resolved_by'
    ];

    protected $casts = [
        'escalated_to' => 'array',
        'auto_actions' => 'array',
        'resolved_at' => 'datetime'
    ];

    // Relationships
    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class, 'consignment_id', 'consignment_id');
    }

    public function deliveryAgent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class, 'da_id', 'agent_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeMonitoring($query)
    {
        return $query->where('status', 'monitoring');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    // Helper methods
    public function getTypeDisplayAttribute(): string
    {
        return str_replace('_', ' ', strtoupper($this->type));
    }

    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray'
        };
    }

    public function isEscalated(): bool
    {
        return !empty($this->escalated_to);
    }

    public function resolve(string $resolvedBy): bool
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy
        ]);
        
        return true;
    }

    public function escalate(array $roles): bool
    {
        $this->update([
            'escalated_to' => $roles
        ]);
        
        return true;
    }

    public function addAutoAction(string $action): bool
    {
        $actions = $this->auto_actions ?? [];
        $actions[] = $action;
        
        $this->update(['auto_actions' => $actions]);
        
        return true;
    }
}
