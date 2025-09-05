<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'name',
        'client_type',
        'platform',
        'device_id',
        'app_version',
        'permissions',
        'is_active',
        'expires_at',
        'last_used_at'
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('expires_at', '>', now());
    }

    public function scopeMobile($query)
    {
        return $query->where('client_type', 'mobile');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }
} 