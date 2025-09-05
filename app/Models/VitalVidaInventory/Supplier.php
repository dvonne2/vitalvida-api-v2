<?php

namespace App\Models\VitalVidaInventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vitalvida_suppliers';

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'country',
        'payment_terms',
        'delivery_rating',
        'quality_rating',
        'overall_rating',
        'total_orders',
        'active_orders',
        'bank_details',
        'tax_id',
        'is_active'
    ];

    protected $casts = [
        'delivery_rating' => 'decimal:1',
        'quality_rating' => 'decimal:1',
        'overall_rating' => 'decimal:1',
        'total_orders' => 'integer',
        'active_orders' => 'integer',
        'is_active' => 'boolean',
        'bank_details' => 'json'
    ];

    // Relationships
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTopRated($query, $limit = 10)
    {
        return $query->orderBy('overall_rating', 'desc')->limit($limit);
    }
}
