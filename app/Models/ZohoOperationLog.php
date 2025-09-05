<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZohoOperationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'operation_type', 'operation_id', 'zoho_endpoint', 'http_method',
        'request_payload', 'response_data', 'response_status_code', 'status',
        'error_message', 'retry_count', 'max_retries', 'next_retry_at',
        'completed_at', 'metadata'
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_data' => 'array',
        'metadata' => 'array',
        'next_retry_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeReadyForRetry($query)
    {
        return $query->where('status', 'retrying')
                    ->where('next_retry_at', '<=', now());
    }

    public function canRetry()
    {
        return $this->retry_count < $this->max_retries;
    }

    public function markAsSuccess($responseData = null)
    {
        $this->update([
            'status' => 'success',
            'response_data' => $responseData,
            'completed_at' => now()
        ]);
    }

    public function markAsFailed($errorMessage, $responseData = null)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'response_data' => $responseData,
            'completed_at' => now()
        ]);
    }

    public function scheduleRetry($delayMinutes = null)
    {
        if (!$this->canRetry()) {
            $this->markAsFailed('Maximum retries exceeded');
            return false;
        }

        $delay = $delayMinutes ?? (2 ** $this->retry_count);
        
        $this->update([
            'status' => 'retrying',
            'retry_count' => $this->retry_count + 1,
            'next_retry_at' => now()->addMinutes($delay)
        ]);

        return true;
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'pending' => '🟡 Pending',
            'success' => '✅ Success',
            'failed' => '❌ Failed',
            'retrying' => '🔄 Retrying',
            default => '❓ Unknown'
        };
    }
}
