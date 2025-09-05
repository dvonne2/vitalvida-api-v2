<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'movement_id', 'order_number', 'item_id', 'item_name', 'item_sku',
        'bin_id', 'bin_name', 'warehouse_id', 'zone', 'aisle', 'rack', 'shelf',
        'movement_type', 'quantity_before', 'quantity_changed', 'quantity_after',
        'source_type', 'source_reference', 'source_details',
        'user_id', 'performed_by', 'ip_address', 'user_agent',
        'zoho_transaction_id', 'zoho_response', 'synced_to_zoho', 'synced_at',
        'status', 'notes', 'reason', 'movement_at'
    ];

    protected $casts = [
        'source_details' => 'array',
        'zoho_response' => 'array',
        'synced_to_zoho' => 'boolean',
        'synced_at' => 'datetime',
        'movement_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_number', 'order_number');
    }

    public function binLocation(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_id', 'bin_id');
    }

    // Scopes for filtering
    public function scopeInbound($query)
    {
        return $query->where('movement_type', 'inbound');
    }

    public function scopeOutbound($query)
    {
        return $query->where('movement_type', 'outbound');
    }

    public function scopeBySource($query, $sourceType)
    {
        return $query->where('source_type', $sourceType);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('movement_at', [$startDate, $endDate]);
    }
}
