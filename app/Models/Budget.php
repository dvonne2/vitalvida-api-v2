<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'department',
        'fiscal_year',
        'month',
        'budget_amount',
        'actual_amount',
        'status',
        'created_by',
        'approved_by',
        'notes',
        'budget_categories',
        'variance_percentage',
        'variance_status',
    ];

    protected $casts = [
        'budget_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'variance_percentage' => 'decimal:2',
        'budget_categories' => 'array',
    ];

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    public function scopeByFiscalYear($query, $year)
    {
        return $query->where('fiscal_year', $year);
    }

    public function scopeByMonth($query, $month)
    {
        return $query->where('month', $month);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeLocked($query)
    {
        return $query->where('status', 'locked');
    }

    // Accessors
    public function getFormattedBudgetAmountAttribute()
    {
        return '₦' . number_format($this->budget_amount, 2);
    }

    public function getFormattedActualAmountAttribute()
    {
        return '₦' . number_format($this->actual_amount, 2);
    }

    public function getFormattedVarianceAttribute()
    {
        $variance = $this->budget_amount - $this->actual_amount;
        return '₦' . number_format($variance, 2);
    }

    public function getFormattedVariancePercentageAttribute()
    {
        return number_format($this->variance_percentage, 1) . '%';
    }

    public function getVarianceStatusColorAttribute()
    {
        return match($this->variance_status) {
            'under_budget' => 'text-green-600',
            'on_budget' => 'text-blue-600',
            'over_budget' => 'text-red-600',
            default => 'text-gray-600',
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'draft' => 'text-gray-600',
            'approved' => 'text-green-600',
            'locked' => 'text-red-600',
            default => 'text-gray-600',
        };
    }

    public function getStatusIconAttribute()
    {
        return match($this->status) {
            'draft' => '📝',
            'approved' => '✅',
            'locked' => '🔒',
            default => '📄',
        };
    }

    // Methods
    public function calculateVariance()
    {
        if ($this->budget_amount > 0) {
            $this->variance_percentage = (($this->budget_amount - $this->actual_amount) / $this->budget_amount) * 100;
            
            if ($this->actual_amount < $this->budget_amount) {
                $this->variance_status = 'under_budget';
            } elseif ($this->actual_amount == $this->budget_amount) {
                $this->variance_status = 'on_budget';
            } else {
                $this->variance_status = 'over_budget';
            }
            
            $this->save();
        }
    }

    public function updateActualAmount($amount)
    {
        $this->actual_amount = $amount;
        $this->calculateVariance();
    }

    public function approve($approvedBy)
    {
        $this->status = 'approved';
        $this->approved_by = $approvedBy;
        $this->save();
    }

    public function lock()
    {
        $this->status = 'locked';
        $this->save();
    }

    public function unlock()
    {
        $this->status = 'draft';
        $this->save();
    }

    public function getUtilizationPercentage()
    {
        if ($this->budget_amount > 0) {
            return ($this->actual_amount / $this->budget_amount) * 100;
        }
        return 0;
    }

    public function isOverBudget()
    {
        return $this->actual_amount > $this->budget_amount;
    }

    public function isUnderBudget()
    {
        return $this->actual_amount < $this->budget_amount;
    }

    public function getRemainingBudget()
    {
        return max(0, $this->budget_amount - $this->actual_amount);
    }

    public function getFormattedRemainingBudgetAttribute()
    {
        return '₦' . number_format($this->getRemainingBudget(), 2);
    }

    // Static methods
    public static function getDepartmentBudgets($department, $fiscalYear)
    {
        return static::where('department', $department)
            ->where('fiscal_year', $fiscalYear)
            ->orderBy('month')
            ->get();
    }

    public static function getTotalBudgetByDepartment($department, $fiscalYear)
    {
        return static::where('department', $department)
            ->where('fiscal_year', $fiscalYear)
            ->where('status', 'approved')
            ->sum('budget_amount');
    }

    public static function getTotalActualByDepartment($department, $fiscalYear)
    {
        return static::where('department', $department)
            ->where('fiscal_year', $fiscalYear)
            ->where('status', 'approved')
            ->sum('actual_amount');
    }

    public static function getOverBudgetDepartments($fiscalYear)
    {
        return static::select('department')
            ->selectRaw('SUM(budget_amount) as total_budget')
            ->selectRaw('SUM(actual_amount) as total_actual')
            ->where('fiscal_year', $fiscalYear)
            ->where('status', 'approved')
            ->groupBy('department')
            ->havingRaw('SUM(actual_amount) > SUM(budget_amount)')
            ->get();
    }

    public static function getBudgetSummary($fiscalYear)
    {
        return static::select('department')
            ->selectRaw('SUM(budget_amount) as total_budget')
            ->selectRaw('SUM(actual_amount) as total_actual')
            ->selectRaw('(SUM(budget_amount) - SUM(actual_amount)) as variance')
            ->selectRaw('((SUM(budget_amount) - SUM(actual_amount)) / SUM(budget_amount) * 100) as variance_percentage')
            ->where('fiscal_year', $fiscalYear)
            ->where('status', 'approved')
            ->groupBy('department')
            ->get();
    }
}
