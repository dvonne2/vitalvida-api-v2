<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'objective', // 'awareness', 'consideration', 'conversions', 'app_installs', 'video_views', 'lead_generation'
        'status', // 'draft', 'active', 'paused', 'completed', 'archived'
        'start_date',
        'end_date',
        'budget',
        'budget_type', // 'daily', 'lifetime'
        'target_audience',
        'platforms', // ['facebook', 'instagram', 'tiktok', 'google']
        'ad_account_id',
        'campaign_id_external',
        'created_by',
        'assigned_to',
        'priority', // 'low', 'medium', 'high', 'urgent'
        'tags',
        'performance_goals',
        'creative_brief',
        'brand_guidelines',
        'competitor_analysis',
        'target_metrics',
        'success_criteria',
        'notes',
        'is_automated',
        'automation_rules',
        'ai_optimization_enabled',
        'ai_optimization_settings',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
        'platforms' => 'array',
        'target_audience' => 'array',
        'tags' => 'array',
        'performance_goals' => 'array',
        'creative_brief' => 'array',
        'brand_guidelines' => 'array',
        'competitor_analysis' => 'array',
        'target_metrics' => 'array',
        'success_criteria' => 'array',
        'automation_rules' => 'array',
        'ai_optimization_settings' => 'array',
        'is_automated' => 'boolean',
        'ai_optimization_enabled' => 'boolean',
    ];

    public function creativeAssets(): HasMany
    {
        return $this->hasMany(CreativeAsset::class);
    }

    public function performanceRecords(): HasMany
    {
        return $this->hasMany(CampaignPerformance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByObjective($query, $objective)
    {
        return $query->where('objective', $objective);
    }

    public function scopeByPlatform($query, $platform)
    {
        return $query->whereJsonContains('platforms', $platform);
    }

    public function scopeByAssignee($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeAutomated($query)
    {
        return $query->where('is_automated', true);
    }

    public function scopeAiOptimized($query)
    {
        return $query->where('ai_optimization_enabled', true);
    }

    public function getTotalSpentAttribute()
    {
        return $this->performanceRecords()->sum('spend');
    }

    public function getTotalRevenueAttribute()
    {
        return $this->performanceRecords()->sum('revenue');
    }

    public function getTotalImpressionsAttribute()
    {
        return $this->performanceRecords()->sum('impressions');
    }

    public function getTotalClicksAttribute()
    {
        return $this->performanceRecords()->sum('clicks');
    }

    public function getTotalConversionsAttribute()
    {
        return $this->performanceRecords()->sum('conversions');
    }

    public function getAverageEngagementRateAttribute()
    {
        $records = $this->performanceRecords()->whereNotNull('engagement_rate');
        if ($records->count() === 0) {
            return 0;
        }
        return $records->avg('engagement_rate');
    }

    public function getAverageClickThroughRateAttribute()
    {
        $records = $this->performanceRecords()->whereNotNull('click_through_rate');
        if ($records->count() === 0) {
            return 0;
        }
        return $records->avg('click_through_rate');
    }

    public function getAverageConversionRateAttribute()
    {
        $records = $this->performanceRecords()->whereNotNull('conversion_rate');
        if ($records->count() === 0) {
            return 0;
        }
        return $records->avg('conversion_rate');
    }

    public function getReturnOnAdSpendAttribute()
    {
        $spent = $this->total_spent;
        $revenue = $this->total_revenue;
        
        if ($spent == 0) {
            return 0;
        }
        
        return $revenue / $spent;
    }

    public function getBudgetUtilizationAttribute()
    {
        if ($this->budget == 0) {
            return 0;
        }
        
        return ($this->total_spent / $this->budget) * 100;
    }

    public function getPerformanceGradeAttribute()
    {
        $roas = $this->return_on_ad_spend;
        $engagement = $this->average_engagement_rate;
        $ctr = $this->average_click_through_rate;
        $conversion = $this->average_conversion_rate;
        
        $score = 0;
        
        // ROAS scoring (40% weight)
        if ($roas >= 4) $score += 40;
        elseif ($roas >= 3) $score += 35;
        elseif ($roas >= 2) $score += 30;
        elseif ($roas >= 1.5) $score += 25;
        elseif ($roas >= 1) $score += 20;
        elseif ($roas >= 0.5) $score += 10;
        
        // Engagement scoring (20% weight)
        if ($engagement >= 0.05) $score += 20;
        elseif ($engagement >= 0.03) $score += 15;
        elseif ($engagement >= 0.02) $score += 10;
        elseif ($engagement >= 0.01) $score += 5;
        
        // CTR scoring (20% weight)
        if ($ctr >= 0.03) $score += 20;
        elseif ($ctr >= 0.02) $score += 15;
        elseif ($ctr >= 0.01) $score += 10;
        elseif ($ctr >= 0.005) $score += 5;
        
        // Conversion scoring (20% weight)
        if ($conversion >= 0.05) $score += 20;
        elseif ($conversion >= 0.03) $score += 15;
        elseif ($conversion >= 0.02) $score += 10;
        elseif ($conversion >= 0.01) $score += 5;
        
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B+';
        if ($score >= 60) return 'B';
        if ($score >= 50) return 'C+';
        if ($score >= 40) return 'C';
        if ($score >= 30) return 'D+';
        if ($score >= 20) return 'D';
        return 'F';
    }

    public function getDaysRemainingAttribute()
    {
        if (!$this->end_date) {
            return null;
        }
        
        return max(0, now()->diffInDays($this->end_date, false));
    }

    public function getIsActiveAttribute()
    {
        return $this->status === 'active' && 
               $this->start_date <= now() && 
               ($this->end_date === null || $this->end_date >= now());
    }
}
