<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SentMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipient', 'message', 'type', 'template_used',
        'delivery_status', 'sent_at', 'delivered_at', 'response_received'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'response_received' => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(AlertTemplate::class, 'template_used');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('delivery_status', $status);
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereDate('sent_at', $date);
    }

    public function getFormattedSentAtAttribute()
    {
        return $this->sent_at?->format('M d, Y g:i A');
    }

    public function getFormattedDeliveredAtAttribute()
    {
        return $this->delivered_at?->format('M d, Y g:i A');
    }

    public function getStatusColorAttribute()
    {
        return match($this->delivery_status) {
            'delivered' => 'green',
            'pending' => 'yellow',
            'failed' => 'red',
            default => 'gray'
        };
    }
}
