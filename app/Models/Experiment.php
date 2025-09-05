<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Experiment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'status',
        'department_id',
        'start_date',
        'end_date',
        'hypothesis',
        'success_metrics',
        'control_group',
        'test_group',
        'sample_size',
        'confidence_level',
        'p_value',
        'statistical_significance',
        'results_summary',
        'recommendation',
        'cost',
        'roi',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'success_metrics' => 'array',
        'cost' => 'decimal:2',
        'roi' => 'decimal:2',
        'p_value' => 'decimal:4',
        'statistical_significance' => 'boolean',
    ];

    // Relationships
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExperimentResult::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed')
            ->where('statistical_significance', true);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_date', [$startDate, $endDate]);
    }

    // Helper methods
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'active' => 'green',
            'paused' => 'yellow',
            'completed' => 'blue',
            'cancelled' => 'red',
            default => 'gray'
        };
    }

    public function getStatusIconAttribute()
    {
        return match($this->status) {
            'active' => 'play-circle',
            'paused' => 'pause-circle',
            'completed' => 'check-circle',
            'cancelled' => 'x-circle',
            default => 'circle'
        };
    }

    public function getFormattedCostAttribute()
    {
        return '₦' . number_format($this->cost, 2);
    }

    public function getFormattedRoiAttribute()
    {
        return '₦' . number_format($this->roi, 2);
    }

    public function getRoiPercentageAttribute()
    {
        if ($this->cost == 0) {
            return 0;
        }

        return ($this->roi / $this->cost) * 100;
    }

    public function getFormattedRoiPercentageAttribute()
    {
        return number_format($this->roi_percentage, 2) . '%';
    }

    public function getDurationInDaysAttribute()
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }

        return $this->start_date->diffInDays($this->end_date);
    }

    public function getProgressAttribute()
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }

        $totalDays = $this->start_date->diffInDays($this->end_date);
        $elapsedDays = $this->start_date->diffInDays(now());

        if ($totalDays == 0) {
            return 100;
        }

        return min(100, max(0, ($elapsedDays / $totalDays) * 100));
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isSuccessful()
    {
        return $this->isCompleted() && $this->statistical_significance;
    }

    public function activate()
    {
        $this->update([
            'status' => 'active',
            'start_date' => now()
        ]);
    }

    public function pause()
    {
        $this->update(['status' => 'paused']);
    }

    public function complete($results = [])
    {
        $this->update([
            'status' => 'completed',
            'end_date' => now(),
            'results_summary' => $results['summary'] ?? null,
            'recommendation' => $results['recommendation'] ?? null,
            'p_value' => $results['p_value'] ?? null,
            'statistical_significance' => $results['statistical_significance'] ?? false,
        ]);
    }

    public function cancel()
    {
        $this->update([
            'status' => 'cancelled',
            'end_date' => now()
        ]);
    }

    // Static methods
    public static function getActiveExperiments()
    {
        return static::with(['department', 'createdBy'])
            ->active()
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public static function getCompletedExperiments()
    {
        return static::with(['department', 'createdBy'])
            ->completed()
            ->orderBy('end_date', 'desc')
            ->get();
    }

    public static function getSuccessfulExperiments()
    {
        return static::with(['department', 'createdBy'])
            ->successful()
            ->orderBy('end_date', 'desc')
            ->get();
    }

    public static function getExperimentCounts()
    {
        return [
            'total' => static::count(),
            'active' => static::active()->count(),
            'completed' => static::completed()->count(),
            'successful' => static::successful()->count(),
            'paused' => static::where('status', 'paused')->count(),
            'cancelled' => static::where('status', 'cancelled')->count(),
        ];
    }

    public static function getExperimentPerformance()
    {
        $completed = static::completed()->count();
        $successful = static::successful()->count();

        return [
            'total_completed' => $completed,
            'successful' => $successful,
            'success_rate' => $completed > 0 ? ($successful / $completed) * 100 : 0,
            'total_cost' => static::sum('cost'),
            'total_roi' => static::sum('roi'),
            'average_roi' => static::avg('roi'),
        ];
    }

    public static function createExperiment($data)
    {
        return static::create([
            'name' => $data['name'],
            'description' => $data['description'],
            'type' => $data['type'],
            'status' => 'draft',
            'department_id' => $data['department_id'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'hypothesis' => $data['hypothesis'],
            'success_metrics' => $data['success_metrics'] ?? [],
            'control_group' => $data['control_group'] ?? null,
            'test_group' => $data['test_group'] ?? null,
            'sample_size' => $data['sample_size'] ?? null,
            'confidence_level' => $data['confidence_level'] ?? 0.95,
            'cost' => $data['cost'] ?? 0,
            'created_by' => $data['created_by'] ?? auth()->id(),
        ]);
    }
} 