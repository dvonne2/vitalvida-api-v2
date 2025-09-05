<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Customer;
use App\Models\Company;

class MarketingRelevancyScoring extends Model
{
    use HasFactory;

    protected $table = 'marketing_relevancy_scoring';

    protected $fillable = [
        'customer_id',
        'content_id',
        'relevancy_score',
        'relevancy_factors',
        'customer_stage',
        'personalization_data',
        'scored_at',
        'company_id'
    ];

    protected $casts = [
        'relevancy_factors' => 'array',
        'personalization_data' => 'array',
        'relevancy_score' => 'decimal:2',
        'scored_at' => 'datetime',
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

    public function content()
    {
        return $this->belongsTo(MarketingContentLibrary::class, 'content_id');
    }

    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeHighRelevancy($query)
    {
        return $query->where('relevancy_score', '>=', 7.5);
    }

    public function scopeByStage($query, $stage)
    {
        return $query->where('customer_stage', $stage);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('scored_at', '>=', now()->subDays($days));
    }

    // Helper Methods
    public function getRelevancyLevelAttribute()
    {
        if ($this->relevancy_score >= 8.5) return 'Excellent';
        if ($this->relevancy_score >= 7.5) return 'High';
        if ($this->relevancy_score >= 6.0) return 'Medium';
        if ($this->relevancy_score >= 4.0) return 'Low';
        return 'Poor';
    }

    public function getRecommendationAttribute()
    {
        return $this->relevancy_score >= 7.5 ? 'SEND - High relevancy' : 'HOLD - Low relevancy';
    }

    public function getTopRelevancyFactorsAttribute()
    {
        if (!$this->relevancy_factors) return [];
        
        return collect($this->relevancy_factors)
            ->sortDesc()
            ->take(3)
            ->keys()
            ->toArray();
    }
}
