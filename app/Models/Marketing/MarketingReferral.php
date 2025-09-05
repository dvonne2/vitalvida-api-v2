<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Company;
use App\Models\Customer;

class MarketingReferral extends Model
{
    use HasUuids;
    
    protected $table = 'marketing_referrals';
    
    protected $fillable = [
        'brand_id', 'referrer_id', 'referral_code', 'commission_rate',
        'total_conversions', 'total_earnings', 'status', 'referral_data',
        'company_id'
    ];
    
    protected $casts = [
        'referral_data' => 'array',
    ];
    
    // ERP Relations
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    
    public function referrer()
    {
        return $this->belongsTo(Customer::class, 'referrer_id');
    }
    
    // Marketing Relations
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
        return $query->where('status', 'active');
    }
    
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    
    public function scopeTopPerformers($query, $limit = 10)
    {
        return $query->orderBy('total_earnings', 'desc')->limit($limit);
    }
    
    // Accessors
    public function getConversionRateAttribute()
    {
        if ($this->total_conversions > 0) {
            return ($this->total_conversions / $this->total_referrals) * 100;
        }
        return 0;
    }
    
    public function getAverageEarningsPerConversionAttribute()
    {
        if ($this->total_conversions > 0) {
            return $this->total_earnings / $this->total_conversions;
        }
        return 0;
    }
}
