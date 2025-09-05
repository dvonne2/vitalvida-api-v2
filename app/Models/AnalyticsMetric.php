<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric_name',
        'metric_category',
        'metric_type',
        'metric_value',
        'unit',
        'dimensions',
        'recorded_at',
        'data_source',
        'aggregation_level',
        'metadata'
    ];

    protected $casts = [
        'metric_value' => 'decimal:4',
        'dimensions' => 'array',
        'recorded_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Get metrics by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('metric_category', $category);
    }

    /**
     * Get metrics by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    /**
     * Get metrics within date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    /**
     * Get metrics by aggregation level
     */
    public function scopeByAggregationLevel($query, string $level)
    {
        return $query->where('aggregation_level', $level);
    }

    /**
     * Get metrics by data source
     */
    public function scopeByDataSource($query, string $source)
    {
        return $query->where('data_source', $source);
    }

    /**
     * Get latest metrics for a specific metric name
     */
    public function scopeLatestByName($query, string $metricName, int $limit = 100)
    {
        return $query->where('metric_name', $metricName)
                    ->orderBy('recorded_at', 'desc')
                    ->limit($limit);
    }

    /**
     * Get aggregated metrics for a time period
     */
    public function scopeAggregatedByPeriod($query, string $period = 'day')
    {
        return $query->selectRaw("
                DATE(recorded_at) as date,
                metric_name,
                metric_category,
                AVG(metric_value) as avg_value,
                MAX(metric_value) as max_value,
                MIN(metric_value) as min_value,
                SUM(metric_value) as sum_value,
                COUNT(*) as count
            ")
            ->groupBy('date', 'metric_name', 'metric_category')
            ->orderBy('date', 'desc');
    }

    /**
     * Get trend analysis for a metric
     */
    public function scopeTrendAnalysis($query, string $metricName, int $days = 30)
    {
        return $query->where('metric_name', $metricName)
                    ->where('recorded_at', '>=', now()->subDays($days))
                    ->orderBy('recorded_at', 'asc');
    }

    /**
     * Get metrics with specific dimensions
     */
    public function scopeWithDimensions($query, array $dimensions)
    {
        foreach ($dimensions as $key => $value) {
            $query->whereJsonContains("dimensions->{$key}", $value);
        }
        return $query;
    }

    /**
     * Get metrics above threshold
     */
    public function scopeAboveThreshold($query, float $threshold)
    {
        return $query->where('metric_value', '>', $threshold);
    }

    /**
     * Get metrics below threshold
     */
    public function scopeBelowThreshold($query, float $threshold)
    {
        return $query->where('metric_value', '<', $threshold);
    }

    /**
     * Get metrics within threshold range
     */
    public function scopeWithinThreshold($query, float $min, float $max)
    {
        return $query->whereBetween('metric_value', [$min, $max]);
    }

    /**
     * Get anomaly metrics (statistical outliers)
     */
    public function scopeAnomalies($query, string $metricName, float $stdDevMultiplier = 2)
    {
        $stats = $query->where('metric_name', $metricName)
                      ->selectRaw('
                          AVG(metric_value) as mean,
                          STDDEV(metric_value) as stddev
                      ')
                      ->first();

        if ($stats && $stats->stddev > 0) {
            $lowerBound = $stats->mean - ($stdDevMultiplier * $stats->stddev);
            $upperBound = $stats->mean + ($stdDevMultiplier * $stats->stddev);

            return $query->where('metric_name', $metricName)
                        ->where(function($q) use ($lowerBound, $upperBound) {
                            $q->where('metric_value', '<', $lowerBound)
                              ->orWhere('metric_value', '>', $upperBound);
                        });
        }

        return $query->where('metric_name', $metricName);
    }

    /**
     * Get metrics for comparison (current vs previous period)
     */
    public function scopeForComparison($query, string $metricName, string $period = 'month')
    {
        $currentStart = match($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth()
        };

        $previousStart = match($period) {
            'day' => now()->subDay()->startOfDay(),
            'week' => now()->subWeek()->startOfWeek(),
            'month' => now()->subMonth()->startOfMonth(),
            'quarter' => now()->subQuarter()->startOfQuarter(),
            'year' => now()->subYear()->startOfYear(),
            default => now()->subMonth()->startOfMonth()
        };

        return $query->where('metric_name', $metricName)
                    ->where(function($q) use ($currentStart, $previousStart) {
                        $q->where('recorded_at', '>=', $currentStart)
                          ->orWhere(function($subQ) use ($previousStart, $currentStart) {
                              $subQ->where('recorded_at', '>=', $previousStart)
                                   ->where('recorded_at', '<', $currentStart);
                          });
                    })
                    ->orderBy('recorded_at', 'asc');
    }

    /**
     * Get metrics for forecasting
     */
    public function scopeForForecasting($query, string $metricName, int $dataPoints = 24)
    {
        return $query->where('metric_name', $metricName)
                    ->orderBy('recorded_at', 'desc')
                    ->limit($dataPoints)
                    ->orderBy('recorded_at', 'asc');
    }

    /**
     * Get metrics for real-time monitoring
     */
    public function scopeForRealTimeMonitoring($query, array $metricNames, int $minutes = 5)
    {
        return $query->whereIn('metric_name', $metricNames)
                    ->where('recorded_at', '>=', now()->subMinutes($minutes))
                    ->orderBy('recorded_at', 'desc');
    }

    /**
     * Get metrics for alerting
     */
    public function scopeForAlerting($query, array $alertRules)
    {
        $query->where(function($q) use ($alertRules) {
            foreach ($alertRules as $rule) {
                $metricName = $rule['metric_name'];
                $operator = $rule['operator'] ?? '>';
                $threshold = $rule['threshold'];
                $timeWindow = $rule['time_window'] ?? 5; // minutes

                $q->orWhere(function($subQ) use ($metricName, $operator, $threshold, $timeWindow) {
                    $subQ->where('metric_name', $metricName)
                         ->where('recorded_at', '>=', now()->subMinutes($timeWindow))
                         ->where('metric_value', $operator, $threshold);
                });
            }
        });

        return $query;
    }

    /**
     * Get metrics for dashboard widgets
     */
    public function scopeForDashboardWidget($query, string $widgetType, array $config = [])
    {
        return match($widgetType) {
            'kpi_summary' => $this->getKpiSummaryMetrics($query, $config),
            'trend_chart' => $this->getTrendChartMetrics($query, $config),
            'comparison_table' => $this->getComparisonTableMetrics($query, $config),
            'alert_panel' => $this->getAlertPanelMetrics($query, $config),
            default => $query
        };
    }

    private function getKpiSummaryMetrics($query, array $config)
    {
        $metricNames = $config['metric_names'] ?? [];
        $timeWindow = $config['time_window'] ?? 24; // hours

        return $query->whereIn('metric_name', $metricNames)
                    ->where('recorded_at', '>=', now()->subHours($timeWindow))
                    ->orderBy('recorded_at', 'desc');
    }

    private function getTrendChartMetrics($query, array $config)
    {
        $metricName = $config['metric_name'] ?? '';
        $period = $config['period'] ?? 'day';
        $dataPoints = $config['data_points'] ?? 30;

        return $query->where('metric_name', $metricName)
                    ->where('recorded_at', '>=', now()->subDays($dataPoints))
                    ->orderBy('recorded_at', 'asc');
    }

    private function getComparisonTableMetrics($query, array $config)
    {
        $metricNames = $config['metric_names'] ?? [];
        $currentPeriod = $config['current_period'] ?? 'month';
        $previousPeriod = $config['previous_period'] ?? 'month';

        return $query->whereIn('metric_name', $metricNames)
                    ->where(function($q) use ($currentPeriod, $previousPeriod) {
                        $q->where('recorded_at', '>=', $this->getPeriodStart($currentPeriod))
                          ->orWhere(function($subQ) use ($previousPeriod, $currentPeriod) {
                              $subQ->where('recorded_at', '>=', $this->getPeriodStart($previousPeriod))
                                   ->where('recorded_at', '<', $this->getPeriodStart($currentPeriod));
                          });
                    })
                    ->orderBy('recorded_at', 'asc');
    }

    private function getAlertPanelMetrics($query, array $config)
    {
        $alertRules = $config['alert_rules'] ?? [];
        return $this->scopeForAlerting($query, $alertRules);
    }

    private function getPeriodStart(string $period): \Carbon\Carbon
    {
        return match($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth()
        };
    }
} 