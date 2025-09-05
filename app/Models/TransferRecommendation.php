<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferRecommendation extends Model
{
    protected $table = 'transfer_recommendations';
    
    protected $fillable = [
        'from_da_id', 'to_da_id', 'recommended_quantity',
        'priority', 'potential_savings', 'reasoning',
        'logistics_data', 'status', 'recommended_at', 'approved_at'
    ];

    protected $casts = [
        'logistics_data' => 'array',
        'potential_savings' => 'decimal:2',
        'recommended_at' => 'datetime',
        'approved_at' => 'datetime'
    ];

    public function fromDA()
    {
        return $this->belongsTo(DeliveryAgent::class, 'from_da_id');
    }

    public function toDA()
    {
        return $this->belongsTo(DeliveryAgent::class, 'to_da_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeHighValue($query)
    {
        return $query->where('potential_savings', '>=', 5000);
    }

    public function approve()
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now()
        ]);
    }

    public function getEfficiencyScoreAttribute()
    {
        $logistics = $this->logistics_data;
        if (!$logistics) return 0;

        $distance = $logistics['distance_km'] ?? 0;
        $cost = $logistics['transport_cost'] ?? 0;
        $quantity = $this->recommended_quantity;

        if ($distance == 0 || $quantity == 0) return 0;

        // Efficiency = (Savings - Cost) / Distance
        return ($this->potential_savings - $cost) / $distance;
    }
} 