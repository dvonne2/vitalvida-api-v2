<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictiveModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_name',
        'model_type',
        'target_metric',
        'model_config',
        'training_data',
        'model_performance',
        'last_trained_at',
        'is_active',
        'created_by',
        'version',
        'metadata'
    ];

    protected $casts = [
        'model_config' => 'array',
        'training_data' => 'array',
        'model_performance' => 'array',
        'last_trained_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array'
    ];

    /**
     * Get active models
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get models by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('model_type', $type);
    }

    /**
     * Get models by target metric
     */
    public function scopeByTargetMetric($query, string $metric)
    {
        return $query->where('target_metric', $metric);
    }

    /**
     * Get models created by specific user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Get models by version
     */
    public function scopeByVersion($query, string $version)
    {
        return $query->where('version', $version);
    }

    /**
     * Get latest version of models
     */
    public function scopeLatestVersion($query)
    {
        return $query->whereIn('id', function($subQuery) {
            $subQuery->selectRaw('MAX(id)')
                    ->from('predictive_models')
                    ->groupBy('model_name');
        });
    }

    /**
     * Get models for specific analysis type
     */
    public function scopeForAnalysisType($query, string $analysisType)
    {
        return $query->whereJsonContains('model_config->analysis_types', $analysisType)
                    ->where('is_active', true);
    }

    /**
     * Get models for forecasting
     */
    public function scopeForForecasting($query)
    {
        return $query->where('model_type', 'forecasting')
                    ->where('is_active', true);
    }

    /**
     * Get models for classification
     */
    public function scopeForClassification($query)
    {
        return $query->where('model_type', 'classification')
                    ->where('is_active', true);
    }

    /**
     * Get models for regression
     */
    public function scopeForRegression($query)
    {
        return $query->where('model_type', 'regression')
                    ->where('is_active', true);
    }

    /**
     * Get models with good performance
     */
    public function scopeWithGoodPerformance($query, float $threshold = 0.8)
    {
        return $query->whereJsonLength('model_performance->accuracy', '>=', $threshold)
                    ->where('is_active', true);
    }

    /**
     * Get models that need retraining
     */
    public function scopeNeedsRetraining($query, int $daysThreshold = 30)
    {
        return $query->where('is_active', true)
                    ->where(function($q) use ($daysThreshold) {
                        $q->whereNull('last_trained_at')
                          ->orWhere('last_trained_at', '<', now()->subDays($daysThreshold));
                    });
    }

    /**
     * Get models for specific business area
     */
    public function scopeForBusinessArea($query, string $businessArea)
    {
        return $query->whereJsonContains('model_config->business_areas', $businessArea)
                    ->where('is_active', true);
    }

    /**
     * Get models for dashboard
     */
    public function scopeForDashboard($query, string $dashboardType = 'executive')
    {
        return $query->whereJsonContains('model_config->dashboard_types', $dashboardType)
                    ->where('is_active', true);
    }

    /**
     * Get models for export
     */
    public function scopeForExport($query, array $filters = [])
    {
        $query->where('is_active', true);

        if (isset($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (isset($filters['target_metric'])) {
            $query->byTargetMetric($filters['target_metric']);
        }

        if (isset($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        return $query->orderBy('model_name', 'asc');
    }

    /**
     * Get models for analytics
     */
    public function scopeForAnalytics($query, array $params = [])
    {
        $query->where('is_active', true);

        if (isset($params['model_types'])) {
            $query->whereIn('model_type', $params['model_types']);
        }

        if (isset($params['performance_threshold'])) {
            $query->whereJsonLength('model_performance->accuracy', '>=', $params['performance_threshold']);
        }

        return $query;
    }

    /**
     * Get models for model management
     */
    public function scopeForModelManagement($query, array $filters = [])
    {
        if (isset($filters['search'])) {
            $query->where('model_name', 'like', "%{$filters['search']}%");
        }

        if (isset($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        if (isset($filters['performance'])) {
            $query->whereJsonLength('model_performance->accuracy', '>=', $filters['performance']);
        }

        return $query->orderBy('model_name', 'asc');
    }

    /**
     * Get models for version control
     */
    public function scopeForVersionControl($query, string $modelName)
    {
        return $query->where('model_name', $modelName)
                    ->orderBy('version', 'desc');
    }

    /**
     * Get models for backup
     */
    public function scopeForBackup($query)
    {
        return $query->where('is_active', true)
                    ->select(['id', 'model_name', 'model_type', 'target_metric', 'version', 'created_at']);
    }

    /**
     * Get models for compliance
     */
    public function scopeForCompliance($query, array $complianceRules = [])
    {
        $query->where('is_active', true);

        foreach ($complianceRules as $rule) {
            $field = $rule['field'] ?? 'model_type';
            $operator = $rule['operator'] ?? '=';
            $value = $rule['value'];

            $query->where($field, $operator, $value);
        }

        return $query;
    }

    /**
     * Get models for performance monitoring
     */
    public function scopeForPerformanceMonitoring($query)
    {
        return $query->where('is_active', true)
                    ->where('last_trained_at', '>=', now()->subDays(7))
                    ->orderBy('last_trained_at', 'desc');
    }

    /**
     * Get models for cleanup
     */
    public function scopeForCleanup($query, int $daysOld = 90)
    {
        return $query->where('is_active', false)
                    ->where('updated_at', '<', now()->subDays($daysOld));
    }

    /**
     * Relationship to user who created the model
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if model is accessible by user
     */
    public function isAccessibleBy(int $userId, string $role): bool
    {
        return $this->created_by === $userId ||
               in_array($role, $this->getConfigValue('allowed_roles', []));
    }

    /**
     * Check if model supports specific analysis type
     */
    public function supportsAnalysisType(string $analysisType): bool
    {
        $supportedTypes = $this->getConfigValue('analysis_types', []);
        return in_array($analysisType, $supportedTypes);
    }

    /**
     * Check if model needs retraining
     */
    public function needsRetraining(int $daysThreshold = 30): bool
    {
        return !$this->last_trained_at ||
               $this->last_trained_at->diffInDays(now()) > $daysThreshold;
    }

    /**
     * Get model configuration value
     */
    public function getConfigValue(string $key, $default = null)
    {
        return data_get($this->model_config, $key, $default);
    }

    /**
     * Set model configuration value
     */
    public function setConfigValue(string $key, $value): void
    {
        $config = $this->model_config ?? [];
        data_set($config, $key, $value);
        $this->model_config = $config;
    }

    /**
     * Get model performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return $this->model_performance ?? [];
    }

    /**
     * Get model accuracy
     */
    public function getAccuracy(): float
    {
        return $this->getConfigValue('model_performance.accuracy', 0.0);
    }

    /**
     * Get model precision
     */
    public function getPrecision(): float
    {
        return $this->getConfigValue('model_performance.precision', 0.0);
    }

    /**
     * Get model recall
     */
    public function getRecall(): float
    {
        return $this->getConfigValue('model_performance.recall', 0.0);
    }

    /**
     * Get model F1 score
     */
    public function getF1Score(): float
    {
        return $this->getConfigValue('model_performance.f1_score', 0.0);
    }

    /**
     * Get model training data
     */
    public function getTrainingData(): array
    {
        return $this->training_data ?? [];
    }

    /**
     * Get model features
     */
    public function getFeatures(): array
    {
        return $this->getConfigValue('features', []);
    }

    /**
     * Get model hyperparameters
     */
    public function getHyperparameters(): array
    {
        return $this->getConfigValue('hyperparameters', []);
    }

    /**
     * Get model algorithm
     */
    public function getAlgorithm(): string
    {
        return $this->getConfigValue('algorithm', 'unknown');
    }

    /**
     * Get model business areas
     */
    public function getBusinessAreas(): array
    {
        return $this->getConfigValue('business_areas', []);
    }

    /**
     * Get model dashboard types
     */
    public function getDashboardTypes(): array
    {
        return $this->getConfigValue('dashboard_types', []);
    }

    /**
     * Get model permissions
     */
    public function getPermissions(): array
    {
        return $this->getConfigValue('permissions', []);
    }

    /**
     * Check if model has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();
        return in_array($permission, $permissions);
    }

    /**
     * Get model usage statistics
     */
    public function getUsageStats(): array
    {
        return [
            'total_predictions' => $this->getConfigValue('usage_stats.total_predictions', 0),
            'last_used' => $this->getConfigValue('usage_stats.last_used_at'),
            'success_rate' => $this->getConfigValue('usage_stats.success_rate', 0),
            'average_response_time' => $this->getConfigValue('usage_stats.average_response_time', 0)
        ];
    }

    /**
     * Increment prediction count
     */
    public function incrementPredictionCount(): void
    {
        $totalPredictions = $this->getConfigValue('usage_stats.total_predictions', 0);
        $this->setConfigValue('usage_stats.total_predictions', $totalPredictions + 1);
        $this->setConfigValue('usage_stats.last_used_at', now());
        $this->save();
    }

    /**
     * Update model performance
     */
    public function updatePerformance(array $performance): void
    {
        $this->model_performance = array_merge($this->model_performance ?? [], $performance);
        $this->save();
    }

    /**
     * Mark model as trained
     */
    public function markAsTrained(): void
    {
        $this->last_trained_at = now();
        $this->save();
    }

    /**
     * Create new version of model
     */
    public function createNewVersion(string $newVersion): self
    {
        $newModel = $this->replicate();
        $newModel->version = $newVersion;
        $newModel->created_at = now();
        $newModel->updated_at = now();
        $newModel->save();

        return $newModel;
    }

    /**
     * Clone model
     */
    public function clone(string $newName, int $newCreatorId): self
    {
        $clonedModel = $this->replicate();
        $clonedModel->model_name = $newName;
        $clonedModel->created_by = $newCreatorId;
        $clonedModel->is_active = false;
        $clonedModel->version = '1.0';
        $clonedModel->created_at = now();
        $clonedModel->updated_at = now();
        $clonedModel->save();

        return $clonedModel;
    }

    /**
     * Get model age in days
     */
    public function getAgeInDaysAttribute(): int
    {
        return $this->created_at ? $this->created_at->diffInDays(now()) : 0;
    }

    /**
     * Get days since last training
     */
    public function getDaysSinceLastTrainingAttribute(): int
    {
        return $this->last_trained_at ? $this->last_trained_at->diffInDays(now()) : 0;
    }
} 