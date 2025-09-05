<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DADistanceMatrix extends Model
{
    protected $table = 'da_distance_matrix';
    
    protected $fillable = [
        'from_da_id', 'to_da_id', 'distance_km', 'travel_time_minutes',
        'transport_cost', 'route_quality', 'route_waypoints'
    ];

    protected $casts = [
        'route_waypoints' => 'array',
        'distance_km' => 'decimal:2',
        'transport_cost' => 'decimal:2'
    ];

    public function fromDA()
    {
        return $this->belongsTo(DeliveryAgent::class, 'from_da_id');
    }

    public function toDA()
    {
        return $this->belongsTo(DeliveryAgent::class, 'to_da_id');
    }

    public function getTransportCostPerKmAttribute()
    {
        return $this->distance_km > 0 ? $this->transport_cost / $this->distance_km : 0;
    }

    public function scopeByDistance($query, $maxDistance)
    {
        return $query->where('distance_km', '<=', $maxDistance);
    }

    public function scopeGoodRoutes($query)
    {
        return $query->where('route_quality', 'good');
    }
} 