<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegionalPerformance extends Model
{
    protected $table = 'regional_performance';
    
    protected $fillable = [
        'region_code', 'state', 'city', 'performance_date',
        'total_stock', 'units_sold', 'sell_through_rate',
        'days_of_inventory', 'velocity_score', 'seasonal_factors'
    ];

    protected $casts = [
        'performance_date' => 'date',
        'sell_through_rate' => 'decimal:2',
        'velocity_score' => 'decimal:2',
        'seasonal_factors' => 'array'
    ];

    public function scopeByRegion($query, $regionCode)
    {
        return $query->where('region_code', $regionCode);
    }

    public function scopeHighPerformance($query)
    {
        return $query->where('sell_through_rate', '>=', 80);
    }

    public function scopeLowPerformance($query)
    {
        return $query->where('sell_through_rate', '<=', 30);
    }

    public function getPerformanceGradeAttribute()
    {
        if ($this->sell_through_rate >= 80) return 'A';
        if ($this->sell_through_rate >= 60) return 'B';
        if ($this->sell_through_rate >= 40) return 'C';
        if ($this->sell_through_rate >= 20) return 'D';
        return 'F';
    }
} 