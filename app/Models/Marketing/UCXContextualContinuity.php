<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Customer;
use App\Models\Company;

class UCXContextualContinuity extends Model
{
    use HasFactory;

    protected $table = 'ucx_contextual_continuity';

    protected $fillable = [
        'customer_id',
        'session_id',
        'entry_channel',
        'current_channel',
        'channel_progression',
        'context_data',
        'carried_context',
        'personalization_applied',
        'session_start',
        'last_activity',
        'session_active',
        'company_id'
    ];

    protected $casts = [
        'channel_progression' => 'array',
        'context_data' => 'array',
        'carried_context' => 'array',
        'personalization_applied' => 'array',
        'session_start' => 'datetime',
        'last_activity' => 'datetime',
        'session_active' => 'boolean'
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the number of channels visited in this session
     */
    public function getChannelCountAttribute(): int
    {
        return count(array_unique($this->channel_progression ?? []));
    }

    /**
     * Check if customer switched channels recently
     */
    public function hasRecentChannelSwitch(): bool
    {
        $progression = $this->channel_progression ?? [];
        return count($progression) > 1 && 
               end($progression) !== prev($progression);
    }

    /**
     * Get session duration in minutes
     */
    public function getSessionDurationAttribute(): int
    {
        if (!$this->session_start || !$this->last_activity) {
            return 0;
        }
        
        return $this->session_start->diffInMinutes($this->last_activity);
    }

    /**
     * Scope for active sessions
     */
    public function scopeActive($query)
    {
        return $query->where('session_active', true);
    }

    /**
     * Scope for recent sessions (last 24 hours)
     */
    public function scopeRecent($query)
    {
        return $query->where('last_activity', '>=', now()->subDay());
    }
}
