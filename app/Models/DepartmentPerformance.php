<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentPerformance extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'metric_name',
        'target_value',
        'actual_value',
        'status',
        'trend',
        'performance_score',
        'measurement_date',
        'notes'
    ];

    protected $casts = [
        'measurement_date' => 'date',
        'performance_score' => 'decimal:2',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // Scopes
    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('measurement_date', [$startDate, $endDate]);
    }

    public function scopeByMetric($query, $metricName)
    {
        return $query->where('metric_name', $metricName);
    }

    // Helper methods
    public function getPerformancePercentageAttribute(): float
    {
        if (empty($this->target_value) || empty($this->actual_value)) {
            return 0;
        }

        $target = (float) preg_replace('/[^0-9.]/', '', $this->target_value);
        $actual = (float) preg_replace('/[^0-9.]/', '', $this->actual_value);

        if ($target == 0) {
            return 0;
        }

        return ($actual / $target) * 100;
    }

    public function getFormattedActualValueAttribute(): string
    {
        return $this->formatValue($this->actual_value);
    }

    public function getFormattedTargetValueAttribute(): string
    {
        return $this->formatValue($this->target_value);
    }

    private function formatValue($value): string
    {
        if (str_contains($value, '%')) {
            return $value;
        }

        if (str_contains($value, '₦')) {
            return $value;
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2);
        }

        return $value;
    }
}
