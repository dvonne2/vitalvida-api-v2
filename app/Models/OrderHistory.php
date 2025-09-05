<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'staff_id',
        'action',
        'previous_status',
        'new_status',
        'timestamp',
        'notes',
        'auto_action',
        'metadata',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'auto_action' => 'boolean',
        'metadata' => 'array',
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    // Scopes
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    public function scopeAutoActions($query)
    {
        return $query->where('auto_action', true);
    }

    public function scopeManualActions($query)
    {
        return $query->where('auto_action', false);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('timestamp', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('timestamp', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('timestamp', now()->month)
                    ->whereYear('timestamp', now()->year);
    }

    // Business Methods
    public static function logAction(
        int $orderId,
        int $staffId,
        string $action,
        string $previousStatus,
        string $newStatus,
        string $notes = null,
        bool $autoAction = false,
        array $metadata = []
    ): self {
        return self::create([
            'order_id' => $orderId,
            'staff_id' => $staffId,
            'action' => $action,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'timestamp' => now(),
            'notes' => $notes,
            'auto_action' => $autoAction,
            'metadata' => $metadata,
        ]);
    }

    public function getActionDescriptionAttribute(): string
    {
        return match($this->action) {
            'order_created' => 'Order Created',
            'order_confirmed' => 'Order Confirmed',
            'payment_received' => 'Payment Received',
            'assigned_to_da' => 'Assigned to Delivery Agent',
            'picked_up' => 'Picked Up for Delivery',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'ghosted' => 'Ghosted',
            'rerouted' => 'Rerouted',
            'payment_mismatch' => 'Payment Mismatch Detected',
            'fraud_alert' => 'Fraud Alert Triggered',
            default => ucfirst(str_replace('_', ' ', $this->action))
        };
    }

    public function getActionColorAttribute(): string
    {
        return match($this->action) {
            'order_created' => 'blue',
            'order_confirmed' => 'green',
            'payment_received' => 'green',
            'assigned_to_da' => 'purple',
            'picked_up' => 'orange',
            'in_transit' => 'cyan',
            'delivered' => 'green',
            'cancelled' => 'red',
            'ghosted' => 'red',
            'rerouted' => 'yellow',
            'payment_mismatch' => 'red',
            'fraud_alert' => 'red',
            default => 'gray'
        };
    }

    public function getFormattedTimestampAttribute(): string
    {
        return $this->timestamp->format('M d, Y H:i:s');
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->timestamp->diffForHumans();
    }

    public function isRecent(): bool
    {
        return $this->timestamp->isAfter(now()->subHours(24));
    }

    public function getSummaryAttribute(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'staff_name' => $this->staff->name ?? 'System',
            'action' => $this->action,
            'action_description' => $this->action_description,
            'previous_status' => $this->previous_status,
            'new_status' => $this->new_status,
            'timestamp' => $this->formatted_timestamp,
            'time_ago' => $this->time_ago,
            'notes' => $this->notes,
            'auto_action' => $this->auto_action,
            'is_recent' => $this->isRecent(),
        ];
    }
}
