<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'category_id',
        'supplier_id',
        'sku',
        'description',
        'unit_price',
        'cost_price',
        'selling_price',
        'stock_quantity',
        'reorder_level',
        'max_stock',
        'min_stock',
        'unit_of_measure',
        'brand',
        'model',
        'barcode',
        'is_active',
        'is_tracked',
        'location',
        'shelf_location',
        'expiry_date',
        'manufacturing_date',
        'warranty_period',
        'weight',
        'dimensions',
        'tax_rate',
        'margin_percentage',
        'last_purchase_date',
        'last_sale_date',
        'total_purchased',
        'total_sold',
        'total_returned'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'reorder_level' => 'integer',
        'max_stock' => 'integer',
        'min_stock' => 'integer',
        'is_active' => 'boolean',
        'is_tracked' => 'boolean',
        'expiry_date' => 'date',
        'manufacturing_date' => 'date',
        'weight' => 'decimal:3',
        'tax_rate' => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'last_purchase_date' => 'date',
        'last_sale_date' => 'date',
        'total_purchased' => 'integer',
        'total_sold' => 'integer',
        'total_returned' => 'integer',
        'dimensions' => 'array',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function inventoryHistory()
    {
        return $this->hasMany(InventoryHistory::class);
    }

    public function transferOrderItems()
    {
        return $this->hasMany(TransferOrderItem::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeLowStock($query)
    {
        return $query->where('stock_quantity', '<=', 'reorder_level');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_quantity', 0);
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('expiry_date', '<=', now()->addDays($days));
    }

    // Business Logic Methods
    public function generateItemCode(): string
    {
        $lastItem = self::latest('id')->first();
        $nextId = $lastItem ? $lastItem->id + 1 : 1;
        return 'ITM-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->reorder_level;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock_quantity <= 0;
    }

    public function needsReorder(): bool
    {
        return $this->isLowStock() && $this->is_active;
    }

    public function getReorderQuantityAttribute(): int
    {
        return max(0, $this->max_stock - $this->stock_quantity);
    }

    public function getMarginAttribute(): float
    {
        if ($this->cost_price > 0) {
            return round((($this->selling_price - $this->cost_price) / $this->cost_price) * 100, 2);
        }
        return 0;
    }

    public function getTotalValueAttribute(): float
    {
        return $this->stock_quantity * $this->unit_price;
    }

    public function getTurnoverRateAttribute(): float
    {
        if ($this->average_stock > 0) {
            return $this->total_sold / $this->average_stock;
        }
        return 0;
    }

    public function getAverageStockAttribute(): float
    {
        $history = $this->inventoryHistory()
            ->where('created_at', '>=', now()->subMonths(3))
            ->avg('quantity_after');
        
        return $history ?? $this->stock_quantity;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date && $this->expiry_date->diffInDays(now()) <= $days;
    }

    public function updateStockQuantity(int $change, string $reason = 'manual'): void
    {
        $oldQuantity = $this->stock_quantity;
        $newQuantity = max(0, $oldQuantity + $change);
        
        $this->update(['stock_quantity' => $newQuantity]);
        
        // Log inventory history
        $this->inventoryHistory()->create([
            'quantity_before' => $oldQuantity,
            'quantity_after' => $newQuantity,
            'change_quantity' => $change,
            'reason' => $reason,
            'user_id' => auth()->id()
        ]);
    }

    public function canSell(int $quantity): bool
    {
        return $this->is_active && $this->stock_quantity >= $quantity;
    }

    public function sell(int $quantity): bool
    {
        if (!$this->canSell($quantity)) {
            return false;
        }
        
        $this->updateStockQuantity(-$quantity, 'sale');
        $this->update([
            'total_sold' => $this->total_sold + $quantity,
            'last_sale_date' => now()
        ]);
        
        return true;
    }

    public function purchase(int $quantity, ?float $unitCost = null): void
    {
        $this->updateStockQuantity($quantity, 'purchase');
        
        if ($unitCost) {
            $this->update(['cost_price' => $unitCost]);
        }
        
        $this->update([
            'total_purchased' => $this->total_purchased + $quantity,
            'last_purchase_date' => now()
        ]);
    }

    // Additional Stock Movement Methods
    public function increaseStock(int $quantity, string $reason, int $userId = null): void
    {
        $this->updateStockQuantity($quantity, $reason);
        
        // Log inventory history
        $this->inventoryHistory()->create([
            'quantity_before' => $this->stock_quantity - $quantity,
            'quantity_after' => $this->stock_quantity,
            'change_quantity' => $quantity,
            'reason' => $reason,
            'user_id' => $userId ?? auth()->id(),
            'location' => $this->location
        ]);
    }

    public function decreaseStock(int $quantity, string $reason, int $userId = null): void
    {
        if ($this->stock_quantity < $quantity) {
            throw new \Exception("Insufficient stock. Available: {$this->stock_quantity}, Required: {$quantity}");
        }

        $this->updateStockQuantity(-$quantity, $reason);
        
        // Log inventory history
        $this->inventoryHistory()->create([
            'quantity_before' => $this->stock_quantity + $quantity,
            'quantity_after' => $this->stock_quantity,
            'change_quantity' => -$quantity,
            'reason' => $reason,
            'user_id' => $userId ?? auth()->id(),
            'location' => $this->location
        ]);
    }

    public function transferStock(string $fromLocation, string $toLocation, int $quantity, int $userId = null): void
    {
        // Decrease from source location
        $sourceItem = self::where('id', $this->id)
            ->where('location', $fromLocation)
            ->first();

        if (!$sourceItem || $sourceItem->stock_quantity < $quantity) {
            throw new \Exception("Insufficient stock at source location: {$fromLocation}");
        }

        $sourceItem->decreaseStock($quantity, 'transfer_out', $userId);

        // Increase at destination location
        $destItem = self::where('id', $this->id)
            ->where('location', $toLocation)
            ->first();

        if ($destItem) {
            $destItem->increaseStock($quantity, 'transfer_in', $userId);
        } else {
            // Create new item at destination if it doesn't exist
            $newItem = $this->replicate();
            $newItem->location = $toLocation;
            $newItem->stock_quantity = $quantity;
            $newItem->save();

            $newItem->inventoryHistory()->create([
                'quantity_before' => 0,
                'quantity_after' => $quantity,
                'change_quantity' => $quantity,
                'reason' => 'transfer_in',
                'user_id' => $userId ?? auth()->id(),
                'location' => $toLocation
            ]);
        }
    }

    public function getCurrentStock(string $location = null): int
    {
        $query = self::where('id', $this->id);
        
        if ($location) {
            $query->where('location', $location);
        }

        return $query->sum('stock_quantity');
    }

    public function getStockByAgent(): array
    {
        return $this->inventoryHistory()
            ->with('deliveryAgent')
            ->selectRaw('delivery_agent_id, SUM(change_quantity) as total_change')
            ->groupBy('delivery_agent_id')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->delivery_agent_id => $item->total_change];
            })
            ->toArray();
    }

    public function getLowStockItems(int $threshold = 10): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('stock_quantity', '<=', $threshold)
            ->where('is_active', true)
            ->with(['category', 'supplier'])
            ->get();
    }

    public function getStockMovementHistory(int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        return $this->inventoryHistory()
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->with(['user', 'deliveryAgent'])
            ->get();
    }

    public function getStockValue(): float
    {
        return $this->stock_quantity * $this->unit_price;
    }

    public function getTurnoverRate(int $days = 30): float
    {
        $sales = $this->inventoryHistory()
            ->where('reason', 'sale')
            ->where('created_at', '>=', now()->subDays($days))
            ->sum('change_quantity');

        $averageStock = $this->getAverageStockAttribute();

        return $averageStock > 0 ? abs($sales) / $averageStock : 0;
    }

    public function getDaysOfInventory(): int
    {
        $turnoverRate = $this->getTurnoverRate();
        return $turnoverRate > 0 ? round(365 / $turnoverRate) : 0;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function getExpiryStatus(): string
    {
        if (!$this->expiry_date) return 'no_expiry';
        
        $daysUntilExpiry = $this->expiry_date->diffInDays(now(), false);
        
        if ($daysUntilExpiry < 0) return 'expired';
        if ($daysUntilExpiry <= 7) return 'expiring_soon';
        if ($daysUntilExpiry <= 30) return 'expiring_month';
        return 'good';
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (empty($item->code)) {
                $item->code = $item->generateItemCode();
            }
        });
    }
} 