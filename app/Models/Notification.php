<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'priority', 'title', 'message', 'data', 'read'
    ];

    protected $casts = [
        'data' => 'json',
        'read' => 'boolean'
    ];

    public function scopeUnread($query)
    {
        return $query->where('read', false);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function markAsRead(): void
    {
        $this->update(['read' => true]);
    }

    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'high' => 'text-red-600',
            'medium' => 'text-yellow-600',
            'low' => 'text-blue-600',
            default => 'text-gray-600'
        };
    }

    public function getTypeIconAttribute(): string
    {
        return match($this->type) {
            'assignment_timeout' => '⏰',
            'payment_received' => '💰',
            'performance_alert' => '📊',
            'system_update' => '🔄',
            default => '📢'
        };
    }
}
