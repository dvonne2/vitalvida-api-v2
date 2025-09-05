<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityFeed extends Model
{
    use HasFactory;

    protected $table = 'activity_feed';

    protected $fillable = [
        'type',
        'message',
        'da_id',
        'order_id',
        'consignment_id',
        'location',
        'status',
        'activity_data'
    ];

    protected $casts = [
        'activity_data' => 'array'
    ];

    // Relationships
    public function deliveryAgent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class, 'da_id', 'agent_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class, 'consignment_id', 'consignment_id');
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByDA($query, $daId)
    {
        return $query->where('da_id', $daId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeLive($query)
    {
        return $query->where('created_at', '>=', now()->subMinutes(30));
    }

    // Helper methods
    public function getTypeDisplayAttribute(): string
    {
        return match($this->type) {
            'pickup' => 'Pickup',
            'delivery' => 'Delivery',
            'mismatch' => 'Mismatch',
            'call' => 'Call',
            'login' => 'Login',
            'logout' => 'Logout',
            default => ucfirst($this->type)
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'info' => 'blue',
            'delivered' => 'green',
            'flagged' => 'red',
            'warning' => 'yellow',
            default => 'gray'
        };
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    public function getFormattedTimeAttribute(): string
    {
        return $this->created_at->format('H:i:s');
    }

    // Static methods for creating activities
    public static function logPickup(string $daId, string $consignmentId, string $location): self
    {
        return self::create([
            'type' => 'pickup',
            'message' => "DA_{$daId} picked up #{$consignmentId} from {$location}",
            'da_id' => $daId,
            'consignment_id' => $consignmentId,
            'location' => $location,
            'status' => 'info'
        ]);
    }

    public static function logDelivery(string $daId, string $orderId, string $status = 'delivered'): self
    {
        return self::create([
            'type' => 'delivery',
            'message' => "OTP submitted for Order #{$orderId} by DA_{$daId}",
            'da_id' => $daId,
            'order_id' => $orderId,
            'status' => $status
        ]);
    }

    public static function logMismatch(string $consignmentId, string $description): self
    {
        return self::create([
            'type' => 'mismatch',
            'message' => "Mismatch flagged on #{$consignmentId}: {$description}",
            'consignment_id' => $consignmentId,
            'status' => 'flagged'
        ]);
    }

    public static function logCall(string $daId, string $orderId): self
    {
        return self::create([
            'type' => 'call',
            'message' => "DA_{$daId} called customer for Order #{$orderId}",
            'da_id' => $daId,
            'order_id' => $orderId,
            'status' => 'info'
        ]);
    }
}
