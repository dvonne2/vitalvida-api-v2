<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_agent_id',
        'date',
        'delivery_rate',
        'otp_success_rate',
        'stock_accuracy',
        'sales_amount',
        'orders_completed',
        'orders_total',
        'delivery_time_avg',
        'customer_satisfaction',
        'returns_count',
        'complaints_count',
        'bonus_earned',
        'penalties_incurred'
    ];

    protected $casts = [
        'date' => 'date',
        'delivery_rate' => 'decimal:2',
        'otp_success_rate' => 'decimal:2',
        'stock_accuracy' => 'decimal:2',
        'sales_amount' => 'decimal:2',
        'orders_completed' => 'integer',
        'orders_total' => 'integer',
        'delivery_time_avg' => 'integer', // in minutes
        'customer_satisfaction' => 'decimal:2',
        'returns_count' => 'integer',
        'complaints_count' => 'integer',
        'bonus_earned' => 'decimal:2',
        'penalties_incurred' => 'decimal:2'
    ];

    // Relationships
    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    // Scopes
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByAgent($query, $agentId)
    {
        return $query->where('delivery_agent_id', $agentId);
    }

    public function scopeHighPerformers($query)
    {
        return $query->where('delivery_rate', '>=', 90)
                    ->where('otp_success_rate', '>=', 95);
    }

    public function scopeLowPerformers($query)
    {
        return $query->where('delivery_rate', '<', 70)
                    ->orWhere('otp_success_rate', '<', 80);
    }

    // Business Logic Methods
    public function calculateDeliveryRate(): float
    {
        if ($this->orders_total == 0) return 0;
        return round(($this->orders_completed / $this->orders_total) * 100, 2);
    }

    public function calculateOtpSuccessRate(): float
    {
        // This would be calculated from OTP verification logs
        return $this->otp_success_rate ?? 0;
    }

    public function calculateStockAccuracy(): float
    {
        // This would be calculated from inventory count discrepancies
        return $this->stock_accuracy ?? 0;
    }

    public function getPerformanceScoreAttribute(): float
    {
        $deliveryWeight = 0.4;
        $otpWeight = 0.3;
        $stockWeight = 0.2;
        $satisfactionWeight = 0.1;

        $score = ($this->delivery_rate * $deliveryWeight) +
                ($this->otp_success_rate * $otpWeight) +
                ($this->stock_accuracy * $stockWeight) +
                ($this->customer_satisfaction * $satisfactionWeight);

        return round($score, 2);
    }

    public function getPerformanceLevelAttribute(): string
    {
        $score = $this->performance_score;
        
        if ($score >= 90) return 'excellent';
        if ($score >= 80) return 'good';
        if ($score >= 70) return 'average';
        if ($score >= 60) return 'below_average';
        return 'poor';
    }

    public function getPerformanceColorAttribute(): string
    {
        return match($this->performance_level) {
            'excellent' => 'success',
            'good' => 'info',
            'average' => 'warning',
            'below_average' => 'orange',
            'poor' => 'danger',
            default => 'secondary'
        };
    }

    public function getNetEarningsAttribute(): float
    {
        return $this->bonus_earned - $this->penalties_incurred;
    }

    public function getEfficiencyScoreAttribute(): float
    {
        if ($this->orders_completed == 0) return 0;
        
        $timeEfficiency = max(0, 100 - ($this->delivery_time_avg - 30)); // 30 min baseline
        $qualityEfficiency = max(0, 100 - ($this->returns_count + $this->complaints_count) * 10);
        
        return round(($timeEfficiency + $qualityEfficiency) / 2, 2);
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($metric) {
            if (empty($metric->delivery_rate)) {
                $metric->delivery_rate = $metric->calculateDeliveryRate();
            }
            if (empty($metric->otp_success_rate)) {
                $metric->otp_success_rate = $metric->calculateOtpSuccessRate();
            }
            if (empty($metric->stock_accuracy)) {
                $metric->stock_accuracy = $metric->calculateStockAccuracy();
            }
        });
    }
} 