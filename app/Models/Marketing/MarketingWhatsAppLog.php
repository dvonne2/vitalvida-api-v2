<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use App\Models\Company;
use App\Models\User;

class MarketingWhatsAppLog extends Model
{
    protected $table = 'marketing_whatsapp_logs';
    
    protected $fillable = [
        'phone', 'message', 'provider', 'status', 'error_message',
        'response_data', 'company_id', 'user_id', 'campaign_id'
    ];
    
    protected $casts = [
        'response_data' => 'array',
        'created_at' => 'datetime',
    ];
    
    // Relations
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
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
    
    public function scopeByProvider($query, $provider)
    {
        return $query->where('provider', $provider);
    }
    
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }
    
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
    
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
    
    // Accessors
    public function getFormattedPhoneAttribute()
    {
        return '+234' . substr($this->phone, -10);
    }
    
    public function getMessagePreviewAttribute()
    {
        return substr($this->message, 0, 50) . (strlen($this->message) > 50 ? '...' : '');
    }
}
