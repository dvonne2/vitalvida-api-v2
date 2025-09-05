<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustodyTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transfer_id',
        'from_agent_id',
        'to_agent_id',
        'product_id',
        'quantity',
        'seal_id',
        'custody_status',
        'transfer_type',
        'initiated_by',
        'approved_by',
        'received_by',
        'transfer_notes',
        'custody_metadata',
        'initiated_at',
        'approved_at',
        'in_transit_at',
        'received_at',
        'completed_at'
    ];

    protected $casts = [
        'custody_metadata' => 'array',
        'initiated_at' => 'datetime',
        'approved_at' => 'datetime',
        'in_transit_at' => 'datetime',
        'received_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    // Relationships
    public function fromAgent()
    {
        return $this->belongsTo(DeliveryAgent::class, 'from_agent_id');
    }

    public function toAgent()
    {
        return $this->belongsTo(DeliveryAgent::class, 'to_agent_id');
    }

    public function product()
    {
        return $this->belongsTo(VitalVidaProduct::class, 'product_id');
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function sealLogs()
    {
        return $this->hasMany(SealLog::class, 'custody_transfer_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('custody_status', 'pending');
    }

    public function scopeInTransit($query)
    {
        return $query->where('custody_status', 'in_transit');
    }

    public function scopeCompleted($query)
    {
        return $query->where('custody_status', 'completed');
    }

    public function scopeViolated($query)
    {
        return $query->where('custody_status', 'violated');
    }

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_RECEIVED = 'received';
    const STATUS_COMPLETED = 'completed';
    const STATUS_VIOLATED = 'violated';
    const STATUS_CANCELLED = 'cancelled';

    // Transfer types
    const TYPE_AGENT_TO_AGENT = 'agent_to_agent';
    const TYPE_WAREHOUSE_TO_AGENT = 'warehouse_to_agent';
    const TYPE_AGENT_TO_WAREHOUSE = 'agent_to_warehouse';
    const TYPE_ZONE_TRANSFER = 'zone_transfer';

    // Accessors
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => ['class' => 'warning', 'text' => 'Pending'],
            'approved' => ['class' => 'info', 'text' => 'Approved'],
            'in_transit' => ['class' => 'primary', 'text' => 'In Transit'],
            'received' => ['class' => 'success', 'text' => 'Received'],
            'completed' => ['class' => 'success', 'text' => 'Completed'],
            'violated' => ['class' => 'danger', 'text' => 'Violated'],
            'cancelled' => ['class' => 'secondary', 'text' => 'Cancelled']
        ];

        return $badges[$this->custody_status] ?? ['class' => 'secondary', 'text' => 'Unknown'];
    }

    public function getDurationInTransitAttribute()
    {
        if ($this->in_transit_at && $this->received_at) {
            return $this->in_transit_at->diffInHours($this->received_at);
        }
        
        if ($this->in_transit_at && !$this->received_at) {
            return $this->in_transit_at->diffInHours(now());
        }

        return 0;
    }

    public function getIsSealIntactAttribute()
    {
        $latestSealLog = $this->sealLogs()->latest()->first();
        return $latestSealLog ? $latestSealLog->seal_status === 'intact' : false;
    }
}
