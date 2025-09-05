<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transfer_number',
        'from_location',
        'to_location',
        'delivery_agent_id',
        'status',
        'transfer_date',
        'expected_date',
        'actual_date',
        'total_items',
        'total_value',
        'notes',
        'approved_by',
        'approved_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason'
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'expected_date' => 'date',
        'actual_date' => 'date',
        'total_items' => 'integer',
        'total_value' => 'decimal:2',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function items()
    {
        return $this->hasMany(TransferOrderItem::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByLocation($query, $location)
    {
        return $query->where('from_location', $location)->orWhere('to_location', $location);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    // Business Logic Methods
    public function generateTransferNumber(): string
    {
        $lastTransfer = self::latest('id')->first();
        $nextId = $lastTransfer ? $lastTransfer->id + 1 : 1;
        return 'TRF-' . date('Y') . '-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    public function calculateTotals(): void
    {
        $items = $this->items;
        $totalItems = $items->sum('quantity');
        $totalValue = $items->sum(\DB::raw('quantity * unit_cost'));

        $this->update([
            'total_items' => $totalItems,
            'total_value' => $totalValue
        ]);
    }

    public function canApprove(): bool
    {
        return $this->status === 'pending';
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['pending', 'approved']);
    }

    public function canComplete(): bool
    {
        return $this->status === 'approved';
    }

    public function approve(int $userId): void
    {
        if (!$this->canApprove()) {
            throw new \Exception('Transfer order cannot be approved');
        }

        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now()
        ]);
    }

    public function cancel(int $userId, string $reason): void
    {
        if (!$this->canCancel()) {
            throw new \Exception('Transfer order cannot be cancelled');
        }

        $this->update([
            'status' => 'cancelled',
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason
        ]);
    }

    public function complete(): void
    {
        if (!$this->canComplete()) {
            throw new \Exception('Transfer order cannot be completed');
        }

        // Process inventory movements
        foreach ($this->items as $item) {
            // Reduce from source location
            $sourceItem = Item::where('id', $item->item_id)
                ->where('location', $this->from_location)
                ->first();

            if ($sourceItem) {
                $sourceItem->updateStockQuantity(-$item->quantity, 'transfer_out');
            }

            // Add to destination location
            $destItem = Item::where('id', $item->item_id)
                ->where('location', $this->to_location)
                ->first();

            if ($destItem) {
                $destItem->updateStockQuantity($item->quantity, 'transfer_in');
            }
        }

        $this->update([
            'status' => 'completed',
            'actual_date' => now()
        ]);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'approved' => 'info',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'secondary'
        };
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transferOrder) {
            if (empty($transferOrder->transfer_number)) {
                $transferOrder->transfer_number = $transferOrder->generateTransferNumber();
            }
        });
    }
} 