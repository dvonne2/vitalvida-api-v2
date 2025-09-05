<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Customer;
use App\Models\Company;

class MarketingUnifiedExperience extends Model
{
    use HasFactory;

    protected $table = 'marketing_unified_experience';

    protected $fillable = [
        'customer_id',
        'session_id',
        'context_data',
        'current_intent',
        'current_channel',
        'entry_channel',
        'channel_progression',
        'session_start',
        'session_end',
        'company_id'
    ];

    protected $casts = [
        'context_data' => 'array',
        'current_intent' => 'array',
        'channel_progression' => 'array',
        'session_start' => 'datetime',
        'session_end' => 'datetime',
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

    public function scopeActive($query)
    {
        return $query->whereNull('session_end');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('session_end');
    }

    public function scopeByChannel($query, $channel)
    {
        return $query->where('current_channel', $channel);
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('session_start', '>=', now()->subHours($hours));
    }

    // Helper Methods
    public function getSessionDurationAttribute()
    {
        $end = $this->session_end ?? now();
        return $this->session_start->diffInMinutes($end);
    }

    public function getChannelCountAttribute()
    {
        return count($this->channel_progression ?? []);
    }

    public function getIsMultiChannelAttribute()
    {
        return $this->getChannelCountAttribute() > 1;
    }

    public function getJourneyPathAttribute()
    {
        if (!$this->channel_progression) return '';
        return implode(' → ', $this->channel_progression);
    }

    public function getSessionStatusAttribute()
    {
        return $this->session_end ? 'Completed' : 'Active';
    }

    public function endSession()
    {
        $this->update(['session_end' => now()]);
    }
}
