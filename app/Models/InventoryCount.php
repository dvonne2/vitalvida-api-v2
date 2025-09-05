<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryCount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'count_number',
        'delivery_agent_id',
        'employee_id',
        'date',
        'status',
        'type',
        'notes',
        'started_at',
        'completed_at',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function items()
    {
        return $this->hasMany(InventoryCountItem::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
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

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    // Business Logic Methods
    public function generateCountNumber(): string
    {
        $lastCount = self::latest('id')->first();
        $nextId = $lastCount ? $lastCount->id + 1 : 1;
        return 'CNT-' . date('Y') . '-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    public function canStart(): bool
    {
        return $this->status === 'pending';
    }

    public function canComplete(): bool
    {
        return $this->status === 'in_progress';
    }

    public function canApprove(): bool
    {
        return $this->status === 'completed';
    }

    public function start(): void
    {
        if (!$this->canStart()) {
            throw new \Exception('Count cannot be started');
        }

        $this->update([
            'status' => 'in_progress',
            'started_at' => now()
        ]);
    }

    public function complete(): void
    {
        if (!$this->canComplete()) {
            throw new \Exception('Count cannot be completed');
        }

        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    public function approve(int $userId): void
    {
        if (!$this->canApprove()) {
            throw new \Exception('Count cannot be approved');
        }

        // Process discrepancies
        $this->processDiscrepancies();

        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now()
        ]);
    }

    public function processDiscrepancies(): void
    {
        $discrepancies = $this->items()->where('variance', '!=', 0)->get();

        foreach ($discrepancies as $item) {
            $adjustment = StockAdjustment::create([
                'item_id' => $item->item_id,
                'delivery_agent_id' => $this->delivery_agent_id,
                'employee_id' => $this->employee_id,
                'adjustment_type' => 'inventory_count',
                'quantity' => $item->variance,
                'reason' => 'Inventory count discrepancy',
                'notes' => "Count: {$item->actual_quantity}, Expected: {$item->expected_quantity}",
                'date' => $this->date,
                'status' => 'approved',
                'approved_by' => $this->approved_by,
                'approved_at' => now()
            ]);

            // Update stock immediately
            if ($item->item) {
                $item->item->updateStockQuantity($item->variance, 'inventory_count');
            }
        }
    }

    public function getTotalItemsAttribute(): int
    {
        return $this->items()->count();
    }

    public function getCountedItemsAttribute(): int
    {
        return $this->items()->whereNotNull('actual_quantity')->count();
    }

    public function getDiscrepancyCountAttribute(): int
    {
        return $this->items()->where('variance', '!=', 0)->count();
    }

    public function getTotalVarianceAttribute(): int
    {
        return $this->items()->sum('variance');
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_items == 0) return 0;
        return round(($this->counted_items / $this->total_items) * 100, 2);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'secondary',
            'in_progress' => 'info',
            'completed' => 'warning',
            'approved' => 'success',
            default => 'secondary'
        };
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($count) {
            if (empty($count->count_number)) {
                $count->count_number = $count->generateCountNumber();
            }
        });
    }
} 