<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;

class MarketingTrustSignals extends Model
{
    use HasFactory;

    protected $table = 'marketing_trust_signals';

    protected $fillable = [
        'brand_id',
        'signal_type',
        'signal_source',
        'signal_content',
        'source_url',
        'credibility_score',
        'display_channels',
        'is_active',
        'company_id'
    ];

    protected $casts = [
        'display_channels' => 'array',
        'credibility_score' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // ERP Relations
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('signal_type', $type);
    }

    public function scopeHighCredibility($query)
    {
        return $query->where('credibility_score', '>=', 7.0);
    }

    public function scopeForChannel($query, $channel)
    {
        return $query->whereJsonContains('display_channels', $channel);
    }

    // Helper Methods
    public function getCredibilityLevelAttribute()
    {
        if ($this->credibility_score >= 9.0) return 'Exceptional';
        if ($this->credibility_score >= 8.0) return 'High';
        if ($this->credibility_score >= 6.0) return 'Good';
        if ($this->credibility_score >= 4.0) return 'Fair';
        return 'Low';
    }

    public function getTrustTypeDescriptionAttribute()
    {
        return match($this->signal_type) {
            'authority' => 'Establish expertise in your field',
            'social_proof' => 'Testimonials, reviews, user-generated content',
            'familiarity' => 'Reassuring omnipresence through consistent messaging',
            'demonstration' => 'Show, don\'t just tell',
            default => 'Unknown trust signal type'
        };
    }
}
