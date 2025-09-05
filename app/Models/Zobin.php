<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zobin extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_agent_id', 'zoho_storage_id', 'zoho_warehouse_id',
        'shampoo_count', 'pomade_count', 'conditioner_count', 'last_updated'
    ];

    protected $dates = ['last_updated'];

    public function deliveryAgent() 
    { 
        return $this->belongsTo(DeliveryAgent::class); 
    }
    
    // Calculate available complete sets
    public function getAvailableSetsAttribute() 
    {
        return min($this->shampoo_count, $this->pomade_count, $this->conditioner_count);
    }
    
    // Check if stock is critically low
    public function isCriticallyLow() 
    {
        return $this->available_sets < 3;
    }
}
