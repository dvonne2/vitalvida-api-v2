<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryAgent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'da_code',
        'vehicle_number',
        'vehicle_type',
        'status',
        'current_location',
        'total_deliveries',
        'successful_deliveries',
        'rating',
        'total_earnings',
        'working_hours',
        'service_areas',
    ];

    protected $casts = [
        'working_hours' => 'array',
        'service_areas' => 'array',
        'rating' => 'decimal:2',
        'total_earnings' => 'decimal:2',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get bins assigned to this delivery agent
     */
    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class, 'assigned_to_da', 'da_code');
    }

    public function strikes(): HasMany
    {
        return $this->hasMany(StrikeLog::class);
    }

    public function recentStrikes(): HasMany
    {
        return $this->hasMany(StrikeLog::class)
            ->where('created_at', '>=', now()->subDays(30));
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getSuccessRate(): float
    {
        if ($this->total_deliveries === 0) return 0;
        return round(($this->successful_deliveries / $this->total_deliveries) * 100, 1);
    }

    /**
     * Get comprehensive performance including bins
     */
    public function getFullPerformance(): array
    {
        $bins = $this->bins()->count();
        $avgBinUtilization = $this->bins()->get()->avg(function($bin) {
            return $bin->getUtilizationRate();
        });

        return [
            'delivery_performance' => [
                'total_deliveries' => $this->total_deliveries,
                'successful_deliveries' => $this->successful_deliveries,
                'success_rate' => $this->getSuccessRate(),
                'rating' => floatval($this->rating),
                'total_earnings' => floatval($this->total_earnings),
            ],
            'bin_management' => [
                'bins_assigned' => $bins,
                'avg_utilization' => round($avgBinUtilization ?? 0, 1),
            ],
            'recent_strikes' => $this->recentStrikes()->count(),
        ];
    }

    public function updateDeliveryStats(bool $successful = true): void
    {
        $this->increment('total_deliveries');
        
        if ($successful) {
            $this->increment('successful_deliveries');
        }
        
        $this->rating = $this->getSuccessRate() / 100 * 5;
        $this->save();
    }

    public function addEarnings(float $amount): void
    {
        $this->increment('total_earnings', $amount);
    }

    public function issueStrike(string $reason, string $severity = 'medium', ?int $issuedBy = null, ?string $notes = null): StrikeLog
    {
        return StrikeLog::create([
            'delivery_agent_id' => $this->id,
            'reason' => $reason,
            'severity' => $severity,
            'issued_by' => $issuedBy,
            'notes' => $notes,
            'source' => 'manual',
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeWithMinDeliveries($query, int $minDeliveries = 1)
    {
        return $query->where('total_deliveries', '>=', $minDeliveries);
    }

    public function scopeTopPerformers($query, int $limit = 10)
    {
        return $query->active()
            ->withMinDeliveries(5)
            ->orderByRaw('(successful_deliveries * 100.0 / total_deliveries) DESC')
            ->limit($limit);
    }
}
