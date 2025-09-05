<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_agent_id',
        'photo_data',
        'stock_levels',
        'uploaded_at',
        'photo_quality',
        'verified_at'
    ];

    protected $casts = [
        'stock_levels' => 'array',
        'uploaded_at' => 'datetime',
        'verified_at' => 'datetime'
    ];

    /**
     * Get the delivery agent that owns the stock photo
     */
    public function deliveryAgent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class);
    }
}
