<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EventImpact extends Model
{
    protected $fillable = [
        'event_type', 'event_name', 'event_date', 'impact_duration_days',
        'demand_impact', 'affected_locations', 'severity', 'external_data',
        'impact_description', 'confidence_level', 'is_active'
    ];

    protected $casts = [
        'event_date' => 'date',
        'affected_locations' => 'array',
        'external_data' => 'array',
        'demand_impact' => 'decimal:2',
        'confidence_level' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeUpcoming($query, $days = 30)
    {
        return $query->where('event_date', '>=', Carbon::today())
                    ->where('event_date', '<=', Carbon::today()->addDays($days));
    }

    public function scopeHighImpact($query)
    {
        return $query->where('demand_impact', '>', 30);
    }

    public function getImpactLevelAttribute()
    {
        $impact = abs($this->demand_impact);
        
        if ($impact >= 50) return 'Critical';
        if ($impact >= 30) return 'High';
        if ($impact >= 15) return 'Medium';
        return 'Low';
    }

    public function isCurrentlyActive()
    {
        $today = Carbon::today();
        $endDate = $this->event_date->addDays($this->impact_duration_days);
        
        return $today->between($this->event_date, $endDate);
    }
} 