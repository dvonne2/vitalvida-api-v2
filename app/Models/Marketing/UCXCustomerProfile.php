<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Customer;
use App\Models\Company;

class UCXCustomerProfile extends Model
{
    use HasFactory;

    protected $table = 'ucx_customer_profiles';

    protected $fillable = [
        'customer_id',
        'unified_profile',
        'real_time_context',
        'behavior_patterns',
        'preferences_learned',
        'emotional_state',
        'last_interaction',
        'current_journey_stage',
        'next_best_actions',
        'company_id'
    ];

    protected $casts = [
        'unified_profile' => 'array',
        'real_time_context' => 'array',
        'behavior_patterns' => 'array',
        'preferences_learned' => 'array',
        'emotional_state' => 'array',
        'next_best_actions' => 'array',
        'last_interaction' => 'datetime'
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
     * Get the primary emotion from emotional state
     */
    public function getPrimaryEmotionAttribute(): ?string
    {
        return $this->emotional_state['primary_emotion'] ?? null;
    }

    /**
     * Get the current urgency level
     */
    public function getUrgencyLevelAttribute(): string
    {
        return $this->real_time_context['urgency_level'] ?? 'low';
    }

    /**
     * Check if customer is in high-intent state
     */
    public function isHighIntent(): bool
    {
        $intentSignals = $this->real_time_context['intent_signals'] ?? [];
        return ($intentSignals['purchase_intent'] ?? 0) > 8;
    }

    /**
     * Get recommended next actions
     */
    public function getRecommendedActions(): array
    {
        return $this->next_best_actions ?? [];
    }
}
