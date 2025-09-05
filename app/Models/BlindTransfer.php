<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlindTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transfer_code',
        'from_agent_id',
        'to_agent_id',
        'product_id',
        'quantity',
        'orchestrated_by',
        'pickup_location',
        'delivery_location',
        'transfer_status',
        'pickup_code',
        'delivery_code',
        'pickup_window_start',
        'pickup_window_end',
        'delivery_window_start',
        'delivery_window_end',
        'picked_up_at',
        'delivered_at',
        'transfer_metadata',
        'violation_flags'
    ];

    protected $casts = [
        'transfer_metadata' => 'array',
        'violation_flags' => 'array',
        'pickup_window_start' => 'datetime',
        'pickup_window_end' => 'datetime',
        'delivery_window_start' => 'datetime',
        'delivery_window_end' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime'
    ];

    // Relationships
    public function fromAgent()
    {
        return $this->belongsTo(DeliveryAgent::class, 'from_agent_id');
    }

    public function toAgent()
    {
        return $this->belongsTo(DeliveryAgent::class, 'to_agent_id');
    }

    public function product()
    {
        return $this->belongsTo(VitalVidaProduct::class, 'product_id');
    }

    public function orchestratedBy()
    {
        return $this->belongsTo(User::class, 'orchestrated_by');
    }

    // Status constants
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_PICKUP_READY = 'pickup_ready';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERY_READY = 'delivery_ready';
    const STATUS_COMPLETED = 'completed';
    const STATUS_VIOLATED = 'violated';
    const STATUS_CANCELLED = 'cancelled';

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNotIn('transfer_status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('transfer_status', '!=', self::STATUS_COMPLETED)
                    ->where('delivery_window_end', '<', now());
    }

    public function scopeViolated($query)
    {
        return $query->where('transfer_status', self::STATUS_VIOLATED);
    }

    // Accessors
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'scheduled' => ['class' => 'info', 'text' => 'Scheduled'],
            'pickup_ready' => ['class' => 'warning', 'text' => 'Pickup Ready'],
            'in_transit' => ['class' => 'primary', 'text' => 'In Transit'],
            'delivery_ready' => ['class' => 'warning', 'text' => 'Delivery Ready'],
            'completed' => ['class' => 'success', 'text' => 'Completed'],
            'violated' => ['class' => 'danger', 'text' => 'Violated'],
            'cancelled' => ['class' => 'secondary', 'text' => 'Cancelled']
        ];

        return $badges[$this->transfer_status] ?? ['class' => 'secondary', 'text' => 'Unknown'];
    }

    public function getIsOverdueAttribute()
    {
        return $this->transfer_status !== self::STATUS_COMPLETED && 
               $this->delivery_window_end && 
               $this->delivery_window_end->isPast();
    }

    public function getTransitDurationAttribute()
    {
        if ($this->picked_up_at && $this->delivered_at) {
            return $this->picked_up_at->diffInHours($this->delivered_at);
        }
        
        if ($this->picked_up_at && !$this->delivered_at) {
            return $this->picked_up_at->diffInHours(now());
        }

        return 0;
    }

    public function getHasViolationsAttribute()
    {
        return !empty($this->violation_flags) || $this->transfer_status === self::STATUS_VIOLATED;
    }
}
