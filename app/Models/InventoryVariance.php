<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryVariance extends Model
{
    use HasFactory;

    protected $fillable = [
        'cycle_count_id',
        'agent_id',
        'product_id',
        'variance_type',
        'variance_quantity',
        'variance_value',
        'root_cause',
        'investigation_status',
        'investigated_by',
        'resolution_action',
        'resolved_by',
        'variance_notes',
        'investigation_metadata',
        'detected_at',
        'investigated_at',
        'resolved_at'
    ];

    protected $casts = [
        'investigation_metadata' => 'array',
        'variance_quantity' => 'decimal:2',
        'variance_value' => 'decimal:2',
        'detected_at' => 'datetime',
        'investigated_at' => 'datetime',
        'resolved_at' => 'datetime'
    ];

    // Relationships
    public function cycleCount()
    {
        return $this->belongsTo(CycleCount::class, 'cycle_count_id');
    }

    public function agent()
    {
        return $this->belongsTo(DeliveryAgent::class, 'agent_id');
    }

    public function product()
    {
        return $this->belongsTo(VitalVidaProduct::class, 'product_id');
    }

    public function investigatedBy()
    {
        return $this->belongsTo(User::class, 'investigated_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Variance type constants
    const TYPE_OVERAGE = 'overage';
    const TYPE_SHORTAGE = 'shortage';
    const TYPE_DAMAGE = 'damage';
    const TYPE_EXPIRY = 'expiry';
    const TYPE_THEFT = 'theft';

    // Investigation status constants
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ESCALATED = 'escalated';
    const STATUS_CLOSED = 'closed';

    // Root cause constants
    const CAUSE_COUNTING_ERROR = 'counting_error';
    const CAUSE_SYSTEM_ERROR = 'system_error';
    const CAUSE_THEFT = 'theft';
    const CAUSE_DAMAGE = 'damage';
    const CAUSE_EXPIRY = 'expiry';
    const CAUSE_TRANSFER_ERROR = 'transfer_error';
    const CAUSE_RECEIVING_ERROR = 'receiving_error';
    const CAUSE_UNKNOWN = 'unknown';

    // Scopes
    public function scopePending($query)
    {
        return $query->where('investigation_status', self::STATUS_PENDING);
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNotIn('investigation_status', [self::STATUS_COMPLETED, self::STATUS_CLOSED]);
    }

    public function scopeSignificant($query, $threshold = 1000)
    {
        return $query->where('variance_value', '>=', $threshold);
    }

    public function scopeByCause($query, $cause)
    {
        return $query->where('root_cause', $cause);
    }

    // Accessors
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => ['class' => 'warning', 'text' => 'Pending'],
            'in_progress' => ['class' => 'info', 'text' => 'In Progress'],
            'completed' => ['class' => 'success', 'text' => 'Completed'],
            'escalated' => ['class' => 'danger', 'text' => 'Escalated'],
            'closed' => ['class' => 'secondary', 'text' => 'Closed']
        ];

        return $badges[$this->investigation_status] ?? ['class' => 'secondary', 'text' => 'Unknown'];
    }

    public function getSeverityLevelAttribute()
    {
        $absValue = abs($this->variance_value);
        
        if ($absValue >= 10000) return 'critical';
        if ($absValue >= 5000) return 'high';
        if ($absValue >= 1000) return 'medium';
        
        return 'low';
    }

    public function getInvestigationAgeAttribute()
    {
        return $this->detected_at ? $this->detected_at->diffInDays(now()) : 0;
    }

    public function getIsOverdueAttribute()
    {
        $maxDays = match($this->severity_level) {
            'critical' => 1,
            'high' => 3,
            'medium' => 7,
            'low' => 14
        };

        return $this->investigation_age > $maxDays && 
               !in_array($this->investigation_status, [self::STATUS_COMPLETED, self::STATUS_CLOSED]);
    }
}
