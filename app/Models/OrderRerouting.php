<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRerouting extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'from_staff_id',
        'to_staff_id',
        'reason',
        'timestamp',
        'success_status',
        'notes',
        'auto_rerouted',
        'rerouted_by',
        'previous_status',
        'new_status',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'auto_rerouted' => 'boolean',
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fromStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_staff_id');
    }

    public function toStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_staff_id');
    }

    public function reroutedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rerouted_by');
    }

    // Scopes
    public function scopeByReason($query, $reason)
    {
        return $query->where('reason', $reason);
    }

    public function scopeAutoRerouted($query)
    {
        return $query->where('auto_rerouted', true);
    }

    public function scopeManualRerouted($query)
    {
        return $query->where('auto_rerouted', false);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('success_status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('success_status', 'failed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('timestamp', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('timestamp', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    // Business Methods
    public static function logRerouting(
        int $orderId,
        int $fromStaffId,
        int $toStaffId,
        string $reason,
        bool $autoRerouted = false,
        int $reroutedBy = null,
        string $notes = null
    ): self {
        return self::create([
            'order_id' => $orderId,
            'from_staff_id' => $fromStaffId,
            'to_staff_id' => $toStaffId,
            'reason' => $reason,
            'timestamp' => now(),
            'success_status' => 'pending',
            'notes' => $notes,
            'auto_rerouted' => $autoRerouted,
            'rerouted_by' => $reroutedBy,
        ]);
    }

    public function markAsSuccessful(): void
    {
        $this->update(['success_status' => 'success']);
    }

    public function markAsFailed(): void
    {
        $this->update(['success_status' => 'failed']);
    }

    public function getReasonDescriptionAttribute(): string
    {
        return match($this->reason) {
            'ghosted' => 'Order Ghosted',
            'staff_unavailable' => 'Staff Unavailable',
            'performance_issue' => 'Performance Issue',
            'customer_request' => 'Customer Request',
            'fraud_alert' => 'Fraud Alert',
            'system_optimization' => 'System Optimization',
            'manual_reassignment' => 'Manual Reassignment',
            'auto_optimization' => 'Auto Optimization',
            default => ucfirst(str_replace('_', ' ', $this->reason))
        };
    }

    public function getReasonColorAttribute(): string
    {
        return match($this->reason) {
            'ghosted' => 'red',
            'staff_unavailable' => 'orange',
            'performance_issue' => 'yellow',
            'customer_request' => 'blue',
            'fraud_alert' => 'red',
            'system_optimization' => 'green',
            'manual_reassignment' => 'purple',
            'auto_optimization' => 'cyan',
            default => 'gray'
        };
    }

    public function getSuccessStatusColorAttribute(): string
    {
        return match($this->success_status) {
            'success' => 'green',
            'failed' => 'red',
            'pending' => 'yellow',
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

    public function getReroutingSummary(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_number' => $this->order->order_number ?? 'Unknown',
            'from_staff' => $this->fromStaff->name ?? 'Unknown',
            'to_staff' => $this->toStaff->name ?? 'Unknown',
            'reason' => $this->reason,
            'reason_description' => $this->reason_description,
            'timestamp' => $this->formatted_timestamp,
            'time_ago' => $this->time_ago,
            'success_status' => $this->success_status,
            'auto_rerouted' => $this->auto_rerouted,
            'rerouted_by' => $this->reroutedBy->name ?? 'System',
            'notes' => $this->notes,
            'is_recent' => $this->isRecent(),
        ];
    }

    public function getReroutingAnalytics(): array
    {
        return [
            'total_reroutings' => self::where('order_id', $this->order_id)->count(),
            'successful_reroutings' => self::where('order_id', $this->order_id)
                ->where('success_status', 'success')->count(),
            'failed_reroutings' => self::where('order_id', $this->order_id)
                ->where('success_status', 'failed')->count(),
            'auto_reroutings' => self::where('order_id', $this->order_id)
                ->where('auto_rerouted', true)->count(),
            'manual_reroutings' => self::where('order_id', $this->order_id)
                ->where('auto_rerouted', false)->count(),
        ];
    }
}
