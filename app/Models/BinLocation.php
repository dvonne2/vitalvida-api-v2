<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BinLocation extends Model
{
    protected $fillable = [
        'bin_id', 'bin_name', 'warehouse_id', 'zone', 'aisle', 
        'rack', 'shelf', 'is_active', 'restrictions'
    ];

    protected $casts = [
        'restrictions' => 'array',
        'is_active' => 'boolean'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
