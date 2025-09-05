<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VitalVidaSupplierPerformance extends Model
{
    use HasFactory;

    protected $table = 'vitalvida_supplier_performance';

    protected $fillable = [
        'supplier_id',
        'performance_date',
        'delivery_rating',
        'quality_rating',
        'service_rating',
        'orders_completed',
        'orders_delayed',
        'order_value',
        'notes'
    ];

    protected $casts = [
        'performance_date' => 'date',
        'delivery_rating' => 'decimal:2',
        'quality_rating' => 'decimal:2',
        'service_rating' => 'decimal:2',
        'order_value' => 'decimal:2'
    ];

    // Relationships
    public function supplier()
    {
        return $this->belongsTo(VitalVidaSupplier::class, 'supplier_id');
    }

    // Accessors
    public function getOverallRatingAttribute()
    {
        return ($this->delivery_rating + $this->quality_rating + $this->service_rating) / 3;
    }
}
