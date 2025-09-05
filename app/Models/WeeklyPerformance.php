<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyPerformance extends Model
{
    use HasFactory;

    protected $fillable = [
        'telesales_agent_id',
        'week_start',
        'week_end',
        'orders_assigned',
        'orders_delivered',
        'delivery_rate',
        'qualified',
        'bonus_earned',
        'avg_response_time'
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'orders_assigned' => 'integer',
        'orders_delivered' => 'integer',
        'delivery_rate' => 'decimal:2',
        'qualified' => 'boolean',
        'bonus_earned' => 'decimal:2',
        'avg_response_time' => 'decimal:2'
    ];

    // Relationships
    public function telesalesAgent(): BelongsTo
    {
        return $this->belongsTo(TelesalesAgent::class);
    }

    // Scopes
    public function scopeForAgent($query, $agentId)
    {
        return $query->where('telesales_agent_id', $agentId);
    }

    public function scopeForWeek($query, $weekStart)
    {
        return $query->where('week_start', $weekStart);
    }

    public function scopeQualified($query)
    {
        return $query->where('qualified', true);
    }

    public function scopeNotQualified($query)
    {
        return $query->where('qualified', false);
    }

    public function scopeHighPerformers($query, $minRate = 70)
    {
        return $query->where('delivery_rate', '>=', $minRate);
    }

    public function scopeLowPerformers($query, $maxRate = 50)
    {
        return $query->where('delivery_rate', '<=', $maxRate);
    }

    public function scopeThisWeek($query)
    {
        $weekStart = now()->startOfWeek()->format('Y-m-d');
        return $query->where('week_start', $weekStart);
    }

    public function scopeLastWeek($query)
    {
        $lastWeekStart = now()->subWeek()->startOfWeek()->format('Y-m-d');
        return $query->where('week_start', $lastWeekStart);
    }

    // Helper methods
    public function calculateDeliveryRate(): float
    {
        if ($this->orders_assigned == 0) return 0.0;
        return ($this->orders_delivered / $this->orders_assigned) * 100;
    }

    public function isQualified(): bool
    {
        return $this->delivery_rate >= 70 && $this->orders_assigned >= 20;
    }

    public function calculateBonus(): float
    {
        if (!$this->isQualified()) return 0.0;
        return $this->orders_delivered * 150; // ₦150 per delivery
    }

    public function updatePerformance(): void
    {
        $this->delivery_rate = $this->calculateDeliveryRate();
        $this->qualified = $this->isQualified();
        $this->bonus_earned = $this->calculateBonus();
        $this->save();
    }

    public function getWeekLabel(): string
    {
        return $this->week_start->format('M d') . ' - ' . $this->week_end->format('M d, Y');
    }

    public function getPerformanceStatus(): string
    {
        if ($this->qualified) return 'qualified';
        if ($this->delivery_rate >= 50) return 'good';
        if ($this->delivery_rate >= 30) return 'fair';
        return 'poor';
    }

    public function getPerformanceColor(): string
    {
        return match($this->getPerformanceStatus()) {
            'qualified' => 'success',
            'good' => 'info',
            'fair' => 'warning',
            'poor' => 'danger',
            default => 'secondary'
        };
    }
}
