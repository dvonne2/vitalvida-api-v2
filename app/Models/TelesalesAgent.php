<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelesalesAgent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'employment_start',
        'status',
        'accumulated_bonus',
        'bonus_unlocked',
        'weekly_performance'
    ];

    protected $casts = [
        'employment_start' => 'date',
        'accumulated_bonus' => 'decimal:2',
        'bonus_unlocked' => 'boolean',
        'weekly_performance' => 'array'
    ];

    // Relationships
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'assigned_telesales_id');
    }

    public function weeklyPerformances(): HasMany
    {
        return $this->hasMany(WeeklyPerformance::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeTerminated($query)
    {
        return $query->where('status', 'terminated');
    }

    public function scopeBonusUnlocked($query)
    {
        return $query->where('bonus_unlocked', true);
    }

    public function scopeBonusLocked($query)
    {
        return $query->where('bonus_unlocked', false);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isEmployedForThreeMonths(): bool
    {
        return $this->employment_start->diffInMonths(now()) >= 3;
    }

    public function canUnlockBonus(): bool
    {
        return $this->isEmployedForThreeMonths() && !$this->bonus_unlocked;
    }

    public function unlockBonus(): void
    {
        if ($this->canUnlockBonus()) {
            $this->update(['bonus_unlocked' => true]);
        }
    }

    public function addToAccumulatedBonus(float $amount): void
    {
        $this->increment('accumulated_bonus', $amount);
    }

    public function getWeeklyPerformance(string $weekStart): ?array
    {
        return $this->weekly_performance[$weekStart] ?? null;
    }

    public function setWeeklyPerformance(string $weekStart, array $data): void
    {
        $performance = $this->weekly_performance ?? [];
        $performance[$weekStart] = $data;
        $this->update(['weekly_performance' => $performance]);
    }

    public function getCurrentWeekPerformance(): ?array
    {
        $weekStart = now()->startOfWeek()->format('Y-m-d');
        return $this->getWeeklyPerformance($weekStart);
    }

    public function getDeliveryRate(): float
    {
        $currentWeek = $this->getCurrentWeekPerformance();
        if (!$currentWeek) return 0.0;
        
        $assigned = $currentWeek['orders_assigned'] ?? 0;
        $delivered = $currentWeek['orders_delivered'] ?? 0;
        
        return $assigned > 0 ? ($delivered / $assigned) * 100 : 0.0;
    }

    public function isQualifiedForBonus(): bool
    {
        $currentWeek = $this->getCurrentWeekPerformance();
        if (!$currentWeek) return false;
        
        $deliveryRate = $this->getDeliveryRate();
        $ordersCount = $currentWeek['orders_assigned'] ?? 0;
        
        return $deliveryRate >= 70 && $ordersCount >= 20;
    }

    public function calculateWeeklyBonus(): float
    {
        if (!$this->isQualifiedForBonus()) return 0.0;
        
        $currentWeek = $this->getCurrentWeekPerformance();
        $delivered = $currentWeek['orders_delivered'] ?? 0;
        
        return $delivered * 150; // ₦150 per delivery
    }
}
