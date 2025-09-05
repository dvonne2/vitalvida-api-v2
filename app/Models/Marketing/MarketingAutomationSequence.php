<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Company;
use App\Models\User;

class MarketingAutomationSequence extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'name',
        'brand_id',
        'trigger_type',
        'trigger_conditions',
        'steps',
        'target_audience',
        'status',
        'activated_at',
        'company_id',
        'created_by'
    ];

    protected $casts = [
        'trigger_conditions' => 'array',
        'steps' => 'array',
        'target_audience' => 'array',
        'activated_at' => 'datetime'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function brand()
    {
        return $this->belongsTo(MarketingBrand::class, 'brand_id');
    }

    public function executions()
    {
        return $this->hasMany(MarketingAutomationExecution::class, 'sequence_id');
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

    public function scopeByTriggerType($query, $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }
}
