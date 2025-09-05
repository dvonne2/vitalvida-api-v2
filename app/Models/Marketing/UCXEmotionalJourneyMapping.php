<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Customer;
use App\Models\Company;

class UCXEmotionalJourneyMapping extends Model
{
    use HasFactory;

    protected $table = 'ucx_emotional_journey_mapping';

    protected $fillable = [
        'customer_id',
        'journey_id',
        'journey_stage',
        'emotional_markers',
        'emotional_intensity',
        'triggers_identified',
        'sentiment_analysis',
        'channel_when_measured',
        'response_strategy',
        'measured_at',
        'company_id'
    ];

    protected $casts = [
        'emotional_markers' => 'array',
        'triggers_identified' => 'array',
        'sentiment_analysis' => 'array',
        'response_strategy' => 'array',
        'emotional_intensity' => 'decimal:2',
        'measured_at' => 'datetime'
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
     * Get the primary emotion from emotional markers
     */
    public function getPrimaryEmotionAttribute(): ?string
    {
        $markers = $this->emotional_markers;
        
        if (!$markers || !is_array($markers) || empty($markers)) {
            return null;
        }

        // Count frequency of emotions and return most common
        $emotionCounts = array_count_values($markers);
        arsort($emotionCounts);
        
        return array_key_first($emotionCounts);
    }

    /**
     * Get emotional intensity level
     */
    public function getIntensityLevelAttribute(): string
    {
        $intensity = $this->emotional_intensity;
        
        return match(true) {
            $intensity >= 8.0 => 'very_high',
            $intensity >= 6.0 => 'high',
            $intensity >= 4.0 => 'moderate',
            $intensity >= 2.0 => 'low',
            default => 'very_low'
        };
    }

    /**
     * Get sentiment label
     */
    public function getSentimentLabelAttribute(): string
    {
        $sentiment = $this->sentiment_analysis['score'] ?? 5;
        
        return match(true) {
            $sentiment >= 7 => 'positive',
            $sentiment >= 4 => 'neutral',
            default => 'negative'
        };
    }

    /**
     * Check if emotion is positive
     */
    public function isPositiveEmotion(): bool
    {
        $positiveEmotions = ['happy', 'excited', 'satisfied', 'confident', 'trust', 'joy', 'love'];
        $primaryEmotion = $this->primary_emotion;
        
        return $primaryEmotion && in_array(strtolower($primaryEmotion), $positiveEmotions);
    }

    /**
     * Check if emotion is negative
     */
    public function isNegativeEmotion(): bool
    {
        $negativeEmotions = ['angry', 'frustrated', 'confused', 'disappointed', 'fear', 'sad', 'anxious'];
        $primaryEmotion = $this->primary_emotion;
        
        return $primaryEmotion && in_array(strtolower($primaryEmotion), $negativeEmotions);
    }

    /**
     * Get the most significant trigger
     */
    public function getPrimaryTriggerAttribute(): ?string
    {
        $triggers = $this->triggers_identified;
        
        if (!$triggers || !is_array($triggers) || empty($triggers)) {
            return null;
        }

        // Return first trigger or most significant one
        return is_array($triggers[0]) ? $triggers[0]['trigger'] ?? null : $triggers[0];
    }

    /**
     * Scope for specific journey stage
     */
    public function scopeForStage($query, $stage)
    {
        return $query->where('journey_stage', $stage);
    }

    /**
     * Scope for high intensity emotions
     */
    public function scopeHighIntensity($query)
    {
        return $query->where('emotional_intensity', '>=', 6.0);
    }

    /**
     * Scope for positive emotions
     */
    public function scopePositiveEmotions($query)
    {
        $positiveEmotions = ['happy', 'excited', 'satisfied', 'confident', 'trust', 'joy', 'love'];
        
        return $query->whereJsonContains('emotional_markers', $positiveEmotions);
    }

    /**
     * Scope for negative emotions
     */
    public function scopeNegativeEmotions($query)
    {
        $negativeEmotions = ['angry', 'frustrated', 'confused', 'disappointed', 'fear', 'sad', 'anxious'];
        
        return $query->whereJsonContains('emotional_markers', $negativeEmotions);
    }

    /**
     * Scope for specific channel
     */
    public function scopeForChannel($query, $channel)
    {
        return $query->where('channel_when_measured', $channel);
    }

    /**
     * Scope for recent measurements
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('measured_at', '>=', now()->subHours($hours));
    }

    /**
     * Get emotional trend over time
     */
    public static function getEmotionalTrend($customerId, $days = 30)
    {
        return static::where('customer_id', $customerId)
            ->where('measured_at', '>=', now()->subDays($days))
            ->orderBy('measured_at')
            ->get()
            ->groupBy(function($item) {
                return $item->measured_at->format('Y-m-d');
            })
            ->map(function($dayMeasurements) {
                return [
                    'avg_intensity' => $dayMeasurements->avg('emotional_intensity'),
                    'primary_emotions' => $dayMeasurements->pluck('primary_emotion')->filter()->unique()->values(),
                    'sentiment_score' => $dayMeasurements->avg('sentiment_analysis.score')
                ];
            });
    }
}
