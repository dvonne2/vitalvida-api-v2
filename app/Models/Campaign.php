<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'campaign_type',
        'platform',
        'status',
        'budget',
        'spent',
        'target_audience',
        'start_date',
        'end_date',
        'performance_metrics',
        'ai_optimization_enabled',
        'auto_scale_enabled',
        'target_cpo',
        'target_ctr',
        'actual_cpo',
        'actual_ctr',
        'orders_generated',
        'revenue_generated',
        'roi'
    ];

    protected $casts = [
        'target_audience' => 'array',
        'performance_metrics' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'budget' => 'decimal:2',
        'spent' => 'decimal:2',
        'target_cpo' => 'decimal:2',
        'target_ctr' => 'decimal:4',
        'actual_cpo' => 'decimal:2',
        'actual_ctr' => 'decimal:4',
        'orders_generated' => 'integer',
        'revenue_generated' => 'decimal:2',
        'roi' => 'decimal:2',
        'ai_optimization_enabled' => 'boolean',
        'auto_scale_enabled' => 'boolean'
    ];

    public function creatives(): HasMany
    {
        return $this->hasMany(AICreative::class);
    }

    public function retargetingCampaigns(): HasMany
    {
        return $this->hasMany(RetargetingCampaign::class);
    }

    public function getROI(): float
    {
        if ($this->spent <= 0) return 0;
        return (($this->revenue_generated - $this->spent) / $this->spent) * 100;
    }

    public function isPerformingWell(): bool
    {
        return $this->actual_cpo <= $this->target_cpo && $this->actual_ctr >= $this->target_ctr;
    }

    public function shouldScale(): bool
    {
        return $this->isPerformingWell() && $this->spent < $this->budget * 0.8;
    }

    public function shouldPause(): bool
    {
        return $this->actual_cpo > $this->target_cpo * 1.5 || $this->actual_ctr < $this->target_ctr * 0.5;
    }

    public function getPerformanceGrade(): string
    {
        $roi = $this->getROI();
        $cpoRatio = $this->target_cpo > 0 ? $this->actual_cpo / $this->target_cpo : 1;
        $ctrRatio = $this->target_ctr > 0 ? $this->actual_ctr / $this->target_ctr : 1;

        if ($roi >= 300 && $cpoRatio <= 0.8 && $ctrRatio >= 1.2) return 'A+';
        if ($roi >= 200 && $cpoRatio <= 1.0 && $ctrRatio >= 1.0) return 'A';
        if ($roi >= 150 && $cpoRatio <= 1.2 && $ctrRatio >= 0.8) return 'B';
        if ($roi >= 100 && $cpoRatio <= 1.5 && $ctrRatio >= 0.6) return 'C';
        return 'D';
    }

    public function getStatusBadge(): string
    {
        return match($this->status) {
            'draft' => 'bg-gray-100 text-gray-800',
            'active' => 'bg-green-100 text-green-800',
            'paused' => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-blue-100 text-blue-800',
            'cancelled' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getPlatformIcon(): string
    {
        return match($this->platform) {
            'meta' => '📘',
            'tiktok' => '🎵',
            'google' => '🔍',
            'youtube' => '📺',
            'whatsapp' => '💬',
            'sms' => '📱',
            'email' => '📧',
            default => '🌐'
        };
    }

    public function getBudgetUtilization(): float
    {
        if ($this->budget <= 0) return 0;
        return ($this->spent / $this->budget) * 100;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeHighROI($query)
    {
        return $query->whereRaw('(revenue_generated - spent) / spent * 100 > 200');
    }

    public function scopePerformingWell($query)
    {
        return $query->whereRaw('actual_cpo <= target_cpo AND actual_ctr >= target_ctr');
    }

    public function scopeNeedsOptimization($query)
    {
        return $query->whereRaw('actual_cpo > target_cpo * 1.5 OR actual_ctr < target_ctr * 0.5');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('campaign_type', $type);
    }
} 