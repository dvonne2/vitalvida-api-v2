<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Company;
use App\Models\Customer;

class MarketingCustomerTouchpoint extends Model
{
    use HasUuids;
    
    protected $table = 'marketing_customer_touchpoints';
    
    protected $fillable = [
        'customer_id', 'brand_id', 'channel', 'touchpoint_type', 
        'content_id', 'interaction_type', 'whatsapp_provider', 'metadata',
        'company_id'
    ];
    
    protected $casts = [
        'metadata' => 'array',
    ];
    
    // ERP Relations
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    
    // Marketing Relations
    public function brand()
    {
        return $this->belongsTo(MarketingBrand::class, 'brand_id');
    }
    
    public function content()
    {
        return $this->belongsTo(MarketingContentLibrary::class, 'content_id');
    }
    
    public function campaign()
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }
    
    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
    
    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }
    
    public function scopeWhatsApp($query)
    {
        return $query->where('channel', 'whatsapp');
    }
    
    public function scopeByProvider($query, $provider)
    {
        return $query->where('whatsapp_provider', $provider);
    }
    
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
