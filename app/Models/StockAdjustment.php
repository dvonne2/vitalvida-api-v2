<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_id',
        'delivery_agent_id',
        'employee_id',
        'adjustment_type',
        'quantity',
        'reason',
        'notes',
        'date',
        'reference_number',
        'approved_by',
        'approved_at',
        'status'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'date' => 'date',
        'approved_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('adjustment_type', $type);
    }

    public function scopeByAgent($query, $agentId)
    {
        return $query->where('delivery_agent_id', $agentId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    // Business Logic Methods
    public function generateReferenceNumber(): string
    {
        $lastAdjustment = self::latest('id')->first();
        $nextId = $lastAdjustment ? $lastAdjustment->id + 1 : 1;
        return 'ADJ-' . date('Y') . '-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    public function isIncrease(): bool
    {
        return $this->quantity > 0;
    }

    public function isDecrease(): bool
    {
        return $this->quantity < 0;
    }

    public function getAbsoluteQuantityAttribute(): int
    {
        return abs($this->quantity);
    }

    public function canApprove(): bool
    {
        return $this->status === 'pending';
    }

    public function approve(int $userId): void
    {
        if (!$this->canApprove()) {
            throw new \Exception('Adjustment cannot be approved');
        }

        // Update stock
        if ($this->item) {
            $this->item->updateStockQuantity($this->quantity, $this->adjustment_type);
        }

        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now()
        ]);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            default => 'secondary'
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->adjustment_type) {
            'damage' => 'Damage',
            'loss' => 'Loss',
            'found' => 'Found',
            'theft' => 'Theft',
            'expiry' => 'Expiry',
            'quality_control' => 'Quality Control',
            'system_adjustment' => 'System Adjustment',
            default => ucfirst($this->adjustment_type)
        };
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($adjustment) {
            if (empty($adjustment->reference_number)) {
                $adjustment->reference_number = $adjustment->generateReferenceNumber();
            }
        });
    }
} 