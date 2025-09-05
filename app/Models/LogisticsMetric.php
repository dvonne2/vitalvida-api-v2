<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsMetric extends Model
{
    protected $fillable = [
        'employee_id',
        'month',
        'deliveries_completed',
        'deliveries_on_time',
        'delivery_efficiency',
        'cost_savings',
        'quality_score',
        'customer_satisfaction',
        'error_rate',
        'fuel_efficiency'
    ];

    protected $casts = [
        'month' => 'date',
        'delivery_efficiency' => 'decimal:2',
        'cost_savings' => 'decimal:2',
        'quality_score' => 'decimal:2',
        'customer_satisfaction' => 'decimal:2',
        'error_rate' => 'decimal:2',
        'fuel_efficiency' => 'decimal:2'
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
} 