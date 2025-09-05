<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class FinancialMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'revenue',
        'costs',
        'profit',
        'margin',
        'orders_count',
        'delivered_orders',
        'ghosted_orders',
        'average_order_value',
        'product_line_data',
        'da_performance_data',
        'state_performance_data',
    ];

    protected $casts = [
        'date' => 'date',
        'revenue' => 'decimal:2',
        'costs' => 'decimal:2',
        'profit' => 'decimal:2',
        'margin' => 'decimal:2',
        'average_order_value' => 'decimal:2',
        'product_line_data' => 'array',
        'da_performance_data' => 'array',
        'state_performance_data' => 'array',
    ];

    /**
     * Scope to get metrics for a specific period
     */
    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to get today's metrics
     */
    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    /**
     * Scope to get this week's metrics
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    /**
     * Scope to get this month's metrics
     */
    public function scopeThisMonth($query)
    {
        return $query->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()]);
    }

    /**
     * Get formatted revenue
     */
    public function getFormattedRevenueAttribute(): string
    {
        return '₦' . $this->formatNumber($this->revenue);
    }

    /**
     * Get formatted costs
     */
    public function getFormattedCostsAttribute(): string
    {
        return '₦' . $this->formatNumber($this->costs);
    }

    /**
     * Get formatted profit
     */
    public function getFormattedProfitAttribute(): string
    {
        return '₦' . $this->formatNumber($this->profit);
    }

    /**
     * Get formatted margin
     */
    public function getFormattedMarginAttribute(): string
    {
        return $this->margin . '%';
    }

    /**
     * Get delivery rate
     */
    public function getDeliveryRateAttribute(): float
    {
        return $this->orders_count > 0 ? round(($this->delivered_orders / $this->orders_count) * 100, 2) : 0;
    }

    /**
     * Get ghost rate
     */
    public function getGhostRateAttribute(): float
    {
        return $this->orders_count > 0 ? round(($this->ghosted_orders / $this->orders_count) * 100, 2) : 0;
    }

    /**
     * Get ROI
     */
    public function getRoiAttribute(): float
    {
        return $this->costs > 0 ? round((($this->profit) / $this->costs) * 100, 2) : 0;
    }

    /**
     * Check if profitable
     */
    public function isProfitable(): bool
    {
        return $this->profit > 0;
    }

    /**
     * Check if high performing
     */
    public function isHighPerforming(): bool
    {
        return $this->delivery_rate >= 80 && $this->margin >= 30;
    }

    /**
     * Format number for display
     */
    private function formatNumber($number): string
    {
        if ($number >= 1000000) {
            return number_format($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return number_format($number / 1000) . 'K';
        }
        return number_format($number);
    }

    /**
     * Get product line performance
     */
    public function getProductLinePerformance(): array
    {
        return $this->product_line_data ?? [];
    }

    /**
     * Get DA performance data
     */
    public function getDAPerformance(): array
    {
        return $this->da_performance_data ?? [];
    }

    /**
     * Get state performance data
     */
    public function getStatePerformance(): array
    {
        return $this->state_performance_data ?? [];
    }

    /**
     * Calculate metrics from orders
     */
    public static function calculateFromOrders($date = null): array
    {
        $date = $date ?? today();
        
        $orders = Order::whereDate('created_at', $date);
        $deliveredOrders = Order::whereDate('delivered_at', $date)->where('status', 'delivered');
        $confirmedPayments = Order::whereDate('created_at', $date)->where('payment_status', 'confirmed');
        
        $totalOrders = $orders->count();
        $deliveredCount = $deliveredOrders->count();
        $ghostedCount = $orders->where('is_ghosted', true)->count();
        $revenue = $confirmedPayments->sum('total_amount');
        $costs = $revenue * 0.65; // Assume 65% cost ratio
        $profit = $revenue - $costs;
        $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;
        $avgOrderValue = $confirmedPayments->avg('total_amount') ?? 0;

        return [
            'date' => $date,
            'revenue' => $revenue,
            'costs' => $costs,
            'profit' => $profit,
            'margin' => $margin,
            'orders_count' => $totalOrders,
            'delivered_orders' => $deliveredCount,
            'ghosted_orders' => $ghostedCount,
            'average_order_value' => $avgOrderValue,
        ];
    }
}
