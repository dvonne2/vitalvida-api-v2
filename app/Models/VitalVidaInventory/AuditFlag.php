<?php

namespace App\Models\VitalVidaInventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditFlag extends Model
{
    use HasFactory;

    protected $table = 'vitalvida_audit_flags';

    protected $fillable = [
        'delivery_agent_id',
        'product_id',
        'flag_type',
        'priority',
        'issue_description',
        'expected_quantity',
        'reported_quantity',
        'actual_quantity',
        'discrepancy_amount',
        'status',
        'flagged_by',
        'resolved_by',
        'resolution_notes',
        'resolved_at'
    ];

    protected $casts = [
        'expected_quantity' => 'integer',
        'reported_quantity' => 'integer',
        'actual_quantity' => 'integer',
        'discrepancy_amount' => 'decimal:2',
        'resolved_at' => 'datetime'
    ];

    // Relationships
    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Scopes
    public function scopeCritical($query)
    {
        return $query->where('priority', 'CRITICAL');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('resolved_at');
    }

    // Accessors
    public function getPriorityColorAttribute()
    {
        return match($this->priority) {
            'CRITICAL' => 'red',
            'HIGH' => 'orange',
            'MEDIUM' => 'yellow',
            'LOW' => 'blue',
            default => 'gray'
        };
    }
}
