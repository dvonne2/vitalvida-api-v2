<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemandForecast extends Model
{
    protected $fillable = [
        'delivery_agent_id', 'forecast_date', 'forecast_period',
        'predicted_demand', 'confidence_score', 'model_used',
        'input_factors', 'accuracy_score', 'actual_demand',
        'model_metadata'
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'predicted_demand' => 'integer',
        'confidence_score' => 'decimal:2',
        'accuracy_score' => 'decimal:2',
        'actual_demand' => 'integer',
        'input_factors' => 'array',
        'model_metadata' => 'array'
    ];

    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function scopeByAgent($query, $agentId)
    {
        return $query->where('delivery_agent_id', $agentId);
    }

    public function scopeByPeriod($query, $period)
    {
        return $query->where('forecast_period', $period);
    }

    public function scopeByModel($query, $model)
    {
        return $query->where('model_used', $model);
    }

    public function scopeHighConfidence($query)
    {
        return $query->where('confidence_score', '>=', 80);
    }

    public function getAccuracyLevelAttribute()
    {
        if (!$this->accuracy_score) return 'Unknown';
        
        if ($this->accuracy_score >= 90) return 'Excellent';
        if ($this->accuracy_score >= 80) return 'Good';
        if ($this->accuracy_score >= 70) return 'Fair';
        return 'Poor';
    }

    public function calculateAccuracy()
    {
        if (!$this->actual_demand || !$this->predicted_demand) return null;
        
        $error = abs($this->actual_demand - $this->predicted_demand);
        $accuracy = 100 - (($error / $this->actual_demand) * 100);
        
        return max(0, $accuracy);
    }
} 