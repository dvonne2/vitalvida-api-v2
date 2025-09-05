<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovementTracking extends Model
{
    use HasFactory;

    protected $table = 'movement_tracking';

    protected $fillable = [
        'movement_type',
        'from_location',
        'to_location',
        'quantity',
        'status',
        'tracking_number',
        'notes',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('movement_type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    // Helper methods
    public function getTypeDisplayAttribute(): string
    {
        return match($this->movement_type) {
            'warehouse_to_da' => 'Warehouse to DA',
            'da_to_da' => 'DA to DA',
            'da_to_hq' => 'DA to HQ',
            'da_to_warehouse' => 'DA to Warehouse',
            default => ucfirst(str_replace('_', ' ', $this->movement_type))
        };
    }

    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => 'Unknown'
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'in_progress' => 'blue',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray'
        };
    }

    public function getDurationAttribute(): string
    {
        if ($this->completed_at && $this->started_at) {
            $hours = $this->started_at->diffInHours($this->completed_at);
            return $hours . ' hours';
        }
        
        if ($this->started_at) {
            $hours = $this->started_at->diffInHours(now());
            return $hours . ' hours (ongoing)';
        }
        
        return 'N/A';
    }

    public function isDelayed(): bool
    {
        if ($this->status === 'in_progress' && $this->started_at) {
            return $this->started_at->diffInHours(now()) > 24;
        }
        
        return false;
    }

    public function start(): bool
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now()
        ]);
        
        return true;
    }

    public function complete(): bool
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
        
        return true;
    }

    public function cancel(): bool
    {
        $this->update([
            'status' => 'cancelled'
        ]);
        
        return true;
    }

    // Static methods for creating movements
    public static function createWarehouseToDA(string $from, string $to, string $quantity, string $notes = null): self
    {
        return self::create([
            'movement_type' => 'warehouse_to_da',
            'from_location' => $from,
            'to_location' => $to,
            'quantity' => $quantity,
            'status' => 'pending',
            'tracking_number' => 'MT-' . strtoupper(uniqid()),
            'notes' => $notes
        ]);
    }

    public static function createDAToDA(string $from, string $to, string $quantity, string $notes = null): self
    {
        return self::create([
            'movement_type' => 'da_to_da',
            'from_location' => $from,
            'to_location' => $to,
            'quantity' => $quantity,
            'status' => 'pending',
            'tracking_number' => 'MT-' . strtoupper(uniqid()),
            'notes' => $notes
        ]);
    }

    public static function createDAToHQ(string $from, string $to, string $quantity, string $notes = null): self
    {
        return self::create([
            'movement_type' => 'da_to_hq',
            'from_location' => $from,
            'to_location' => $to,
            'quantity' => $quantity,
            'status' => 'pending',
            'tracking_number' => 'MT-' . strtoupper(uniqid()),
            'notes' => $notes
        ]);
    }
}
