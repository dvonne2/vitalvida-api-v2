<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'description',
        'category',
        'unit_price',
        'cost_price',
        'available_quantity',
        'minimum_stock_level',
        'maximum_stock_level',
        'status'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'available_quantity' => 'integer',
        'minimum_stock_level' => 'integer',
        'maximum_stock_level' => 'integer',
    ];

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function warehouseStock()
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function binStock()
    {
        return $this->hasMany(BinStock::class);
    }
}
