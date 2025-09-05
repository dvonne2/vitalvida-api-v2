<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    protected $fillable = [
        'zoho_item_id',
        'item_name',
        'sku',
        'action',
        'quantity',
        'quantity_before',
        'quantity_after',
        'bin_location',
        'user_id',
        'purchase_order_id',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'quantity' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
