<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentPerformanceMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_agent_id', 'metric_date', 'deliveries_assigned',
        'deliveries_completed', 'deliveries_failed', 'deliveries_returned',
        'success_rate', 'average_delivery_time', 'total_distance_km',
        'average_rating', 'total_earnings', 'commission_earned',
        'bonus_earned', 'first_delivery_time', 'last_delivery_time',
        'active_hours', 'complaints_received', 'strikes_issued'
    ];

    protected $casts = [
        'metric_date' => 'date',
        'success_rate' => 'decimal:2',
        'average_delivery_time' => 'decimal:2',
        'total_distance_km' => 'decimal:2',
        'average_rating' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'commission_earned' => 'decimal:2',
        'bonus_earned' => 'decimal:2',
        'first_delivery_time' => 'datetime:H:i',
        'last_delivery_time' => 'datetime:H:i',
    ];

    // Relationships
    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    // Scopes
    public function scopeForAgent($query, $agentId)
    {
        return $query->where('delivery_agent_id', $agentId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('metric_date', $date);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('metric_date', [$startDate, $endDate]);
    }

    public function scopeTopPerformers($query, $date = null)
    {
        if ($date) {
            $query->where('metric_date', $date);
        }
        return $query->orderBy('success_rate', 'desc')
                    ->orderBy('average_rating', 'desc');
    }
}
