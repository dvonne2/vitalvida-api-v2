<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'type', 'message', 'status', 'sent_at'
    ];

    protected $casts = [
        'sent_at' => 'datetime'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now()
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function markAsReplied(): void
    {
        $this->update(['status' => 'replied']);
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'welcome' => 'Welcome Message',
            'risk_reminder' => 'Risk Reminder',
            'prepayment_request' => 'Prepayment Request',
            'verification' => 'Verification',
            default => 'Unknown'
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'sent' => 'text-green-600',
            'failed' => 'text-red-600',
            'sending' => 'text-yellow-600',
            'replied' => 'text-blue-600',
            default => 'text-gray-600'
        };
    }
}
