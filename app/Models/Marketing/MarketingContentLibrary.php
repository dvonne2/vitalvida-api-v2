<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Company;
use App\Models\User;

class MarketingContentLibrary extends Model
{
    use HasUuids;
    
    protected $table = 'marketing_content_library';
    
    protected $fillable = [
        'brand_id', 'content_type', 'title', 'file_url', 
        'variations', 'sensory_tags', 'performance_score', 'usage_count',
        'generation_prompt', 'company_id', 'created_by'
    ];
    
    protected $casts = [
        'variations' => 'array',
        'sensory_tags' => 'array',
        'generation_prompt' => 'array',
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
        return $this->hasMany(MarketingCustomerTouchpoint::class, 'content_id');
    }
    
    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
    
    public function scopeByType($query, $type)
    {
        return $query->where('content_type', $type);
    }
    
    public function scopeAiGenerated($query)
    {
        return $query->where('content_type', 'ai_generated');
    }
    
    public function scopeTopPerforming($query, $limit = 10)
    {
        return $query->orderBy('performance_score', 'desc')->limit($limit);
    }
    
    // Accessors
    public function getFileUrlAttribute($value)
    {
        if ($value && !str_starts_with($value, 'http')) {
            return asset('storage/' . $value);
        }
        return $value;
    }
    
    public function getPerformanceGradeAttribute()
    {
        if ($this->performance_score >= 0.8) return 'A';
        if ($this->performance_score >= 0.6) return 'B';
        if ($this->performance_score >= 0.4) return 'C';
        return 'D';
    }
}
