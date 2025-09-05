<?php

namespace App\Models\VitalVidaInventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryAgent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vitalvida_delivery_agents';

    protected $fillable = [
        'agent_id',
        'name',
        'phone',
        'email',
        'location',
        'address',
        'status',
        'rating',
        'total_deliveries',
        'completed_deliveries',
        'success_rate',
        'stock_value',
        'pending_orders',
        'vehicle_type',
        'license_number',
        'bank_account',
        'emergency_contact',
        'hire_date',
        'is_active'
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'success_rate' => 'decimal:1',
        'stock_value' => 'decimal:2',
        'total_deliveries' => 'integer',
        'completed_deliveries' => 'integer',
        'pending_orders' => 'integer',
        'hire_date' => 'date',
        'is_active' => 'boolean'
    ];

    protected $dates = [
        'hire_date',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    // Relationships
    public function products()
    {
        return $this->hasMany(DeliveryAgentProduct::class);
    }

    public function stockTransfers()
    {
        return $this->hasMany(StockTransfer::class, 'from_agent_id')
                    ->orWhere('to_agent_id', $this->id);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function auditFlags()
    {
        return $this->hasMany(AuditFlag::class);
    }

    public function performanceMetrics()
    {
        return $this->hasMany(AgentPerformanceMetric::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'Available');
    }

    public function scopeTopPerformers($query, $limit = 10)
    {
        return $query->orderBy('rating', 'desc')
                    ->orderBy('success_rate', 'desc')
                    ->limit($limit);
    }

    // Accessors
    public function getFormattedPhoneAttribute()
    {
        return '+234 ' . substr($this->phone, -10, 3) . ' ' . 
               substr($this->phone, -7, 3) . ' ' . 
               substr($this->phone, -4);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'Available' => 'green',
            'On Delivery' => 'blue',
            'Offline' => 'gray',
            'Suspended' => 'red',
            default => 'gray'
        };
    }

    public function getCurrentStockValueAttribute()
    {
        return $this->products()->sum('total_value');
    }
}
