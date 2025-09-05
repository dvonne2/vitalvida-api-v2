<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_ASSIGNED = 'assigned';
    const STATUS_PICKED_UP = 'picked_up';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';
    const STATUS_RETURNED = 'returned';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'delivery_code', 'order_id', 'delivery_agent_id', 'assigned_by',
        'status', 'pickup_location', 'delivery_location', 'pickup_coordinates',
        'delivery_coordinates', 'recipient_name', 'recipient_phone',
        'delivery_notes', 'assigned_at', 'picked_up_at', 'delivered_at',
        'expected_delivery_at', 'delivery_otp', 'otp_verified', 'otp_verified_at',
        'delivery_attempts', 'failure_reason', 'distance_km', 'delivery_time_minutes',
        'route_data', 'pickup_photos', 'delivery_photos', 'signature_data',
        'customer_rating', 'customer_feedback', 'agent_notes'
    ];

    protected $casts = [
        'pickup_coordinates' => 'array',
        'delivery_coordinates' => 'array',
        'route_data' => 'array',
        'pickup_photos' => 'array',
        'delivery_photos' => 'array',
        'assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'expected_delivery_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'otp_verified' => 'boolean',
        'distance_km' => 'decimal:2',
    ];

    protected $dates = ['deleted_at'];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // Helper methods
    public function isCompleted()
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isFailed()
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_RETURNED]);
    }

    public function canAttemptDelivery()
    {
        return $this->delivery_attempts < 3 && !$this->isCompleted();
    }

    public function generateOTP()
    {
        $this->delivery_otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->save();
        return $this->delivery_otp;
    }

    public function verifyOTP($otp)
    {
        if ($this->delivery_otp === $otp) {
            $this->update([
                'otp_verified' => true,
                'otp_verified_at' => now()
            ]);
            return true;
        }
        return false;
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByAgent($query, $agentId)
    {
        return $query->where('delivery_agent_id', $agentId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('assigned_at', today());
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_ASSIGNED, self::STATUS_PICKED_UP, self::STATUS_IN_TRANSIT]);
    }
}
