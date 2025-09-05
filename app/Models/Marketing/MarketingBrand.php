<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Company;
use App\Models\User;

class MarketingBrand extends Model
{
    use HasUuids;
    
    protected $table = 'marketing_brands';
    
    protected $fillable = [
        'name', 'industry', 'primary_color', 'secondary_color', 
        'logo_url', 'brand_voice', 'target_audience', 'whatsapp_config', 'company_id', 'created_by'
    ];
    
    protected $casts = [
        'brand_voice' => 'array',
        'target_audience' => 'array',
        'whatsapp_config' => 'array',
    ];
    
    // ERP Relations
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    // Marketing Relations
    public function campaigns()
    {
        return $this->hasMany(MarketingCampaign::class, 'brand_id');
    }
    
    public function contentLibrary()
    {
        return $this->hasMany(MarketingContentLibrary::class, 'brand_id');
    }
    
    public function customerTouchpoints()
    {
        return $this->hasMany(MarketingCustomerTouchpoint::class, 'brand_id');
    }
    
    public function referrals()
    {
        return $this->hasMany(MarketingReferral::class, 'brand_id');
    }
    
    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
    
    public function scopeActive($query)
    {
        return $query->whereHas('campaigns', function($q) {
            $q->where('status', 'active');
        });
    }
}
