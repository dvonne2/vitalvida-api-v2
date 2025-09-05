<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskAssessment extends Model
{
    protected $fillable = [
        'delivery_agent_id', 'assessment_date', 'stockout_probability',
        'overstock_probability', 'days_until_stockout', 'potential_lost_sales',
        'carrying_cost_risk', 'risk_level', 'risk_factors',
        'mitigation_suggestions', 'overall_risk_score'
    ];

    protected $casts = [
        'assessment_date' => 'date',
        'stockout_probability' => 'decimal:2',
        'overstock_probability' => 'decimal:2',
        'potential_lost_sales' => 'decimal:2',
        'carrying_cost_risk' => 'decimal:2',
        'overall_risk_score' => 'decimal:2',
        'risk_factors' => 'array',
        'mitigation_suggestions' => 'array'
    ];

    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function scopeHighRisk($query)
    {
        return $query->whereIn('risk_level', ['high', 'critical']);
    }

    public function getUrgencyLevelAttribute()
    {
        if ($this->days_until_stockout <= 3) return 'Critical';
        if ($this->days_until_stockout <= 7) return 'High';
        if ($this->days_until_stockout <= 14) return 'Medium';
        return 'Low';
    }
}
