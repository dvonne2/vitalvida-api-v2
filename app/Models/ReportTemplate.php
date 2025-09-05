<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'template_type',
        'config',
        'created_by',
        'is_active',
        'is_public',
        'version',
        'metadata'
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'metadata' => 'array'
    ];

    /**
     * Get active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get public templates
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Get templates by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('template_type', $type);
    }

    /**
     * Get templates created by specific user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Get templates by version
     */
    public function scopeByVersion($query, string $version)
    {
        return $query->where('version', $version);
    }

    /**
     * Get latest version of templates
     */
    public function scopeLatestVersion($query)
    {
        return $query->whereIn('id', function($subQuery) {
            $subQuery->selectRaw('MAX(id)')
                    ->from('report_templates')
                    ->groupBy('name');
        });
    }

    /**
     * Get templates for specific role
     */
    public function scopeForRole($query, string $role)
    {
        return $query->whereJsonContains('config->allowed_roles', $role);
    }

    /**
     * Get templates for dashboard
     */
    public function scopeForDashboard($query, string $dashboardType = 'executive')
    {
        return $query->whereJsonContains('config->dashboard_types', $dashboardType)
                    ->where('is_active', true);
    }

    /**
     * Get templates with specific configuration
     */
    public function scopeWithConfig($query, array $config)
    {
        foreach ($config as $key => $value) {
            $query->whereJsonContains("config->{$key}", $value);
        }
        return $query;
    }

    /**
     * Get templates for report generation
     */
    public function scopeForReportGeneration($query, string $reportType)
    {
        return $query->where('is_active', true)
                    ->whereJsonContains('config->report_types', $reportType);
    }

    /**
     * Get templates for scheduling
     */
    public function scopeForScheduling($query)
    {
        return $query->where('is_active', true)
                    ->whereJsonContains('config->schedulable', true);
    }

    /**
     * Get templates for export
     */
    public function scopeForExport($query, array $filters = [])
    {
        $query->where('is_active', true);

        if (isset($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (isset($filters['public'])) {
            $query->public();
        }

        if (isset($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        return $query->orderBy('name', 'asc');
    }

    /**
     * Get templates for analytics
     */
    public function scopeForAnalytics($query, array $params = [])
    {
        $query->where('is_active', true);

        if (isset($params['template_types'])) {
            $query->whereIn('template_type', $params['template_types']);
        }

        if (isset($params['usage_count'])) {
            $query->where('usage_count', '>=', $params['usage_count']);
        }

        return $query;
    }

    /**
     * Get templates for user preferences
     */
    public function scopeForUserPreferences($query, int $userId, string $role)
    {
        return $query->where(function($q) use ($userId, $role) {
            $q->where('created_by', $userId)
              ->orWhere('is_public', true)
              ->orWhereJsonContains('config->allowed_roles', $role);
        })
        ->where('is_active', true)
        ->orderBy('name', 'asc');
    }

    /**
     * Get templates for template management
     */
    public function scopeForTemplateManagement($query, array $filters = [])
    {
        if (isset($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
        }

        if (isset($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        if (isset($filters['visibility'])) {
            $query->where('is_public', $filters['visibility'] === 'public');
        }

        return $query->orderBy('name', 'asc');
    }

    /**
     * Get templates for version control
     */
    public function scopeForVersionControl($query, string $templateName)
    {
        return $query->where('name', $templateName)
                    ->orderBy('version', 'desc');
    }

    /**
     * Get templates for backup
     */
    public function scopeForBackup($query)
    {
        return $query->where('is_active', true)
                    ->select(['id', 'name', 'template_type', 'config', 'version', 'created_at']);
    }

    /**
     * Get templates for migration
     */
    public function scopeForMigration($query, string $fromVersion, string $toVersion)
    {
        return $query->where('version', $fromVersion)
                    ->where('is_active', true);
    }

    /**
     * Get templates for compliance
     */
    public function scopeForCompliance($query, array $complianceRules = [])
    {
        $query->where('is_active', true);

        foreach ($complianceRules as $rule) {
            $field = $rule['field'] ?? 'template_type';
            $operator = $rule['operator'] ?? '=';
            $value = $rule['value'];

            $query->where($field, $operator, $value);
        }

        return $query;
    }

    /**
     * Get templates for performance optimization
     */
    public function scopeForPerformanceOptimization($query)
    {
        return $query->where('is_active', true)
                    ->whereJsonLength('config', '>', 1000) // Large configs that might need optimization
                    ->orderBy('updated_at', 'desc');
    }

    /**
     * Get templates for cleanup
     */
    public function scopeForCleanup($query, int $daysOld = 90)
    {
        return $query->where('is_active', false)
                    ->where('updated_at', '<', now()->subDays($daysOld));
    }

    /**
     * Relationship to user who created the template
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if template is accessible by user
     */
    public function isAccessibleBy(int $userId, string $role): bool
    {
        return $this->created_by === $userId ||
               $this->is_public ||
               in_array($role, $this->getConfigValue('allowed_roles', []));
    }

    /**
     * Check if template supports specific report type
     */
    public function supportsReportType(string $reportType): bool
    {
        $supportedTypes = $this->getConfigValue('report_types', []);
        return in_array($reportType, $supportedTypes);
    }

    /**
     * Check if template is schedulable
     */
    public function isSchedulable(): bool
    {
        return $this->getConfigValue('schedulable', false);
    }

    /**
     * Get template configuration value
     */
    public function getConfigValue(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Set template configuration value
     */
    public function setConfigValue(string $key, $value): void
    {
        $config = $this->config ?? [];
        data_set($config, $key, $value);
        $this->config = $config;
    }

    /**
     * Get template sections
     */
    public function getSections(): array
    {
        return $this->getConfigValue('sections', []);
    }

    /**
     * Get template parameters
     */
    public function getParameters(): array
    {
        return $this->getConfigValue('parameters', []);
    }

    /**
     * Get template layout
     */
    public function getLayout(): array
    {
        return $this->getConfigValue('layout', []);
    }

    /**
     * Get template styling
     */
    public function getStyling(): array
    {
        return $this->getConfigValue('styling', []);
    }

    /**
     * Get template permissions
     */
    public function getPermissions(): array
    {
        return $this->getConfigValue('permissions', []);
    }

    /**
     * Check if template has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();
        return in_array($permission, $permissions);
    }

    /**
     * Get template usage statistics
     */
    public function getUsageStats(): array
    {
        return [
            'total_usage' => $this->getConfigValue('usage_count', 0),
            'last_used' => $this->getConfigValue('last_used_at'),
            'popularity_score' => $this->getConfigValue('popularity_score', 0),
            'user_rating' => $this->getConfigValue('user_rating', 0)
        ];
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $usageCount = $this->getConfigValue('usage_count', 0);
        $this->setConfigValue('usage_count', $usageCount + 1);
        $this->setConfigValue('last_used_at', now());
        $this->save();
    }

    /**
     * Update popularity score
     */
    public function updatePopularityScore(float $score): void
    {
        $this->setConfigValue('popularity_score', $score);
        $this->save();
    }

    /**
     * Update user rating
     */
    public function updateUserRating(float $rating): void
    {
        $this->setConfigValue('user_rating', $rating);
        $this->save();
    }

    /**
     * Create new version of template
     */
    public function createNewVersion(string $newVersion): self
    {
        $newTemplate = $this->replicate();
        $newTemplate->version = $newVersion;
        $newTemplate->created_at = now();
        $newTemplate->updated_at = now();
        $newTemplate->save();

        return $newTemplate;
    }

    /**
     * Clone template
     */
    public function clone(string $newName, int $newCreatorId): self
    {
        $clonedTemplate = $this->replicate();
        $clonedTemplate->name = $newName;
        $clonedTemplate->created_by = $newCreatorId;
        $clonedTemplate->is_public = false;
        $clonedTemplate->version = '1.0';
        $clonedTemplate->created_at = now();
        $clonedTemplate->updated_at = now();
        $clonedTemplate->save();

        return $clonedTemplate;
    }
} 