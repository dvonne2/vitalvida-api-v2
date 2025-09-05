<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Customer;
use App\Models\Company;

class MarketingCustomerPresenceMap extends Model
{
    use HasFactory;

    protected $table = 'marketing_customer_presence_map';

    protected $fillable = [
        'customer_id',
        'channel',
        'engagement_score',
        'frequency_hours',
        'behavior_patterns',
        'conversion_rate',
        'last_active',
        'company_id'
    ];

    protected $casts = [
        'behavior_patterns' => 'array',
        'engagement_score' => 'decimal:2',
        'conversion_rate' => 'decimal:4',
        'last_active' => 'datetime',
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

    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeHighEngagement($query)
    {
        return $query->where('engagement_score', '>=', 7.0);
    }

    public function scopeActiveChannels($query)
    {
        return $query->whereNotNull('last_active')
                    ->where('last_active', '>=', now()->subDays(30));
    }

    // Helper Methods
    public function getEngagementLevelAttribute()
    {
        if ($this->engagement_score >= 8.0) return 'High';
        if ($this->engagement_score >= 6.0) return 'Medium';
        if ($this->engagement_score >= 4.0) return 'Low';
        return 'Inactive';
    }

    public function getConversionEffectivenessAttribute()
    {
        if ($this->conversion_rate >= 0.05) return 'Excellent';
        if ($this->conversion_rate >= 0.03) return 'Good';
        if ($this->conversion_rate >= 0.01) return 'Fair';
        return 'Poor';
    }
}
