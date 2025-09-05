<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Revenue extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'total_revenue',
        'order_revenue',
        'delivery_revenue',
        'service_revenue',
        'other_revenue',
        'department_id',
        'source',
        'currency',
        'exchange_rate',
        'notes',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'date' => 'date',
        'total_revenue' => 'decimal:2',
        'order_revenue' => 'decimal:2',
        'delivery_revenue' => 'decimal:2',
        'service_revenue' => 'decimal:2',
        'other_revenue' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
    ];

    // Relationships
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'revenue_date', 'date');
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
    public function scopeByDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }

    // Helper methods
    public function getFormattedTotalRevenueAttribute()
    {
        return '₦' . number_format($this->total_revenue, 2);
    }

    public function getFormattedOrderRevenueAttribute()
    {
        return '₦' . number_format($this->order_revenue, 2);
    }

    public function getFormattedDeliveryRevenueAttribute()
    {
        return '₦' . number_format($this->delivery_revenue, 2);
    }

    public function getRevenueGrowthAttribute()
    {
        // Calculate growth compared to previous period
        $previousRevenue = static::where('date', '<', $this->date)
            ->where('department_id', $this->department_id)
            ->orderBy('date', 'desc')
            ->first();

        if (!$previousRevenue || $previousRevenue->total_revenue == 0) {
            return 0;
        }

        return (($this->total_revenue - $previousRevenue->total_revenue) / $previousRevenue->total_revenue) * 100;
    }

    public function getRevenueBreakdownAttribute()
    {
        return [
            'order_revenue' => $this->order_revenue,
            'delivery_revenue' => $this->delivery_revenue,
            'service_revenue' => $this->service_revenue,
            'other_revenue' => $this->other_revenue,
        ];
    }

    // Static methods for aggregations
    public static function getDailyRevenue($date)
    {
        return static::where('date', $date)->sum('total_revenue');
    }

    public static function getMonthlyRevenue($year, $month)
    {
        return static::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('total_revenue');
    }

    public static function getRevenueByDepartment($date, $departmentId = null)
    {
        $query = static::where('date', $date);
        
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        return $query->sum('total_revenue');
    }

    public static function getRevenueTrend($days = 30)
    {
        return static::where('date', '>=', now()->subDays($days))
            ->groupBy('date')
            ->selectRaw('date, SUM(total_revenue) as daily_revenue')
            ->orderBy('date')
            ->get();
    }
} 