<?php

namespace App\Models\VitalVidaInventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    use HasFactory;

    protected $table = 'vitalvida_stock_transfers';

    protected $fillable = [
        'transfer_id',
        'product_id',
        'from_agent_id',
        'to_agent_id',
        'quantity',
        'unit_price',
        'total_value',
        'status',
        'reason',
        'notes',
        'requested_by',
        'approved_by',
        'completed_at',
        'tracking_number'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_value' => 'decimal:2',
        'completed_at' => 'datetime'
    ];

    protected $dates = [
        'completed_at',
        'created_at',
        'updated_at'
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function fromAgent()
    {
        return $this->belongsTo(DeliveryAgent::class, 'from_agent_id');
    }

    public function toAgent()
    {
        return $this->belongsTo(DeliveryAgent::class, 'to_agent_id');
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'Completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeInTransit($query)
    {
        return $query->where('status', 'In Transit');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'Failed');
    }

    // Accessors
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'Completed' => 'green',
            'In Transit' => 'blue',
            'Pending' => 'yellow',
            'Failed' => 'red',
            default => 'gray'
        };
    }

    public function getFormattedDateAttribute()
    {
        return $this->created_at->format('M d, Y');
    }
}
