<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncJob extends Model
{
    protected $fillable = [
        'device_id',
        'entity_type',
        'entity_id',
        'action',
        'status',
        'error_message',
        'processed_at',
        'retry_count'
    ];

    protected $casts = [
        'processed_at' => 'datetime'
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }
} 