<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryAudit extends Model
{
    protected $fillable = [
        'order_number', 'item_id', 'bin_id', 'quantity_deducted', 
        'reason', 'user_id', 'zoho_adjustment_id', 'zoho_response',
        'deducted_at', 'ip_address', 'user_agent'
    ];

    protected $casts = [
        'zoho_response' => 'array',
        'deducted_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_number', 'order_number');
    }
}
