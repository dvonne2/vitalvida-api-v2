<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlertTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'type', 'sms_template', 'whatsapp_template',
        'recipients', 'priority', 'auto_escalation_rules'
    ];

    protected $casts = [
        'recipients' => 'array',
        'auto_escalation_rules' => 'array',
    ];

    public function sentMessages()
    {
        return $this->hasMany(SentMessage::class);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function getFormattedRecipientsAttribute()
    {
        return implode(', ', $this->recipients ?? []);
    }

    public function getEscalationRulesAttribute()
    {
        return $this->auto_escalation_rules ?? [];
    }
}
