<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequest extends Model
{
    protected $fillable = [
        'service',
        'method',
        'path',
        'user_id',
        'client_type',
        'ip_address',
        'user_agent',
        'status',
        'response_time',
        'error_message',
        'request_id',
        'timestamp'
    ];

    protected $casts = [
        'response_time' => 'decimal:4',
        'timestamp' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeError($query)
    {
        return $query->where('status', 'error');
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