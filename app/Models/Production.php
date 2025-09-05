<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Production extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'production_number',
        'date',
        'status',
        'notes',
        'started_at',
        'completed_at',
        'approved_by',
        'approved_at',
        'total_cost',
        'total_produced'
    ];

    protected $casts = [
        'date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'total_cost' => 'decimal:2',
        'total_produced' => 'integer',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function items()
    {
        return $this->hasMany(ProductionItem::class);
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
    public function generateProductionNumber(): string
    {
        $lastProduction = self::latest('id')->first();
        $nextId = $lastProduction ? $lastProduction->id + 1 : 1;
        return 'PRD-' . date('Y') . '-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
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
            throw new \Exception('Production cannot be started');
        }

        // Consume raw materials
        $this->consumeRawMaterials();

        $this->update([
            'status' => 'in_progress',
            'started_at' => now()
        ]);
    }

    public function complete(): void
    {
        if (!$this->canComplete()) {
            throw new \Exception('Production cannot be completed');
        }

        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    public function approve(int $userId): void
    {
        if (!$this->canApprove()) {
            throw new \Exception('Production cannot be approved');
        }

        // Add finished goods to inventory
        $this->addFinishedGoods();

        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now()
        ]);
    }

    public function consumeRawMaterials(): void
    {
        $rawItems = $this->items()->whereNotNull('raw_item_id')->get();

        foreach ($rawItems as $item) {
            $rawItem = Item::find($item->raw_item_id);
            if ($rawItem && $rawItem->stock_quantity >= $item->quantity_used) {
                $rawItem->updateStockQuantity(-$item->quantity_used, 'production_consumption');
            } else {
                throw new \Exception("Insufficient stock for raw material: {$rawItem->name}");
            }
        }
    }

    public function addFinishedGoods(): void
    {
        $finishedItems = $this->items()->whereNotNull('finished_item_id')->get();

        foreach ($finishedItems as $item) {
            $finishedItem = Item::find($item->finished_item_id);
            if ($finishedItem) {
                $finishedItem->updateStockQuantity($item->quantity_produced, 'production_completion');
            }
        }
    }

    public function calculateTotalCost(): float
    {
        return $this->items()->sum(\DB::raw('quantity_used * unit_cost'));
    }

    public function calculateTotalProduced(): int
    {
        return $this->items()->whereNotNull('finished_item_id')->sum('quantity_produced');
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

        static::creating(function ($production) {
            if (empty($production->production_number)) {
                $production->production_number = $production->generateProductionNumber();
            }
        });

        static::saved(function ($production) {
            if ($production->status === 'completed') {
                $production->update([
                    'total_cost' => $production->calculateTotalCost(),
                    'total_produced' => $production->calculateTotalProduced()
                ]);
            }
        });
    }
} 