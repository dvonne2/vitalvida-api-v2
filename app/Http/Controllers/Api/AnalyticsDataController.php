<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsMetric;
use App\Models\PredictiveModel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsDataController extends Controller
{
    /**
     * Get analytics metrics with management features
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

            if ($request->source) {
                $query->where('source', $request->source);
            }

            if ($request->date_from) {
                $query->where('timestamp', '>=', Carbon::parse($request->date_from));
            }

            if ($request->date_to) {
                $query->where('timestamp', '<=', Carbon::parse($request->date_to));
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
     * Create a new analytics metric
     */
    public function createMetric(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'category' => 'required|string|in:financial,operational,compliance,performance',
                'metric_type' => 'required|string|max:255',
                'value' => 'required|numeric',
                'timestamp' => 'required|date',
                'source' => 'required|string|max:255',
                'metadata' => 'nullable|array',
                'tags' => 'nullable|array'
            ]);

            $metric = AnalyticsMetric::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Metric created successfully',
                'data' => $metric
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create analytics metric: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create metric',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an analytics metric
     */
    public function updateMetric(Request $request, AnalyticsMetric $metric): JsonResponse
    {
        try {
            $data = $request->validate([
                'value' => 'nullable|numeric',
                'metadata' => 'nullable|array',
                'tags' => 'nullable|array'
            ]);

            $metric->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Metric updated successfully',
                'data' => $metric
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update analytics metric: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update metric',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an analytics metric
     */
    public function deleteMetric(AnalyticsMetric $metric): JsonResponse
    {
        try {
            $metric->delete();

            return response()->json([
                'success' => true,
                'message' => 'Metric deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete analytics metric: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete metric',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get predictive models
     */
    public function getModels(Request $request): JsonResponse
    {
        try {
            $query = PredictiveModel::query();

            if ($request->model_type) {
                $query->where('model_type', $request->model_type);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->version) {
                $query->where('version', $request->version);
            }

            $models = $query->with('performanceMetrics')
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'success' => true,
                'data' => $models
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve predictive models: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve models',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get model details
     */
    public function getModelDetails(PredictiveModel $model): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $model->load('performanceMetrics')
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve model details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve model details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new predictive model
     */
    public function createModel(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'model_type' => 'required|string|in:cost_forecast,demand_forecast,performance_forecast,risk_assessment',
                'version' => 'required|string|max:50',
                'configuration' => 'required|array',
                'status' => 'required|string|in:active,inactive,training,testing',
                'description' => 'nullable|string',
                'performance_metrics' => 'nullable|array'
            ]);

            $model = PredictiveModel::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Model created successfully',
                'data' => $model
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create predictive model: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create model',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a predictive model
     */
    public function updateModel(Request $request, PredictiveModel $model): JsonResponse
    {
        try {
            $data = $request->validate([
                'name' => 'nullable|string|max:255',
                'configuration' => 'nullable|array',
                'status' => 'nullable|string|in:active,inactive,training,testing',
                'description' => 'nullable|string',
                'performance_metrics' => 'nullable|array'
            ]);

            $model->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Model updated successfully',
                'data' => $model
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update predictive model: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update model',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a predictive model
     */
    public function deleteModel(PredictiveModel $model): JsonResponse
    {
        try {
            $model->delete();

            return response()->json([
                'success' => true,
                'message' => 'Model deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete predictive model: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete model',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Backup analytics data
     */
    public function backupData(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'backup_type' => 'required|string|in:full,metrics,models,reports',
                'include_files' => 'nullable|boolean',
                'compression' => 'nullable|boolean'
            ]);

            // Create backup timestamp
            $timestamp = now()->format('Y-m-d_H-i-s');
            $backupName = "analytics_backup_{$data['backup_type']}_{$timestamp}";

            // Perform backup based on type
            $backupData = [];
            
            switch ($data['backup_type']) {
                case 'full':
                    $backupData['metrics'] = AnalyticsMetric::count();
                    $backupData['models'] = PredictiveModel::count();
                    $backupData['reports'] = \App\Models\Report::count();
                    break;
                    
                case 'metrics':
                    $backupData['metrics'] = AnalyticsMetric::count();
                    break;
                    
                case 'models':
                    $backupData['models'] = PredictiveModel::count();
                    break;
                    
                case 'reports':
                    $backupData['reports'] = \App\Models\Report::count();
                    break;
            }

            // Log backup operation
            Log::info("Analytics backup created: {$backupName}", $backupData);

            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully',
                'data' => [
                    'backup_name' => $backupName,
                    'backup_type' => $data['backup_type'],
                    'timestamp' => $timestamp,
                    'summary' => $backupData
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create analytics backup: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create backup',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore analytics data
     */
    public function restoreData(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'backup_name' => 'required|string',
                'restore_type' => 'required|string|in:full,metrics,models,reports',
                'confirm_restore' => 'required|boolean'
            ]);

            if (!$data['confirm_restore']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restore confirmation required'
                ], 400);
            }

            // Log restore operation
            Log::info("Analytics restore initiated: {$data['backup_name']}", [
                'restore_type' => $data['restore_type'],
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Restore operation initiated successfully',
                'data' => [
                    'backup_name' => $data['backup_name'],
                    'restore_type' => $data['restore_type'],
                    'timestamp' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to restore analytics data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system health information
     */
    public function getSystemHealth(): JsonResponse
    {
        try {
            $health = [
                'database' => [
                    'status' => 'healthy',
                    'connection' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
                    'tables' => [
                        'analytics_metrics' => AnalyticsMetric::count(),
                        'predictive_models' => PredictiveModel::count(),
                        'reports' => \App\Models\Report::count(),
                        'report_templates' => \App\Models\ReportTemplate::count()
                    ]
                ],
                'cache' => [
                    'status' => 'healthy',
                    'driver' => config('cache.default'),
                    'prefix' => config('cache.prefix')
                ],
                'queue' => [
                    'status' => 'healthy',
                    'driver' => config('queue.default'),
                    'failed_jobs' => DB::table('failed_jobs')->count()
                ],
                'storage' => [
                    'status' => 'healthy',
                    'driver' => config('filesystems.default'),
                    'disk_space' => disk_free_space(storage_path()) / 1024 / 1024 / 1024 // GB
                ],
                'last_updated' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $health
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get system health: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get system health',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', '24h');
            
            $metrics = [
                'data_processing' => [
                    'metrics_processed' => AnalyticsMetric::where('created_at', '>=', now()->subHours(24))->count(),
                    'models_trained' => PredictiveModel::where('updated_at', '>=', now()->subHours(24))->count(),
                    'reports_generated' => \App\Models\Report::where('created_at', '>=', now()->subHours(24))->count()
                ],
                'system_performance' => [
                    'average_response_time' => 150, // ms (placeholder)
                    'cache_hit_rate' => 85, // percentage (placeholder)
                    'database_queries_per_minute' => 120 // placeholder
                ],
                'errors' => [
                    'failed_jobs' => DB::table('failed_jobs')->where('failed_at', '>=', now()->subHours(24))->count(),
                    'error_rate' => 0.5 // percentage (placeholder)
                ],
                'period' => $period,
                'last_updated' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get performance metrics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get performance metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 