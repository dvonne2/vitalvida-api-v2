<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignPerformance extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'creative_asset_id',
        'platform', // 'facebook', 'instagram', 'tiktok', 'google', 'email', 'sms'
        'date',
        'impressions',
        'reach',
        'clicks',
        'conversions',
        'spend',
        'revenue',
        'engagement_rate',
        'click_through_rate',
        'conversion_rate',
        'cost_per_click',
        'cost_per_conversion',
        'return_on_ad_spend',
        'quality_score',
        'audience_retention',
        'video_views',
        'video_completion_rate',
        'shares',
        'comments',
        'likes',
        'saves',
        'link_clicks',
        'profile_visits',
        'follows',
        'messages',
        'phone_calls',
        'direction_requests',
        'website_visits',
        'app_installs',
        'purchases',
        'add_to_cart',
        'initiate_checkout',
        'complete_registration',
        'custom_events',
        'demographics',
        'placement_performance',
        'device_performance',
        'time_performance',
        'audience_insights',
        'competitor_benchmarks',
        'trend_analysis',
        'optimization_suggestions',
        'notes',
        'tracked_by',
    ];

    protected $casts = [
        'date' => 'date',
        'spend' => 'decimal:2',
        'revenue' => 'decimal:2',
        'engagement_rate' => 'decimal:4',
        'click_through_rate' => 'decimal:4',
        'conversion_rate' => 'decimal:4',
        'cost_per_click' => 'decimal:2',
        'cost_per_conversion' => 'decimal:2',
        'return_on_ad_spend' => 'decimal:2',
        'quality_score' => 'decimal:2',
        'audience_retention' => 'decimal:4',
        'video_completion_rate' => 'decimal:4',
        'demographics' => 'array',
        'placement_performance' => 'array',
        'device_performance' => 'array',
        'time_performance' => 'array',
        'audience_insights' => 'array',
        'competitor_benchmarks' => 'array',
        'trend_analysis' => 'array',
        'optimization_suggestions' => 'array',
        'custom_events' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creativeAsset(): BelongsTo
    {
        return $this->belongsTo(CreativeAsset::class);
    }

    public function tracker(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'tracked_by');
    }

    public function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByCampaign($query, $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    public function scopeByCreativeAsset($query, $creativeAssetId)
    {
        return $query->where('creative_asset_id', $creativeAssetId);
    }

    public function getProfitAttribute()
    {
        return $this->revenue - $this->spend;
    }

    public function getProfitMarginAttribute()
    {
        if ($this->revenue == 0) {
            return 0;
        }
        return (($this->revenue - $this->spend) / $this->revenue) * 100;
    }

    public function getEfficiencyScoreAttribute()
    {
        $score = 0;
        
        // Base score from ROAS
        if ($this->return_on_ad_spend > 0) {
            $score += min($this->return_on_ad_spend * 10, 40); // Max 40 points for ROAS
        }
        
        // Engagement score
        if ($this->engagement_rate > 0) {
            $score += min($this->engagement_rate * 100, 20); // Max 20 points for engagement
        }
        
        // Quality score
        if ($this->quality_score > 0) {
            $score += min($this->quality_score * 2, 20); // Max 20 points for quality
        }
        
        // Conversion efficiency
        if ($this->conversion_rate > 0) {
            $score += min($this->conversion_rate * 100, 20); // Max 20 points for conversions
        }
        
        return round($score, 1);
    }

    public function getPerformanceGradeAttribute()
    {
        $score = $this->efficiency_score;
        
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

    public function getTrendDirectionAttribute()
    {
        // This would typically compare with previous period
        // For now, return a placeholder
        return 'stable';
    }
}
