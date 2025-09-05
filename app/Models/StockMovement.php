<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'movement_type',
        'source_type',
        'source_id',
        'destination_type',
        'destination_id',
        'quantity',
        'reference_type',
        'reference_id',
        'performed_by',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceUser()
    {
        return $this->belongsTo(User::class, 'source_id')->where('source_type', 'delivery_agent');
    }

    public function destinationUser()
    {
        return $this->belongsTo(User::class, 'destination_id')->where('destination_type', 'delivery_agent');
    }
}
