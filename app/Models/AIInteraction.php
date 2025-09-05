<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIInteraction extends Model
{
    use HasFactory;

    protected $table = 'ai_interactions';

    protected $fillable = [
        'customer_id',
        'interaction_type',
        'platform',
        'content_generated',
        'ai_model_used',
        'confidence_score',
        'response_received',
        'conversion_achieved',
        'performance_metrics',
        'cost',
        'revenue_generated',
        'ai_decision_reasoning'
    ];

    protected $casts = [
        'content_generated' => 'array',
        'performance_metrics' => 'array',
        'confidence_score' => 'decimal:2',
        'response_received' => 'boolean',
        'conversion_achieved' => 'boolean',
        'cost' => 'decimal:2',
        'revenue_generated' => 'decimal:2',
        'ai_decision_reasoning' => 'array'
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getROI(): float
    {
        if ($this->cost <= 0) return 0;
        return (($this->revenue_generated - $this->cost) / $this->cost) * 100;
    }

    public function isSuccessful(): bool
    {
        return $this->conversion_achieved || $this->getROI() > 100;
    }

    public function getInteractionIcon(): string
    {
        return match($this->interaction_type) {
            'creative_generation' => '🎨',
            'retargeting_message' => '🎯',
            'churn_prevention' => '🛡️',
            'reorder_reminder' => '🔄',
            'personalized_offer' => '🎁',
            'abandoned_cart' => '🛒',
            'viral_amplification' => '🚀',
            default => '🤖'
        };
    }

    public function getConfidenceLevel(): string
    {
        return match(true) {
            $this->confidence_score >= 0.9 => 'Very High',
            $this->confidence_score >= 0.8 => 'High',
            $this->confidence_score >= 0.7 => 'Medium',
            $this->confidence_score >= 0.6 => 'Low',
            default => 'Very Low'
        };
    }

    public function getConfidenceColor(): string
    {
        return match(true) {
            $this->confidence_score >= 0.8 => 'text-green-600',
            $this->confidence_score >= 0.7 => 'text-yellow-600',
            default => 'text-red-600'
        };
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('interaction_type', $type);
    }

    public function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('conversion_achieved', true);
    }

    public function scopeHighConfidence($query)
    {
        return $query->where('confidence_score', '>=', 0.8);
    }

    public function scopeHighROI($query)
    {
        return $query->whereRaw('(revenue_generated - cost) / cost * 100 > 200');
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
} 