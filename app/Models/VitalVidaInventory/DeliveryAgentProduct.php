<?php

namespace App\Models\VitalVidaInventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryAgentProduct extends Model
{
    use HasFactory;

    protected $table = 'vitalvida_delivery_agent_products';

    protected $fillable = [
        'delivery_agent_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_value',
        'assigned_date',
        'status'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_value' => 'decimal:2',
        'assigned_date' => 'datetime'
    ];

    // Relationships
    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
