<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KanbanMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'from_status', 'to_status', 'movement_type',
        'movement_reason', 'conditions_met', 'requires_approval',
        'approved_by', 'moved_at'
    ];

    protected $casts = [
        'conditions_met' => 'json',
        'moved_at' => 'datetime',
        'requires_approval' => 'boolean'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('movement_type', $type);
    }

    public function scopeRequiresApproval($query)
    {
        return $query->where('requires_approval', true);
    }

    public function getMovementTypeLabelAttribute(): string
    {
        return match($this->movement_type) {
            'ai_auto' => 'AI Auto',
            'ai_conditional' => 'AI Conditional',
            'manual_override' => 'Manual Override',
            'ai_blocked' => 'AI Blocked',
            default => 'Unknown'
        };
    }

    public function getMovementTypeColorAttribute(): string
    {
        return match($this->movement_type) {
            'ai_auto' => 'text-green-600',
            'ai_conditional' => 'text-yellow-600',
            'manual_override' => 'text-blue-600',
            'ai_blocked' => 'text-red-600',
            default => 'text-gray-600'
        };
    }
}
