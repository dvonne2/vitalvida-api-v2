<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncConflict extends Model
{
    protected $fillable = [
        'device_id',
        'entity_type',
        'entity_id',
        'client_data',
        'server_data',
        'conflict_type',
        'status',
        'resolution',
        'resolved_at',
        'detected_at'
    ];

    protected $casts = [
        'client_data' => 'array',
        'server_data' => 'array',
        'resolved_at' => 'datetime',
        'detected_at' => 'datetime'
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }
} 