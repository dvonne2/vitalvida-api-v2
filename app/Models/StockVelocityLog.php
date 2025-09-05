<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockVelocityLog extends Model
{
    protected $table = 'stock_velocity_logs';
    
    protected $fillable = [
        'delivery_agent_id', 'tracking_date', 'opening_stock',
        'closing_stock', 'units_sold', 'units_received',
        'daily_velocity', 'stockout_days', 'opportunity_cost'
    ];

    protected $casts = [
        'tracking_date' => 'date',
        'daily_velocity' => 'decimal:2',
        'opportunity_cost' => 'decimal:2'
    ];

    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function scopeByDA($query, $daId)
    {
        return $query->where('delivery_agent_id', $daId);
    }

    public function scopeHighVelocity($query)
    {
        return $query->where('daily_velocity', '>=', 5);
    }

    public function scopeLowVelocity($query)
    {
        return $query->where('daily_velocity', '<=', 1);
    }

    public function getVelocityGradeAttribute()
    {
        if ($this->daily_velocity >= 8) return 'Excellent';
        if ($this->daily_velocity >= 5) return 'Good';
        if ($this->daily_velocity >= 3) return 'Average';
        if ($this->daily_velocity >= 1) return 'Poor';
        return 'Critical';
    }
} 