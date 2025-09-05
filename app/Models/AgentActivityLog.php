<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentActivityLog extends Model
{
    use HasFactory;

    const ACTIVITY_LOGIN = 'login';
    const ACTIVITY_LOGOUT = 'logout';
    const ACTIVITY_PICKUP = 'pickup';
    const ACTIVITY_DELIVERY = 'delivery';
    const ACTIVITY_LOCATION_UPDATE = 'location_update';
    const ACTIVITY_STATUS_CHANGE = 'status_change';
    const ACTIVITY_ORDER_ACCEPTANCE = 'order_acceptance';
    const ACTIVITY_ORDER_REJECTION = 'order_rejection';
    const ACTIVITY_BREAK_START = 'break_start';
    const ACTIVITY_BREAK_END = 'break_end';
    const ACTIVITY_ISSUE_REPORT = 'issue_report';

    protected $fillable = [
        'delivery_agent_id', 'activity_type', 'description',
        'activity_data', 'location_data', 'ip_address',
        'user_agent', 'related_order_id'
    ];

    protected $casts = [
        'activity_data' => 'array',
        'location_data' => 'array',
    ];

    // Relationships
    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function relatedOrder()
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }

    // Helper methods
    public static function logActivity($agentId, $type, $description, $data = null, $orderId = null)
    {
        return self::create([
            'delivery_agent_id' => $agentId,
            'activity_type' => $type,
            'description' => $description,
            'activity_data' => $data,
            'location_data' => request()->header('Location-Data') ? json_decode(request()->header('Location-Data'), true) : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'related_order_id' => $orderId,
        ]);
    }

    // Scopes
    public function scopeForAgent($query, $agentId)
    {
        return $query->where('delivery_agent_id', $agentId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('activity_type', $type);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
