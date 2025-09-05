<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'staff_type',
        'state_assigned',
        'performance_score',
        'daily_limit',
        'status',
        'hire_date',
        'guarantor_info',
        'commission_rate',
        'target_orders',
        'completed_orders',
        'ghosted_orders',
        'total_earnings',
        'last_activity_date',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'performance_score' => 'decimal:2',
        'daily_limit' => 'integer',
        'commission_rate' => 'decimal:2',
        'target_orders' => 'integer',
        'completed_orders' => 'integer',
        'ghosted_orders' => 'integer',
        'total_earnings' => 'decimal:2',
        'hire_date' => 'date',
        'last_activity_date' => 'datetime',
        'guarantor_info' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'assigned_telesales_id', 'user_id');
    }

    public function deliveryAgent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class, 'user_id', 'user_id');
    }

    public function telesalesPerformance(): HasMany
    {
        return $this->hasMany(TelesalesPerformance::class, 'staff_id');
    }

    public function orderReroutings(): HasMany
    {
        return $this->hasMany(OrderRerouting::class, 'from_staff_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('staff_type', $type);
    }

    public function scopeByState($query, $state)
    {
        return $query->where('state_assigned', $state);
    }

    public function scopeHighPerformers($query, $minScore = 80)
    {
        return $query->where('performance_score', '>=', $minScore);
    }

    public function scopeLowPerformers($query, $maxScore = 60)
    {
        return $query->where('performance_score', '<=', $maxScore);
    }

    // Accessors
    public function getDeliveryRateAttribute(): float
    {
        $totalOrders = $this->completed_orders + $this->ghosted_orders;
        return $totalOrders > 0 ? round(($this->completed_orders / $totalOrders) * 100, 2) : 0;
    }

    public function getGhostRateAttribute(): float
    {
        $totalOrders = $this->completed_orders + $this->ghosted_orders;
        return $totalOrders > 0 ? round(($this->ghosted_orders / $totalOrders) * 100, 2) : 0;
    }

    public function getPerformanceStatusAttribute(): string
    {
        if ($this->performance_score >= 90) return 'excellent';
        if ($this->performance_score >= 80) return 'good';
        if ($this->performance_score >= 70) return 'average';
        if ($this->performance_score >= 60) return 'below_average';
        return 'poor';
    }

    public function getPerformanceColorAttribute(): string
    {
        return match($this->performance_status) {
            'excellent' => 'green',
            'good' => 'blue',
            'average' => 'yellow',
            'below_average' => 'orange',
            'poor' => 'red',
            default => 'gray'
        };
    }

    public function getFormattedEarningsAttribute(): string
    {
        return '₦' . number_format($this->total_earnings, 2);
    }

    public function getTenureAttribute(): int
    {
        return $this->hire_date ? $this->hire_date->diffInDays(now()) : 0;
    }

    // Business Methods
    public function isDeliveryAgent(): bool
    {
        return $this->staff_type === 'delivery_agent';
    }

    public function isTelesalesRep(): bool
    {
        return $this->staff_type === 'telesales_rep';
    }

    public function isManager(): bool
    {
        return in_array($this->staff_type, ['gm', 'coo']);
    }

    public function canHandleMoreOrders(): bool
    {
        return $this->completed_orders < $this->daily_limit;
    }

    public function updatePerformanceScore(): void
    {
        $deliveryRate = $this->delivery_rate;
        $ghostRate = $this->ghost_rate;
        $tenure = $this->tenure;

        // Calculate performance score based on multiple factors
        $score = 0;
        
        // Delivery rate weight: 40%
        $score += ($deliveryRate / 100) * 40;
        
        // Ghost rate penalty: -30% for high ghost rate
        if ($ghostRate > 20) {
            $score -= ($ghostRate / 100) * 30;
        }
        
        // Tenure bonus: +10% for experienced staff
        if ($tenure > 30) {
            $score += 10;
        }
        
        // Ensure score is between 0 and 100
        $score = max(0, min(100, $score));
        
        $this->update(['performance_score' => $score]);
    }

    public function incrementCompletedOrders(): void
    {
        $this->increment('completed_orders');
        $this->updatePerformanceScore();
    }

    public function incrementGhostedOrders(): void
    {
        $this->increment('ghosted_orders');
        $this->updatePerformanceScore();
    }

    public function addEarnings(float $amount): void
    {
        $this->increment('total_earnings', $amount);
    }

    public function isAtRisk(): bool
    {
        return $this->ghost_rate > 80 || $this->performance_score < 60;
    }

    public function getPerformanceSummary(): array
    {
        return [
            'staff_id' => $this->user_id,
            'name' => $this->user->name,
            'staff_type' => $this->staff_type,
            'state' => $this->state_assigned,
            'performance_score' => $this->performance_score,
            'delivery_rate' => $this->delivery_rate,
            'ghost_rate' => $this->ghost_rate,
            'total_orders' => $this->completed_orders + $this->ghosted_orders,
            'completed_orders' => $this->completed_orders,
            'ghosted_orders' => $this->ghosted_orders,
            'total_earnings' => $this->total_earnings,
            'status' => $this->status,
            'is_at_risk' => $this->isAtRisk(),
            'can_handle_more' => $this->canHandleMoreOrders(),
        ];
    }
}
