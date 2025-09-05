<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'consignment_id',
        'from_location',
        'to_location',
        'quantity',
        'port',
        'driver_name',
        'driver_phone',
        'status',
        'pickup_time',
        'delivery_time',
        'notes'
    ];

    protected $casts = [
        'pickup_time' => 'datetime',
        'delivery_time' => 'datetime'
    ];

    // Relationships
    public function fraudAlerts(): HasMany
    {
        return $this->hasMany(FraudAlert::class, 'consignment_id', 'consignment_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivityFeed::class, 'consignment_id', 'consignment_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(MovementTracking::class, 'consignment_id', 'consignment_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'in_transit');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    // Helper methods
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => 'Unknown'
        };
    }

    public function isDelayed(): bool
    {
        if ($this->status === 'pending' && $this->created_at->diffInHours(now()) > 4) {
            return true;
        }
        
        if ($this->status === 'in_transit' && $this->pickup_time && $this->pickup_time->diffInHours(now()) > 24) {
            return true;
        }
        
        return false;
    }

    public function getDurationAttribute(): string
    {
        if ($this->delivery_time && $this->pickup_time) {
            $hours = $this->pickup_time->diffInHours($this->delivery_time);
            return $hours . ' hours';
        }
        
        return 'N/A';
    }
}
