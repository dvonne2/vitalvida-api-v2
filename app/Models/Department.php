<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'head_user_id',
        'budget',
        'target_revenue',
        'current_revenue',
        'employee_count',
        'status',
        'color',
        'icon',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'target_revenue' => 'decimal:2',
        'current_revenue' => 'decimal:2',
        'employee_count' => 'integer',
    ];

    // Relationships
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(Exception::class);
    }

    public function performanceMetrics(): HasMany
    {
        return $this->hasMany(PerformanceMetric::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function scopeByHead($query, $headUserId)
    {
        return $query->where('head_user_id', $headUserId);
    }

    // Helper methods
    public function getFormattedBudgetAttribute()
    {
        return '₦' . number_format($this->budget, 2);
    }

    public function getFormattedTargetRevenueAttribute()
    {
        return '₦' . number_format($this->target_revenue, 2);
    }

    public function getFormattedCurrentRevenueAttribute()
    {
        return '₦' . number_format($this->current_revenue, 2);
    }

    public function getRevenueAchievementAttribute()
    {
        if ($this->target_revenue == 0) {
            return 0;
        }

        return ($this->current_revenue / $this->target_revenue) * 100;
    }

    public function getBudgetUtilizationAttribute()
    {
        if ($this->budget == 0) {
            return 0;
        }

        return ($this->current_revenue / $this->budget) * 100;
    }

    public function getPerformanceStatusAttribute()
    {
        $achievement = $this->revenue_achievement;

        if ($achievement >= 100) {
            return 'exceeded';
        } elseif ($achievement >= 80) {
            return 'on_track';
        } elseif ($achievement >= 60) {
            return 'at_risk';
        } else {
            return 'behind';
        }
    }

    public function getPerformanceColorAttribute()
    {
        $status = $this->performance_status;

        return match($status) {
            'exceeded' => 'green',
            'on_track' => 'blue',
            'at_risk' => 'yellow',
            'behind' => 'red',
            default => 'gray'
        };
    }

    // Static methods
    public static function getDepartmentByCode($code)
    {
        return static::where('code', $code)->first();
    }

    public static function getTotalRevenue()
    {
        return static::sum('current_revenue');
    }

    public static function getTotalBudget()
    {
        return static::sum('budget');
    }

    public static function getTotalEmployees()
    {
        return static::sum('employee_count');
    }

    public static function getDepartmentPerformance()
    {
        return static::with(['head', 'performanceMetrics'])
            ->get()
            ->map(function ($department) {
                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'head' => $department->head?->name,
                    'current_revenue' => $department->current_revenue,
                    'target_revenue' => $department->target_revenue,
                    'achievement' => $department->revenue_achievement,
                    'performance_status' => $department->performance_status,
                    'performance_color' => $department->performance_color,
                    'employee_count' => $department->employee_count,
                ];
            });
    }
} 