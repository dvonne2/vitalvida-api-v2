<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'action',
        'table_name',
        'record_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'user_id',
        'user_role'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByTable($query, $table)
    {
        return $query->where('table_name', $table);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('user_role', $role);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // Helper methods
    public function getActionDisplayAttribute(): string
    {
        return str_replace('_', ' ', ucfirst($this->action));
    }

    public function getChangesAttribute(): array
    {
        if (!$this->old_values || !$this->new_values) {
            return [];
        }

        $changes = [];
        foreach ($this->new_values as $key => $newValue) {
            $oldValue = $this->old_values[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }

        return $changes;
    }

    public function hasChanges(): bool
    {
        return !empty($this->changes);
    }

    public function getFormattedTimestampAttribute(): string
    {
        return $this->created_at->format('Y-m-d H:i:s');
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    // Static methods for logging
    public static function logAction(string $action, array $data = []): self
    {
        return self::create([
            'action' => $action,
            'table_name' => $data['table_name'] ?? null,
            'record_id' => $data['record_id'] ?? null,
            'old_values' => $data['old_values'] ?? null,
            'new_values' => $data['new_values'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => auth()->id(),
            'user_role' => auth()->user()?->role ?? 'guest'
        ]);
    }

    public static function logConsignmentCreated(string $consignmentId): self
    {
        return self::logAction('consignment_created', [
            'table_name' => 'consignments',
            'record_id' => $consignmentId,
            'new_values' => ['consignment_id' => $consignmentId]
        ]);
    }

    public static function logFraudDetected(string $alertId, string $type): self
    {
        return self::logAction('fraud_detected', [
            'table_name' => 'fraud_alerts',
            'record_id' => $alertId,
            'new_values' => ['alert_id' => $alertId, 'type' => $type]
        ]);
    }

    public static function logMovementCreated(string $trackingNumber): self
    {
        return self::logAction('movement_created', [
            'table_name' => 'movement_tracking',
            'record_id' => $trackingNumber,
            'new_values' => ['tracking_number' => $trackingNumber]
        ]);
    }

    public static function logUserLogin(int $userId): self
    {
        return self::logAction('user_login', [
            'table_name' => 'users',
            'record_id' => $userId,
            'new_values' => ['user_id' => $userId]
        ]);
    }

    public static function logUserLogout(int $userId): self
    {
        return self::logAction('user_logout', [
            'table_name' => 'users',
            'record_id' => $userId,
            'new_values' => ['user_id' => $userId]
        ]);
    }

    public static function logDataUpdate(string $table, string $recordId, array $oldValues, array $newValues): self
    {
        return self::logAction('data_updated', [
            'table_name' => $table,
            'record_id' => $recordId,
            'old_values' => $oldValues,
            'new_values' => $newValues
        ]);
    }

    public static function logDataDeleted(string $table, string $recordId, array $deletedValues): self
    {
        return self::logAction('data_deleted', [
            'table_name' => $table,
            'record_id' => $recordId,
            'old_values' => $deletedValues
        ]);
    }
}
