<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelesalesPerformance extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'date',
        'orders_assigned',
        'orders_attended',
        'orders_delivered',
        'orders_ghosted',
        'delivery_rate',
        'bonus_eligible',
        'status',
        'total_earnings',
        'commission_earned',
        'bonus_amount',
        'penalties',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'orders_assigned' => 'integer',
        'orders_attended' => 'integer',
        'orders_delivered' => 'integer',
        'orders_ghosted' => 'integer',
        'delivery_rate' => 'decimal:2',
        'bonus_eligible' => 'boolean',
        'total_earnings' => 'decimal:2',
        'commission_earned' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'penalties' => 'decimal:2',
    ];

    // Relationships
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    // Scopes
    public function scopeByDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeByStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    public function scopeBonusEligible($query)
    {
        return $query->where('bonus_eligible', true);
    }

    public function scopeHighPerformers($query, $minRate = 80)
    {
        return $query->where('delivery_rate', '>=', $minRate);
    }

    public function scopeLowPerformers($query, $maxRate = 60)
    {
        return $query->where('delivery_rate', '<=', $maxRate);
    }

    public function scopeToday($query)
    {
        return $query->where('date', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year);
    }

    // Accessors
    public function getGhostRateAttribute(): float
    {
        $totalOrders = $this->orders_attended;
        return $totalOrders > 0 ? round(($this->orders_ghosted / $totalOrders) * 100, 2) : 0;
    }

    public function getAttendanceRateAttribute(): float
    {
        $totalAssigned = $this->orders_assigned;
        return $totalAssigned > 0 ? round(($this->orders_attended / $totalAssigned) * 100, 2) : 0;
    }

    public function getPerformanceStatusAttribute(): string
    {
        if ($this->delivery_rate >= 90) return 'excellent';
        if ($this->delivery_rate >= 80) return 'good';
        if ($this->delivery_rate >= 70) return 'average';
        if ($this->delivery_rate >= 60) return 'below_average';
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

    public function getFormattedCommissionAttribute(): string
    {
        return '₦' . number_format($this->commission_earned, 2);
    }

    public function getFormattedBonusAttribute(): string
    {
        return '₦' . number_format($this->bonus_amount, 2);
    }

    public function getNetEarningsAttribute(): float
    {
        return $this->total_earnings + $this->commission_earned + $this->bonus_amount - $this->penalties;
    }

    public function getFormattedNetEarningsAttribute(): string
    {
        return '₦' . number_format($this->net_earnings, 2);
    }

    // Business Methods
    public function calculateDeliveryRate(): void
    {
        $totalOrders = $this->orders_attended;
        if ($totalOrders > 0) {
            $this->delivery_rate = round(($this->orders_delivered / $totalOrders) * 100, 2);
        } else {
            $this->delivery_rate = 0;
        }
    }

    public function checkBonusEligibility(): void
    {
        $this->bonus_eligible = $this->delivery_rate >= 80 && $this->orders_delivered >= 10;
    }

    public function calculateCommission(): void
    {
        // Commission calculation logic
        $commissionRate = 0.05; // 5% commission
        $this->commission_earned = $this->orders_delivered * 1000 * $commissionRate; // Assuming ₦1000 per order
    }

    public function calculateBonus(): void
    {
        if ($this->bonus_eligible) {
            $bonusRate = 0.02; // 2% bonus
            $this->bonus_amount = $this->orders_delivered * 1000 * $bonusRate;
        } else {
            $this->bonus_amount = 0;
        }
    }

    public function calculatePenalties(): void
    {
        $penaltyRate = 0.01; // 1% penalty for ghosted orders
        $this->penalties = $this->orders_ghosted * 1000 * $penaltyRate;
    }

    public function updatePerformance(): void
    {
        $this->calculateDeliveryRate();
        $this->checkBonusEligibility();
        $this->calculateCommission();
        $this->calculateBonus();
        $this->calculatePenalties();
        $this->save();
    }

    public function isAtRisk(): bool
    {
        return $this->ghost_rate > 80 || $this->delivery_rate < 60;
    }

    public function getPerformanceSummary(): array
    {
        return [
            'staff_id' => $this->staff_id,
            'staff_name' => $this->user->name ?? 'Unknown',
            'date' => $this->date->format('M d, Y'),
            'orders_assigned' => $this->orders_assigned,
            'orders_attended' => $this->orders_attended,
            'orders_delivered' => $this->orders_delivered,
            'orders_ghosted' => $this->orders_ghosted,
            'delivery_rate' => $this->delivery_rate,
            'ghost_rate' => $this->ghost_rate,
            'attendance_rate' => $this->attendance_rate,
            'performance_status' => $this->performance_status,
            'bonus_eligible' => $this->bonus_eligible,
            'total_earnings' => $this->total_earnings,
            'commission_earned' => $this->commission_earned,
            'bonus_amount' => $this->bonus_amount,
            'penalties' => $this->penalties,
            'net_earnings' => $this->net_earnings,
            'is_at_risk' => $this->isAtRisk(),
        ];
    }
}
