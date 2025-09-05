<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VitalVidaSupplier extends Model
{
    use HasFactory;

    protected $table = 'vitalvida_suppliers';

    protected $fillable = [
        'supplier_code',
        'company_name',
        'contact_person', 
        'phone',
        'email',
        'business_address',
        'website',
        'products_supplied',
        'rating',
        'total_orders',
        'total_purchase_value',
        'payment_terms',
        'delivery_time',
        'status'
    ];

    protected $casts = [
        'products_supplied' => 'array',
        'rating' => 'decimal:2',
        'total_purchase_value' => 'decimal:2'
    ];

    // Relationships
    public function products()
    {
        return $this->hasMany(\App\Models\VitalVidaInventory\Product::class, 'supplier_id');
    }

    public function performance()
    {
        return $this->hasMany(VitalVidaSupplierPerformance::class, 'supplier_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeTopRated($query)
    {
        return $query->where('rating', '>=', 4.0)->orderBy('rating', 'desc');
    }

    // Accessors
    public function getFormattedPurchaseValueAttribute()
    {
        return '₦' . number_format($this->total_purchase_value, 2);
    }
}
