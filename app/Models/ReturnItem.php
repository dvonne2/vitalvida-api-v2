<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'return_id',
        'agent_id',
        'product_id',
        'quantity',
        'return_reason',
        'return_type',
        'condition_on_return',
        'return_status',
        'initiated_by',
        'processed_by',
        'approved_by',
        'quarantine_location',
        'disposition_decision',
        'disposition_value',
        'return_metadata',
        'initiated_at',
        'quarantined_at',
        'inspected_at',
        'processed_at',
        'completed_at'
    ];

    protected $casts = [
        'return_metadata' => 'array',
        'disposition_value' => 'decimal:2',
        'initiated_at' => 'datetime',
        'quarantined_at' => 'datetime',
        'inspected_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    // Relationships
    public function agent()
    {
        return $this->belongsTo(DeliveryAgent::class, 'agent_id');
    }

    public function product()
    {
        return $this->belongsTo(VitalVidaProduct::class, 'product_id');
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function damageAssessments()
    {
        return $this->hasMany(DamageAssessment::class, 'return_item_id');
    }

    // Return reason constants
    const REASON_DAMAGED = 'damaged';
    const REASON_EXPIRED = 'expired';
    const REASON_DEFECTIVE = 'defective';
    const REASON_OVERSTOCKED = 'overstocked';
    const REASON_CUSTOMER_RETURN = 'customer_return';
    const REASON_RECALL = 'recall';
    const REASON_WRONG_PRODUCT = 'wrong_product';

    // Return type constants
    const TYPE_VOLUNTARY = 'voluntary';
    const TYPE_MANDATORY = 'mandatory';
    const TYPE_EMERGENCY = 'emergency';
    const TYPE_ROUTINE = 'routine';

    // Condition constants
    const CONDITION_GOOD = 'good';
    const CONDITION_DAMAGED = 'damaged';
    const CONDITION_EXPIRED = 'expired';
    const CONDITION_CONTAMINATED = 'contaminated';
    const CONDITION_UNKNOWN = 'unknown';

    // Status constants
    const STATUS_INITIATED = 'initiated';
    const STATUS_QUARANTINED = 'quarantined';
    const STATUS_INSPECTING = 'inspecting';
    const STATUS_PENDING_DISPOSITION = 'pending_disposition';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Disposition constants
    const DISPOSITION_RETURN_TO_STOCK = 'return_to_stock';
    const DISPOSITION_DESTROY = 'destroy';
    const DISPOSITION_DONATE = 'donate';
    const DISPOSITION_RETURN_TO_SUPPLIER = 'return_to_supplier';
    const DISPOSITION_REPAIR = 'repair';
    const DISPOSITION_SELL_AS_DAMAGED = 'sell_as_damaged';

    // Scopes
    public function scopePendingInspection($query)
    {
        return $query->where('return_status', self::STATUS_QUARANTINED);
    }

    public function scopePendingDisposition($query)
    {
        return $query->where('return_status', self::STATUS_PENDING_DISPOSITION);
    }

    public function scopeOverdue($query)
    {
        return $query->where('return_status', '!=', self::STATUS_COMPLETED)
                    ->where('initiated_at', '<', now()->subDays(3));
    }

    public function scopeByReason($query, $reason)
    {
        return $query->where('return_reason', $reason);
    }

    public function scopeHighValue($query, $threshold = 10000)
    {
        return $query->whereHas('product', function($q) use ($threshold) {
            $q->where('unit_price', '>=', $threshold);
        });
    }

    // Accessors
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'initiated' => ['class' => 'info', 'text' => 'Initiated'],
            'quarantined' => ['class' => 'warning', 'text' => 'Quarantined'],
            'inspecting' => ['class' => 'primary', 'text' => 'Inspecting'],
            'pending_disposition' => ['class' => 'warning', 'text' => 'Pending Disposition'],
            'approved' => ['class' => 'success', 'text' => 'Approved'],
            'rejected' => ['class' => 'danger', 'text' => 'Rejected'],
            'completed' => ['class' => 'success', 'text' => 'Completed'],
            'cancelled' => ['class' => 'secondary', 'text' => 'Cancelled']
        ];

        return $badges[$this->return_status] ?? ['class' => 'secondary', 'text' => 'Unknown'];
    }

    public function getProcessingTimeAttribute()
    {
        if ($this->initiated_at && $this->completed_at) {
            return $this->initiated_at->diffInHours($this->completed_at);
        }
        
        if ($this->initiated_at && !$this->completed_at) {
            return $this->initiated_at->diffInHours(now());
        }

        return 0;
    }

    public function getIsOverdueAttribute()
    {
        $maxHours = match($this->return_type) {
            self::TYPE_EMERGENCY => 4,
            self::TYPE_MANDATORY => 24,
            self::TYPE_VOLUNTARY => 72,
            self::TYPE_ROUTINE => 168,
            default => 72
        };

        return $this->return_status !== self::STATUS_COMPLETED && 
               $this->processing_time > $maxHours;
    }

    public function getEstimatedValueAttribute()
    {
        return $this->quantity * ($this->product->unit_price ?? 0);
    }

    public function getRiskLevelAttribute()
    {
        if (in_array($this->return_reason, [self::REASON_EXPIRED, self::REASON_CONTAMINATED])) {
            return 'high';
        }
        
        if ($this->estimated_value >= 50000) {
            return 'high';
        }
        
        if ($this->estimated_value >= 10000) {
            return 'medium';
        }
        
        return 'low';
    }
}
