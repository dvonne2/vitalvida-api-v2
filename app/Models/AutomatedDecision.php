<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomatedDecision extends Model
{
    protected $fillable = [
        'decision_type', 'delivery_agent_id', 'trigger_reason',
        'decision_data', 'confidence_score', 'status',
        'triggered_at', 'executed_at', 'execution_result',
        'human_override', 'notes'
    ];

    protected $casts = [
        'decision_data' => 'array',
        'execution_result' => 'array',
        'confidence_score' => 'decimal:2',
        'triggered_at' => 'datetime',
        'executed_at' => 'datetime'
    ];

    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExecuted($query)
    {
        return $query->where('status', 'executed');
    }

    public function getExecutionTimeAttribute()
    {
        if (!$this->executed_at || !$this->triggered_at) return null;
        return $this->triggered_at->diffInMinutes($this->executed_at);
    }
}
