<?php

namespace App\Models\VitalVidaInventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vitalvida_products';

    protected $fillable = [
        'name',
        'code',
        'category',
        'description',
        'unit_price',
        'cost_price',
        'stock_level',
        'min_stock',
        'max_stock',
        'supplier_id',
        'expiry_date',
        'batch_number',
        'barcode',
        'status',
        'location',
        'weight',
        'dimensions',
        'image_url',
        'is_active'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock_level' => 'integer',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
        'dimensions' => 'json'
    ];

    protected $dates = [
        'expiry_date',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    // Relationships
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function deliveryAgentProducts()
    {
        return $this->hasMany(DeliveryAgentProduct::class);
    }

    public function stockTransfers()
    {
        return $this->hasMany(StockTransfer::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('stock_level <= min_stock');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_level', 0);
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('expiry_date', '<=', now()->addDays($days))
                    ->where('expiry_date', '>', now());
    }

    // Accessors
    public function getStockStatusAttribute()
    {
        if ($this->stock_level == 0) {
            return 'Out of Stock';
        } elseif ($this->stock_level <= $this->min_stock) {
            return 'Low Stock';
        }
        return 'In Stock';
    }

    public function getTotalValueAttribute()
    {
        return $this->stock_level * $this->unit_price;
    }

    public function getIsExpiringAttribute()
    {
        if (!$this->expiry_date) return false;
        return $this->expiry_date <= now()->addDays(30);
    }
}
