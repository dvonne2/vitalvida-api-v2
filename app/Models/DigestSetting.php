<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigestSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'enabled',
        'send_time',
        'email',
        'template_sections',
        'custom_filters',
        'timezone',
        'include_charts',
        'include_attachments'
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'send_time' => 'datetime:H:i:s',
        'template_sections' => 'array',
        'custom_filters' => 'array',
        'include_charts' => 'boolean',
        'include_attachments' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Helper methods
    public function getFormattedSendTimeAttribute(): string
    {
        return $this->send_time->format('H:i');
    }

    public function getNextSendTimeAttribute(): string
    {
        $today = now()->setTimeFrom($this->send_time);
        
        if ($today->isPast()) {
            $today->addDay();
        }

        return $today->format('Y-m-d H:i:s');
    }

    public function getSectionsListAttribute(): array
    {
        return $this->template_sections ?? [
            'daily_snapshot',
            'cash_position',
            'orders_summary',
            'refunds_summary',
            'ad_spend_summary',
            'staff_attendance',
            'top_risks'
        ];
    }

    public function getFiltersListAttribute(): array
    {
        return $this->custom_filters ?? [];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function shouldSendNow(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $now = now();
        $sendTime = $now->copy()->setTimeFrom($this->send_time);
        
        return $now->diffInMinutes($sendTime) <= 5;
    }
}
