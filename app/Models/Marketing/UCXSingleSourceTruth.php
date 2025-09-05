<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Customer;
use App\Models\Company;

class UCXSingleSourceTruth extends Model
{
    use HasFactory;

    protected $table = 'ucx_single_source_truth';

    protected $fillable = [
        'customer_id',
        'data_type',
        'unified_data',
        'data_sources',
        'last_sync',
        'sync_conflicts',
        'is_current',
        'access_permissions',
        'company_id'
    ];

    protected $casts = [
        'unified_data' => 'array',
        'data_sources' => 'array',
        'sync_conflicts' => 'array',
        'access_permissions' => 'array',
        'is_current' => 'boolean',
        'last_sync' => 'datetime'
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get data freshness in hours
     */
    public function getDataFreshnessAttribute(): float
    {
        if (!$this->last_sync) {
            return 0;
        }

        return $this->last_sync->diffInHours(now());
    }

    /**
     * Check if data is stale (older than specified hours)
     */
    public function isStale($hours = 24): bool
    {
        return $this->data_freshness > $hours;
    }

    /**
     * Check if there are sync conflicts
     */
    public function hasConflicts(): bool
    {
        return !empty($this->sync_conflicts);
    }

    /**
     * Get the number of data sources contributing to this record
     */
    public function getSourceCountAttribute(): int
    {
        return is_array($this->data_sources) ? count($this->data_sources) : 0;
    }

    /**
     * Get data quality score based on freshness, conflicts, and source count
     */
    public function getDataQualityScoreAttribute(): float
    {
        $score = 10.0;

        // Reduce score for staleness
        if ($this->isStale(24)) {
            $score -= 2.0;
        } elseif ($this->isStale(12)) {
            $score -= 1.0;
        }

        // Reduce score for conflicts
        if ($this->hasConflicts()) {
            $conflictCount = count($this->sync_conflicts);
            $score -= min(3.0, $conflictCount * 0.5);
        }

        // Increase score for multiple sources (better data coverage)
        if ($this->source_count > 3) {
            $score += 1.0;
        } elseif ($this->source_count < 2) {
            $score -= 1.0;
        }

        return max(0, min(10, $score));
    }

    /**
     * Get specific data field from unified data
     */
    public function getDataField($field, $default = null)
    {
        return data_get($this->unified_data, $field, $default);
    }

    /**
     * Check if user has permission to access this data
     */
    public function userCanAccess($userId): bool
    {
        $permissions = $this->access_permissions;
        
        if (!$permissions || !is_array($permissions)) {
            return false;
        }

        return in_array($userId, $permissions['allowed_users'] ?? []) ||
               in_array('all', $permissions['access_level'] ?? []);
    }

    /**
     * Scope for current (non-archived) records
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope for specific data type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('data_type', $type);
    }

    /**
     * Scope for fresh data (not stale)
     */
    public function scopeFresh($query, $hours = 24)
    {
        return $query->where('last_sync', '>=', now()->subHours($hours));
    }

    /**
     * Scope for data without conflicts
     */
    public function scopeWithoutConflicts($query)
    {
        return $query->whereNull('sync_conflicts');
    }

    /**
     * Scope for high quality data
     */
    public function scopeHighQuality($query)
    {
        return $query->fresh(12)->withoutConflicts();
    }

    /**
     * Get unified customer profile data
     */
    public static function getUnifiedProfile($customerId, $companyId)
    {
        $profileData = static::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('is_current', true)
            ->get()
            ->keyBy('data_type');

        return [
            'profile' => $profileData->get('profile')?->unified_data ?? [],
            'preferences' => $profileData->get('preferences')?->unified_data ?? [],
            'behavior' => $profileData->get('behavior')?->unified_data ?? [],
            'context' => $profileData->get('context')?->unified_data ?? [],
            'data_quality' => [
                'profile_quality' => $profileData->get('profile')?->data_quality_score ?? 0,
                'preferences_quality' => $profileData->get('preferences')?->data_quality_score ?? 0,
                'behavior_quality' => $profileData->get('behavior')?->data_quality_score ?? 0,
                'context_quality' => $profileData->get('context')?->data_quality_score ?? 0,
                'overall_quality' => $profileData->avg('data_quality_score') ?? 0
            ],
            'last_updated' => $profileData->max('last_sync'),
            'data_sources' => $profileData->pluck('data_sources')->flatten()->unique()->values()
        ];
    }

    /**
     * Update unified data with conflict resolution
     */
    public function updateUnifiedData($newData, $source, $conflictResolutionStrategy = 'merge')
    {
        $currentData = $this->unified_data ?? [];
        $conflicts = [];

        // Detect conflicts
        foreach ($newData as $key => $value) {
            if (isset($currentData[$key]) && $currentData[$key] !== $value) {
                $conflicts[$key] = [
                    'current_value' => $currentData[$key],
                    'new_value' => $value,
                    'source' => $source,
                    'detected_at' => now()
                ];
            }
        }

        // Resolve conflicts based on strategy
        $resolvedData = $this->resolveConflicts($currentData, $newData, $conflicts, $conflictResolutionStrategy);

        // Update sources
        $sources = $this->data_sources ?? [];
        if (!in_array($source, $sources)) {
            $sources[] = $source;
        }

        $this->update([
            'unified_data' => $resolvedData,
            'data_sources' => $sources,
            'sync_conflicts' => empty($conflicts) ? null : $conflicts,
            'last_sync' => now()
        ]);

        return $this;
    }

    /**
     * Resolve data conflicts based on strategy
     */
    private function resolveConflicts($currentData, $newData, $conflicts, $strategy)
    {
        return match($strategy) {
            'overwrite' => array_merge($currentData, $newData),
            'preserve' => array_merge($newData, $currentData),
            'merge' => $this->mergeDataIntelligently($currentData, $newData, $conflicts),
            'timestamp' => $this->resolveByTimestamp($currentData, $newData, $conflicts),
            default => array_merge($currentData, $newData)
        };
    }

    /**
     * Intelligent merge strategy
     */
    private function mergeDataIntelligently($currentData, $newData, $conflicts)
    {
        $merged = $currentData;

        foreach ($newData as $key => $value) {
            if (!isset($conflicts[$key])) {
                // No conflict, safe to merge
                $merged[$key] = $value;
            } else {
                // Conflict exists, apply intelligent resolution
                $merged[$key] = $this->resolveFieldConflict($key, $currentData[$key], $value);
            }
        }

        return $merged;
    }

    /**
     * Resolve individual field conflicts
     */
    private function resolveFieldConflict($field, $currentValue, $newValue)
    {
        // Field-specific resolution logic
        return match($field) {
            'email', 'phone' => $this->isMoreComplete($newValue, $currentValue) ? $newValue : $currentValue,
            'last_activity', 'updated_at' => max($currentValue, $newValue),
            'preferences' => is_array($currentValue) && is_array($newValue) ? 
                array_merge($currentValue, $newValue) : $newValue,
            default => $newValue // Default to new value
        };
    }

    /**
     * Check if new value is more complete than current
     */
    private function isMoreComplete($newValue, $currentValue)
    {
        if (is_string($newValue) && is_string($currentValue)) {
            return strlen($newValue) > strlen($currentValue);
        }

        if (is_array($newValue) && is_array($currentValue)) {
            return count($newValue) > count($currentValue);
        }

        return !empty($newValue) && empty($currentValue);
    }

    /**
     * Resolve conflicts by timestamp (newest wins)
     */
    private function resolveByTimestamp($currentData, $newData, $conflicts)
    {
        // This would require timestamp metadata for each field
        // For now, default to new data wins
        return array_merge($currentData, $newData);
    }
}
