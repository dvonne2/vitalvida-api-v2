<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Company;
use App\Models\Customer;

class MarketingAutomationExecution extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'sequence_id',
        'customer_id',
        'current_step',
        'status',
        'started_at',
        'completed_at',
        'failed_at',
        'failure_reason',
        'execution_data',
        'company_id'
    ];

    protected $casts = [
        'execution_data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime'
    ];

    public function sequence()
    {
        return $this->belongsTo(MarketingAutomationSequence::class, 'sequence_id');
    }

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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
