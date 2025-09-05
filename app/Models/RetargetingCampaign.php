<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetargetingCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'platform',
        'campaign_type',
        'status',
        'message_content',
        'target_audience',
        'scheduled_at',
        'sent_at',
        'response_received',
        'conversion_achieved',
        'cost',
        'revenue_generated',
        'performance_metrics'
    ];

    protected $casts = [
        'message_content' => 'array',
        'target_audience' => 'array',
        'performance_metrics' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'response_received' => 'boolean',
        'conversion_achieved' => 'boolean',
        'cost' => 'decimal:2',
        'revenue_generated' => 'decimal:2'
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getROI(): float
    {
        if ($this->cost <= 0) return 0;
        return (($this->revenue_generated - $this->cost) / $this->cost) * 100;
    }

    public function isSuccessful(): bool
    {
        return $this->conversion_achieved || $this->getROI() > 100;
    }

    public function getPlatformIcon(): string
    {
        return match($this->platform) {
            'meta' => '📘',
            'tiktok' => '🎵',
            'google' => '🔍',
            'youtube' => '📺',
            'whatsapp' => '💬',
            'sms' => '📱',
            'email' => '📧',
            default => '🌐'
        };
    }

    public function getStatusBadge(): string
    {
        return match($this->status) {
            'scheduled' => 'bg-yellow-100 text-yellow-800',
            'sent' => 'bg-blue-100 text-blue-800',
            'delivered' => 'bg-green-100 text-green-800',
            'failed' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    // Scopes
    public function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('conversion_achieved', true);
    }

    public function scopeHighROI($query)
    {
        return $query->whereRaw('(revenue_generated - cost) / cost * 100 > 200');
    }

    public function scopeByCampaignType($query, $type)
    {
        return $query->where('campaign_type', $type);
    }
} 