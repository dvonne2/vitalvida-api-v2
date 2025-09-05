<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'address',
        'manager',
        'phone',
        'capacity',
        'status'
    ];

    public function warehouseStock()
    {
        return $this->hasMany(WarehouseStock::class);
    }
}
