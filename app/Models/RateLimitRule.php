<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateLimitRule extends Model
{
    protected $fillable = [
        'user_id',
        'service',
        'client_type',
        'max_requests',
        'window_seconds',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeService($query, string $service)
    {
        return $query->where('service', $service);
    }

    public function scopeClientType($query, string $clientType)
    {
        return $query->where('client_type', $clientType);
    }
} 