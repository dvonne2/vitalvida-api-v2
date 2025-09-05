<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Company;
use App\Models\User;

class MarketingCampaign extends Model
{
    use HasUuids;
    
    protected $table = 'marketing_campaigns';
    
    protected $fillable = [
        'brand_id', 'name', 'status', 'channels', 'whatsapp_providers',
        'start_date', 'end_date', 'budget_total', 'actual_spend', 'actual_revenue', 
        'company_id', 'created_by'
    ];
    
    protected $casts = [
        'channels' => 'array',
        'whatsapp_providers' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
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
    public function brand()
    {
        return $this->belongsTo(MarketingBrand::class, 'brand_id');
    }
    
    public function customerTouchpoints()
    {
        return $this->hasMany(MarketingCustomerTouchpoint::class, 'campaign_id');
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
    
    // Accessors
    public function getRoiAttribute()
    {
        if ($this->actual_spend > 0) {
            return ($this->actual_revenue - $this->actual_spend) / $this->actual_spend;
        }
        return 0;
    }
    
    public function getRoiPercentageAttribute()
    {
        return $this->roi * 100;
    }
    
    public function getBudgetUtilizationAttribute()
    {
        if ($this->budget_total > 0) {
            return ($this->actual_spend / $this->budget_total) * 100;
        }
        return 0;
    }
}
