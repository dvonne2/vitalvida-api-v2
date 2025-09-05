<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemRecommendation extends Model
{
    protected $fillable = [
        'delivery_agent_id', 'type', 'priority', 'message',
        'action_data', 'status', 'assigned_to', 'executed_at'
    ];

    protected $casts = [
        'action_data' => 'array',
        'executed_at' => 'datetime'
    ];

    public function deliveryAgent() { return $this->belongsTo(DeliveryAgent::class); }
    public function assignedTo() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function scopePending($query) { return $query->where('status', 'pending'); }
    public function markExecuted() { $this->update(['status' => 'executed', 'executed_at' => now()]); }
}
