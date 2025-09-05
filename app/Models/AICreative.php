<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AICreative extends Model
{
    use HasFactory;

    protected $table = 'ai_creatives';

    protected $fillable = [
        'type',
        'platform',
        'prompt_used',
        'content_url',
        'thumbnail_url',
        'copy_text',
        'performance_score',
        'cpo',
        'ctr',
        'orders_generated',
        'spend',
        'revenue',
        'status',
        'ai_confidence_score',
        'target_audience',
        'campaign_id',
        'ad_set_id',
        'ad_id',
        'parent_creative_id',
        'variation_style'
    ];

    protected $casts = [
        'performance_score' => 'decimal:2',
        'cpo' => 'decimal:2',
        'ctr' => 'decimal:4',
        'ai_confidence_score' => 'decimal:2',
        'target_audience' => 'array',
        'spend' => 'decimal:2',
        'revenue' => 'decimal:2'
    ];

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function variations(): HasMany
    {
        return $this->hasMany(AICreative::class, 'parent_creative_id');
    }

    public function parent(): HasMany
    {
        return $this->belongsTo(AICreative::class, 'parent_creative_id');
    }

    public function generateVariations(): array
    {
        $variations = [];
        $basePrompt = $this->prompt_used;
        
        $styleVariations = [
            'emotional' => "Make this more emotional and heart-touching",
            'urgent' => "Add urgency and scarcity elements", 
            'social_proof' => "Include social proof and testimonials",
            'problem_focused' => "Focus on the pain point",
            'solution_focused' => "Focus on the transformation"
        ];

        foreach ($styleVariations as $style => $modifier) {
            $variations[] = [
                'type' => $this->type,
                'platform' => $this->platform,
                'prompt_used' => $basePrompt . ". " . $modifier,
                'target_audience' => $this->target_audience,
                'parent_creative_id' => $this->id,
                'variation_style' => $style
            ];
        }

        return $variations;
    }

    public function shouldScale(): bool
    {
        return $this->cpo <= 1200 && $this->ctr >= 0.015 && $this->orders_generated >= 5;
    }

    public function shouldKill(): bool
    {
        return $this->cpo > 2500 || ($this->ctr < 0.005 && $this->spend > 10000);
    }

    public function calculateROI(): float
    {
        if ($this->spend <= 0) return 0;
        return (($this->revenue - $this->spend) / $this->spend) * 100;
    }

    public function getPerformanceGrade(): string
    {
        if ($this->cpo <= 1000 && $this->ctr >= 0.02) return 'A+';
        if ($this->cpo <= 1200 && $this->ctr >= 0.015) return 'A';
        if ($this->cpo <= 1500 && $this->ctr >= 0.01) return 'B';
        if ($this->cpo <= 2000 && $this->ctr >= 0.008) return 'C';
        return 'D';
    }

    public function isWinning(): bool
    {
        return $this->shouldScale() && $this->calculateROI() > 200;
    }

    public function isLosing(): bool
    {
        return $this->shouldKill() || $this->calculateROI() < 50;
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

    public function scopeWinning($query)
    {
        return $query->where('cpo', '<=', 1200)
                    ->where('ctr', '>=', 0.015)
                    ->where('orders_generated', '>=', 5);
    }

    public function scopeLosing($query)
    {
        return $query->where(function($q) {
            $q->where('cpo', '>', 2500)
              ->orWhere(function($q2) {
                  $q2->where('ctr', '<', 0.005)
                     ->where('spend', '>', 10000);
              });
        });
    }

    public function scopeHighROI($query)
    {
        return $query->whereRaw('(revenue - spend) / spend * 100 > 200');
    }
} 