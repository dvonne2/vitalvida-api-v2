<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketIntelligence extends Model
{
    protected $table = 'market_intelligence';
    
    protected $fillable = [
        'region_code', 'intelligence_date', 'market_temperature',
        'demand_drivers', 'supply_constraints', 'price_sensitivity',
        'competitor_activity', 'external_indicators', 'market_summary',
        'reliability_score'
    ];

    protected $casts = [
        'intelligence_date' => 'date',
        'market_temperature' => 'decimal:2',
        'demand_drivers' => 'array',
        'supply_constraints' => 'array',
        'price_sensitivity' => 'decimal:2',
        'competitor_activity' => 'array',
        'external_indicators' => 'array',
        'reliability_score' => 'decimal:2'
    ];

    public function getMarketConditionAttribute()
    {
        if ($this->market_temperature >= 80) return 'Very Hot';
        if ($this->market_temperature >= 60) return 'Hot';
        if ($this->market_temperature >= 40) return 'Warm';
        if ($this->market_temperature >= 20) return 'Cool';
        return 'Cold';
    }
}
