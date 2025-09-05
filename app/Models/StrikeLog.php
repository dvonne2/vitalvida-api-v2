<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StrikeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_agent_id',
        'reason',
        'notes',
        'source',
        'severity',
        'issued_by',
        'payout_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the delivery agent that received the strike
     */
    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    /**
     * Get the user who issued the strike
     */
    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Get the related payout
     */
    public function payout()
    {
        return $this->belongsTo(Payout::class);
    }

    /**
     * Scope to get strikes for a specific DA
     */
    public function scopeForAgent($query, $deliveryAgentId)
    {
        return $query->where('delivery_agent_id', $deliveryAgentId);
    }

    /**
     * Scope to get recent strikes
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to get strikes by severity
     */
    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Get strike count for a DA in the last X days
     */
    public static function getStrikeCount($deliveryAgentId, $days = 30)
    {
        return self::forAgent($deliveryAgentId)
            ->recent($days)
            ->count();
    }
}
