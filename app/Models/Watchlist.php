<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Watchlist extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'watchlist';

    protected $fillable = [
        'delivery_agent_id',
        'reason',
        'created_by',
        'escalated_at',
        'is_active',
        'resolved_at',
        'resolved_by'
    ];

    protected $casts = [
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Get the delivery agent that is watchlisted
     */
    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    /**
     * Get the user who created the watchlist entry
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who resolved the watchlist entry
     */
    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope to get only active watchlist entries
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('resolved_at');
    }

    /**
     * Scope to get resolved watchlist entries
     */
    public function scopeResolved($query)
    {
        return $query->where('is_active', false)->whereNotNull('resolved_at');
    }

    /**
     * Scope to get recent watchlist entries
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('escalated_at', '>=', now()->subDays($days));
    }

    /**
     * Check if a delivery agent is currently watchlisted
     */
    public static function isWatchlisted($deliveryAgentId)
    {
        return self::where('delivery_agent_id', $deliveryAgentId)
            ->active()
            ->exists();
    }

    /**
     * Get active watchlist entry for a delivery agent
     */
    public static function getActiveWatchlist($deliveryAgentId)
    {
        return self::where('delivery_agent_id', $deliveryAgentId)
            ->active()
            ->first();
    }

    /**
     * Resolve the watchlist entry
     */
    public function resolve($resolvedBy = null, $note = null)
    {
        $this->update([
            'is_active' => false,
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy ?? auth()->id()
        ]);

        return $this;
    }

    /**
     * Get days since watchlisted
     */
    public function getDaysSinceWatchlistedAttribute()
    {
        return $this->escalated_at ? now()->diffInDays($this->escalated_at) : 0;
    }

    /**
     * Get formatted escalation date
     */
    public function getFormattedEscalationDateAttribute()
    {
        return $this->escalated_at?->format('M d, Y H:i:s');
    }
}
