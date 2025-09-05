<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsEngineService;
use App\Models\AnalyticsMetric;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    protected $analyticsService;

    public function __construct(AnalyticsEngineService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Process real-time analytics
     */
    public function processAnalytics(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'category' => 'required|string|in:financial,operational,compliance,performance',
                'metrics' => 'required|array',
                'timestamp' => 'nullable|date',
                'source' => 'nullable|string'
            ]);

            $result = $this->analyticsService->processRealTimeAnalytics(
                $data['category'],
                $data['metrics'],
                $data['timestamp'] ?? now(),
                $data['source'] ?? 'api'
            );

            return response()->json([
                'success' => true,
                'message' => 'Analytics processed successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Analytics processing failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get analytics metrics with filtering
     */
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $query = AnalyticsMetric::query();

            // Apply filters
            if ($request->category) {
                $query->where('category', $request->category);
            }

            if ($request->metric_type) {
                $query->where('metric_type', $request->metric_type);
            }

            if ($request->date_from) {
                $query->where('timestamp', '>=', Carbon::parse($request->date_from));
            }

            if ($request->date_to) {
                $query->where('timestamp', '<=', Carbon::parse($request->date_to));
            }

            if ($request->source) {
                $query->where('source', $request->source);
            }

            // Apply aggregation if requested
            if ($request->aggregate) {
                $query->selectRaw('
                    category,
                    metric_type,
                    ' . $request->aggregate . '(value) as aggregated_value,
                    COUNT(*) as data_points,
                    MIN(timestamp) as start_date,
                    MAX(timestamp) as end_date
                ')->groupBy('category', 'metric_type');
            }

            $metrics = $query->orderBy('timestamp', 'desc')
                ->paginate($request->per_page ?? 50);

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve analytics metrics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific metric details
     */
    public function getMetricDetails(AnalyticsMetric $metric): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $metric->load('relatedData')
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve metric details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve metric details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get financial analytics
     */
    public function getFinancialAnalytics(Request $request): JsonResponse
    {
        try {
            $cacheKey = 'financial_analytics_' . ($request->period ?? 'current');
            
            $analytics = Cache::remember($cacheKey, 300, function () use ($request) {
                return $this->analyticsService->getFinancialAnalytics($request->period ?? 'current');
            });

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve financial analytics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve financial analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get operational analytics
     */
    public function getOperationalAnalytics(Request $request): JsonResponse
    {
        try {
            $cacheKey = 'operational_analytics_' . ($request->period ?? 'current');
            
            $analytics = Cache::remember($cacheKey, 300, function () use ($request) {
                return $this->analyticsService->getOperationalAnalytics($request->period ?? 'current');
            });

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve operational analytics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve operational analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get compliance analytics
     */
    public function getComplianceAnalytics(Request $request): JsonResponse
    {
        try {
            $cacheKey = 'compliance_analytics_' . ($request->period ?? 'current');
            
            $analytics = Cache::remember($cacheKey, 300, function () use ($request) {
                return $this->analyticsService->getComplianceAnalytics($request->period ?? 'current');
            });

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve compliance analytics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve compliance analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance analytics
     */
    public function getPerformanceAnalytics(Request $request): JsonResponse
    {
        try {
            $cacheKey = 'performance_analytics_' . ($request->period ?? 'current');
            
            $analytics = Cache::remember($cacheKey, 300, function () use ($request) {
                return $this->analyticsService->getPerformanceAnalytics($request->period ?? 'current');
            });

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve performance analytics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve performance analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get time-series data
     */
    public function getTimeSeriesData(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'metric_type' => 'required|string',
                'category' => 'required|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'interval' => 'nullable|string|in:hourly,daily,weekly,monthly'
            ]);

            $timeSeriesData = $this->analyticsService->getTimeSeriesData(
                $data['metric_type'],
                $data['category'],
                $data['start_date'],
                $data['end_date'],
                $data['interval'] ?? 'daily'
            );

            return response()->json([
                'success' => true,
                'data' => $timeSeriesData
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve time-series data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve time-series data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh analytics cache
     */
    public function refreshCache(Request $request): JsonResponse
    {
        try {
            $category = $request->input('category', 'all');
            
            $this->analyticsService->refreshCache($category);

            return response()->json([
                'success' => true,
                'message' => 'Analytics cache refreshed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to refresh analytics cache: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear analytics cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->analyticsService->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Analytics cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear analytics cache: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 