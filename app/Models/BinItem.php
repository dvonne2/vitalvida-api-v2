<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinItem extends Model
{
    protected $fillable = [
        'bin_id',
        'item_id',
        'item_name',
        'quantity',
        'reserved_quantity',
        'cost_per_unit',
        'expiry_date',
        'batch_number',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'cost_per_unit' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function getAvailableQuantity()
    {
        return $this->quantity - $this->reserved_quantity;
    }

    public function reserveQuantity($amount)
    {
        if ($this->getAvailableQuantity() >= $amount) {
            $this->increment('reserved_quantity', $amount);
            return true;
        }
        return false;
    }

    public function deductQuantity($amount)
    {
        if ($this->quantity >= $amount) {
            $this->decrement('quantity', $amount);
            $this->decrement('reserved_quantity', min($amount, $this->reserved_quantity));
            return true;
        }
        return false;
    }
}
