<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeographicZone extends Model
{
    protected $table = 'geographic_zones';
    
    protected $fillable = [
        'zone_code', 'zone_name', 'states_included',
        'hub_da_id', 'avg_transport_cost_per_km', 'seasonal_patterns'
    ];

    protected $casts = [
        'states_included' => 'array',
        'avg_transport_cost_per_km' => 'decimal:2',
        'seasonal_patterns' => 'array'
    ];

    public function hubDA()
    {
        return $this->belongsTo(DeliveryAgent::class, 'hub_da_id');
    }

    public function deliveryAgents()
    {
        return $this->hasMany(DeliveryAgent::class, 'zone_id');
    }

    public function scopeByZone($query, $zoneCode)
    {
        return $query->where('zone_code', $zoneCode);
    }

    public function getZonePerformanceAttribute()
    {
        return RegionalPerformance::whereIn('state', $this->states_included)
            ->avg('sell_through_rate');
    }
} 