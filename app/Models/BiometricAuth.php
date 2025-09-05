<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricAuth extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'biometric_type',
        'public_key',
        'is_active',
        'registered_at',
        'last_used_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'registered_at' => 'datetime',
        'last_used_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }
} 