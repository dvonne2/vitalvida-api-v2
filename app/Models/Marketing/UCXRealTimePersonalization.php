<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Customer;
use App\Models\Company;

class UCXRealTimePersonalization extends Model
{
    use HasFactory;

    protected $table = 'ucx_real_time_personalization';

    protected $fillable = [
        'customer_id',
        'trigger_event',
        'customer_context_at_trigger',
        'personalization_applied',
        'channel_applied',
        'decision_factors',
        'relevancy_score',
        'customer_response',
        'was_effective',
        'applied_at',
        'company_id'
    ];

    protected $casts = [
        'customer_context_at_trigger' => 'array',
        'personalization_applied' => 'array',
        'decision_factors' => 'array',
        'customer_response' => 'array',
        'was_effective' => 'boolean',
        'applied_at' => 'datetime',
        'relevancy_score' => 'decimal:2'
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Check if personalization was effective
     */
    public function isEffective(): bool
    {
        return $this->was_effective === true;
    }

    /**
     * Get the primary personalization type applied
     */
    public function getPrimaryPersonalizationTypeAttribute(): ?string
    {
        $applied = $this->personalization_applied;
        
        if (!$applied || !is_array($applied)) {
            return null;
        }

        // Return the first key as primary type
        return array_key_first($applied);
    }

    /**
     * Get relevancy level based on score
     */
    public function getRelevancyLevelAttribute(): string
    {
        $score = $this->relevancy_score;
        
        return match(true) {
            $score >= 8.5 => 'excellent',
            $score >= 7.0 => 'high',
            $score >= 5.5 => 'moderate',
            $score >= 3.0 => 'low',
            default => 'poor'
        };
    }

    /**
     * Scope for effective personalizations
     */
    public function scopeEffective($query)
    {
        return $query->where('was_effective', true);
    }

    /**
     * Scope for high relevancy personalizations
     */
    public function scopeHighRelevancy($query)
    {
        return $query->where('relevancy_score', '>=', 7.0);
    }

    /**
     * Scope for specific channel
     */
    public function scopeForChannel($query, $channel)
    {
        return $query->where('channel_applied', $channel);
    }

    /**
     * Scope for recent personalizations
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('applied_at', '>=', now()->subHours($hours));
    }
}
