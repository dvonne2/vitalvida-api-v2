<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreativeAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type', // 'image', 'video', 'copy', 'audio'
        'content',
        'file_path',
        'file_size',
        'mime_type',
        'dimensions',
        'duration',
        'tags',
        'status', // 'draft', 'review', 'approved', 'published'
        'platform', // 'facebook', 'instagram', 'tiktok', 'google', 'general'
        'campaign_id',
        'created_by',
        'approved_by',
        'approved_at',
        'performance_metrics',
        'ai_generated',
        'generation_prompt',
        'cost',
        'usage_rights',
        'expires_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'dimensions' => 'array',
        'performance_metrics' => 'array',
        'ai_generated' => 'boolean',
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
        'cost' => 'decimal:2',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function performanceRecords(): HasMany
    {
        return $this->hasMany(CampaignPerformance::class, 'creative_asset_id');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeAiGenerated($query)
    {
        return $query->where('ai_generated', true);
    }

    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getPerformanceScoreAttribute()
    {
        if (!$this->performance_metrics) {
            return 0;
        }

        $metrics = $this->performance_metrics;
        $score = 0;
        
        // Calculate weighted score based on various metrics
        if (isset($metrics['engagement_rate'])) {
            $score += $metrics['engagement_rate'] * 0.3;
        }
        if (isset($metrics['click_through_rate'])) {
            $score += $metrics['click_through_rate'] * 0.25;
        }
        if (isset($metrics['conversion_rate'])) {
            $score += $metrics['conversion_rate'] * 0.25;
        }
        if (isset($metrics['reach'])) {
            $score += min($metrics['reach'] / 10000, 1) * 0.2; // Normalize reach
        }

        return round($score, 2);
    }
}
