<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CycleCount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'count_id',
        'agent_id',
        'product_id',
        'abc_classification',
        'scheduled_date',
        'count_status',
        'system_quantity',
        'counted_quantity',
        'variance_quantity',
        'variance_percentage',
        'count_method',
        'counted_by',
        'verified_by',
        'count_notes',
        'count_metadata',
        'started_at',
        'completed_at',
        'verified_at'
    ];

    protected $casts = [
        'count_metadata' => 'array',
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'variance_quantity' => 'decimal:2',
        'variance_percentage' => 'decimal:2'
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

    public function countedBy()
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function variances()
    {
        return $this->hasMany(InventoryVariance::class, 'cycle_count_id');
    }

    // Status constants
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_VERIFIED = 'verified';
    const STATUS_DISPUTED = 'disputed';
    const STATUS_CANCELLED = 'cancelled';

    // ABC Classification constants
    const ABC_A = 'A'; // High value, weekly counts
    const ABC_B = 'B'; // Medium value, bi-weekly counts
    const ABC_C = 'C'; // Low value, monthly counts

    // Count method constants
    const METHOD_PHYSICAL = 'physical';
    const METHOD_BARCODE = 'barcode';
    const METHOD_RFID = 'rfid';
    const METHOD_BLIND = 'blind';

    // Scopes
    public function scopeScheduled($query)
    {
        return $query->where('count_status', self::STATUS_SCHEDULED);
    }

    public function scopeOverdue($query)
    {
        return $query->where('count_status', self::STATUS_SCHEDULED)
                    ->where('scheduled_date', '<', now());
    }

    public function scopeWithVariances($query)
    {
        return $query->where('variance_quantity', '!=', 0);
    }

    public function scopeByClassification($query, $classification)
    {
        return $query->where('abc_classification', $classification);
    }

    public function scopeAccurate($query, $tolerance = 0.01)
    {
        return $query->where('variance_percentage', '<=', $tolerance);
    }

    // Accessors
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'scheduled' => ['class' => 'info', 'text' => 'Scheduled'],
            'in_progress' => ['class' => 'warning', 'text' => 'In Progress'],
            'completed' => ['class' => 'primary', 'text' => 'Completed'],
            'verified' => ['class' => 'success', 'text' => 'Verified'],
            'disputed' => ['class' => 'danger', 'text' => 'Disputed'],
            'cancelled' => ['class' => 'secondary', 'text' => 'Cancelled']
        ];

        return $badges[$this->count_status] ?? ['class' => 'secondary', 'text' => 'Unknown'];
    }

    public function getVarianceTypeAttribute()
    {
        if ($this->variance_quantity == 0) {
            return 'accurate';
        }
        
        return $this->variance_quantity > 0 ? 'overage' : 'shortage';
    }

    public function getVarianceSeverityAttribute()
    {
        $absPercentage = abs($this->variance_percentage);
        
        if ($absPercentage <= 1) return 'low';
        if ($absPercentage <= 5) return 'medium';
        if ($absPercentage <= 10) return 'high';
        
        return 'critical';
    }

    public function getIsOverdueAttribute()
    {
        return $this->count_status === self::STATUS_SCHEDULED && 
               $this->scheduled_date && 
               $this->scheduled_date->isPast();
    }

    public function getCountDurationAttribute()
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInMinutes($this->completed_at);
        }
        
        return null;
    }

    public function getAccuracyRatingAttribute()
    {
        $absPercentage = abs($this->variance_percentage);
        
        if ($absPercentage <= 0.5) return 'excellent';
        if ($absPercentage <= 1) return 'good';
        if ($absPercentage <= 3) return 'fair';
        if ($absPercentage <= 5) return 'poor';
        
        return 'unacceptable';
    }
}
