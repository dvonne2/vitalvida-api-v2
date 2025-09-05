<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KPIMetric extends Model
{
    use HasFactory;

    protected $table = 'kpi_metrics';

    protected $fillable = [
        'name',
        'current_value',
        'target_value',
        'unit',
        'status',
        'period',
        'recorded_date'
    ];

    protected $casts = [
        'current_value' => 'decimal:2',
        'target_value' => 'decimal:2',
        'recorded_date' => 'date'
    ];

    // Scopes
    public function scopeByName($query, $name)
    {
        return $query->where('name', $name);
    }

    public function scopeByPeriod($query, $period)
    {
        return $query->where('period', $period);
    }

    public function scopeToday($query)
    {
        return $query->where('recorded_date', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('recorded_date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('recorded_date', [now()->startOfMonth(), now()->endOfMonth()]);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Helper methods
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'poor' => 'red',
            'good' => 'yellow',
            'excellent' => 'green',
            default => 'gray'
        };
    }

    public function getFormattedValueAttribute(): string
    {
        if ($this->unit === 'mins') {
            return $this->current_value . 'mins';
        }
        
        if ($this->unit === '%') {
            return $this->current_value . '%';
        }
        
        return (string) $this->current_value;
    }

    public function getFormattedTargetAttribute(): string
    {
        if ($this->target_value === null) {
            return 'N/A';
        }
        
        if ($this->unit === 'mins') {
            return $this->target_value . 'mins';
        }
        
        if ($this->unit === '%') {
            return $this->target_value . '%';
        }
        
        return (string) $this->target_value;
    }

    public function isOnTarget(): bool
    {
        if ($this->target_value === null) {
            return true;
        }
        
        return $this->current_value >= $this->target_value;
    }

    public function getPerformancePercentageAttribute(): float
    {
        if ($this->target_value === null || $this->target_value == 0) {
            return 0;
        }
        
        return ($this->current_value / $this->target_value) * 100;
    }

    // Static methods for creating KPIs
    public static function updateDispatchAccuracyRate(float $value): self
    {
        return self::updateOrCreate(
            ['name' => 'Dispatch Accuracy Rate', 'period' => 'daily', 'recorded_date' => today()],
            [
                'current_value' => $value,
                'target_value' => 100,
                'unit' => '%',
                'status' => $value >= 95 ? 'excellent' : ($value >= 90 ? 'good' : 'poor')
            ]
        );
    }

    public static function updateDeliveryChainMatchRate(float $value): self
    {
        return self::updateOrCreate(
            ['name' => 'Delivery Chain Match Rate', 'period' => 'daily', 'recorded_date' => today()],
            [
                'current_value' => $value,
                'target_value' => 95,
                'unit' => '%',
                'status' => $value >= 95 ? 'excellent' : ($value >= 90 ? 'good' : 'poor')
            ]
        );
    }

    public static function updateProofComplianceScore(float $value): self
    {
        return self::updateOrCreate(
            ['name' => 'Proof Compliance Score', 'period' => 'daily', 'recorded_date' => today()],
            [
                'current_value' => $value,
                'target_value' => 100,
                'unit' => '%',
                'status' => $value >= 95 ? 'excellent' : ($value >= 90 ? 'good' : 'poor')
            ]
        );
    }

    public static function updateSLARelayCompletionRate(float $value): self
    {
        return self::updateOrCreate(
            ['name' => 'SLA Relay Completion Rate', 'period' => 'daily', 'recorded_date' => today()],
            [
                'current_value' => $value,
                'target_value' => 95,
                'unit' => '%',
                'status' => $value >= 95 ? 'excellent' : ($value >= 90 ? 'good' : 'poor')
            ]
        );
    }

    public static function updateFraudEscalationResponseTime(float $value): self
    {
        return self::updateOrCreate(
            ['name' => 'Fraud Escalation Response Time', 'period' => 'daily', 'recorded_date' => today()],
            [
                'current_value' => $value,
                'target_value' => 30,
                'unit' => 'mins',
                'status' => $value <= 30 ? 'excellent' : ($value <= 45 ? 'good' : 'poor')
            ]
        );
    }
}
