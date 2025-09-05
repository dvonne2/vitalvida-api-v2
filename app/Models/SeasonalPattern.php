<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeasonalPattern extends Model
{
    protected $fillable = [
        'pattern_type', 'pattern_name', 'start_date', 'end_date',
        'demand_multiplier', 'affected_regions', 'historical_data',
        'confidence_level', 'description', 'is_active'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'demand_multiplier' => 'decimal:2',
        'confidence_level' => 'decimal:2',
        'historical_data' => 'array',
        'is_active' => 'boolean'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('pattern_type', $type);
    }

    public function scopeByRegion($query, $region)
    {
        return $query->where('affected_regions', 'like', "%{$region}%");
    }

    public function scopeCurrentlyActive($query)
    {
        $today = now()->toDateString();
        return $query->where('is_active', true)
                    ->where('start_date', '<=', $today)
                    ->where('end_date', '>=', $today);
    }

    public function getImpactLevelAttribute()
    {
        $multiplier = $this->demand_multiplier;
        
        if ($multiplier >= 2.0) return 'Very High';
        if ($multiplier >= 1.5) return 'High';
        if ($multiplier >= 1.2) return 'Medium';
        if ($multiplier >= 1.0) return 'Low';
        return 'Negative';
    }

    public function getDurationInDaysAttribute()
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function isCurrentlyActive()
    {
        $today = now()->toDateString();
        return $this->is_active && 
               $this->start_date <= $today && 
               $this->end_date >= $today;
    }

    public function affectsRegion($region)
    {
        $regions = is_array($this->affected_regions) ? 
                  $this->affected_regions : 
                  explode(',', $this->affected_regions);
        
        return in_array($region, $regions);
    }
} 