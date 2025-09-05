<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Customer;
use App\Models\Company;

class MarketingIntimacyTracking extends Model
{
    use HasFactory;

    protected $table = 'marketing_intimacy_tracking';

    protected $fillable = [
        'customer_id',
        'brand_id',
        'intimacy_score',
        'total_interactions',
        'interaction_quality',
        'preference_data',
        'emotional_triggers',
        'relationship_started',
        'company_id'
    ];

    protected $casts = [
        'interaction_quality' => 'array',
        'preference_data' => 'array',
        'emotional_triggers' => 'array',
        'intimacy_score' => 'decimal:2',
        'relationship_started' => 'date',
    ];

    // ERP Relations
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function brand()
    {
        return $this->belongsTo(MarketingBrand::class, 'brand_id');
    }

    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeHighIntimacy($query)
    {
        return $query->where('intimacy_score', '>=', 7.0);
    }

    public function scopeByBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeActiveRelationships($query)
    {
        return $query->where('total_interactions', '>', 0)
                    ->where('intimacy_score', '>', 0);
    }

    // Helper Methods
    public function getRelationshipStrengthAttribute()
    {
        if ($this->intimacy_score >= 8.5) return 'Very Strong';
        if ($this->intimacy_score >= 7.0) return 'Strong';
        if ($this->intimacy_score >= 5.5) return 'Moderate';
        if ($this->intimacy_score >= 3.0) return 'Weak';
        return 'Very Weak';
    }

    public function getRelationshipAgeAttribute()
    {
        return $this->relationship_started->diffInDays(now());
    }

    public function getInteractionFrequencyAttribute()
    {
        $days = max(1, $this->getRelationshipAgeAttribute());
        return round($this->total_interactions / $days, 2);
    }

    public function getTopEmotionalTriggersAttribute()
    {
        if (!$this->emotional_triggers) return [];
        
        return collect($this->emotional_triggers)
            ->sortByDesc('strength')
            ->take(3)
            ->pluck('trigger')
            ->toArray();
    }
}
