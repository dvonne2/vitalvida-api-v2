<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bin extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'zoho_storage_id',
        'zoho_warehouse_id',
        'assigned_to_da',
        'da_phone',
        'location',
        'status',
        'type',
        'max_capacity',
        'metadata',
        'state',
        'zoho_location_id',
        'zoho_zone_id',
        'zoho_bin_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'max_capacity' => 'decimal:2',
    ];

    /**
     * Get the delivery agent assigned to this bin
     */
    public function deliveryAgent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class, 'assigned_to_da', 'da_code');
    }

    /**
     * Get utilization rate if we have stock data
     */
    public function getUtilizationRate(): float
    {
        // This would need to be calculated based on your inventory system
        // For now, returning a placeholder
        return rand(60, 95);
    }

    /**
     * Scope for bins assigned to delivery agents
     */
    public function scopeAssigned($query)
    {
        return $query->whereNotNull('assigned_to_da');
    }

    /**
     * Scope for active bins
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
