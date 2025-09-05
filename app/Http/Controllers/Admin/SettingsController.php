<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\SystemLog;

class SettingsController extends Controller
{
    /**
     * Display the settings page
     */
    public function index()
    {
        $settings = Cache::remember('system_settings', 3600, function () {
            return [
                'general' => [
                    'site_name' => config('app.name', 'RBAC System'),
                    'site_description' => config('app.description', 'Role-Based Access Control System'),
                    'timezone' => config('app.timezone', 'UTC'),
                    'date_format' => config('app.date_format', 'Y-m-d'),
                    'time_format' => config('app.time_format', 'H:i:s'),
                    'maintenance_mode' => app()->isDownForMaintenance(),
                    'debug_mode' => config('app.debug', false)
                ],
                'security' => [
                    'session_timeout' => config('session.lifetime', 3600),
                    'max_login_attempts' => config('auth.max_attempts', 5),
                    'password_min_length' => config('auth.password_min_length', 8),
                    'require_2fa' => config('auth.require_2fa', false),
                    'lockout_duration' => config('auth.lockout_duration', 900),
                    'password_expiry_days' => config('auth.password_expiry_days', 90),
                    'force_password_change' => config('auth.force_password_change', false)
                ],
                'logging' => [
                    'log_retention_days' => config('logging.activity_logs_retention_days', 90),
                    'log_user_activities' => config('logging.log_user_activities', true),
                    'log_system_events' => config('logging.log_system_events', true),
                    'log_level' => config('logging.default', 'info'),
                    'log_database_queries' => config('logging.log_queries', false),
                    'log_slow_queries' => config('logging.log_slow_queries', true)
                ],
                'notifications' => [
                    'email_notifications' => config('notifications.email_enabled', true),
                    'security_alerts' => config('notifications.security_alerts', true),
                    'system_alerts' => config('notifications.system_alerts', true),
                    'dashboard_notifications' => config('notifications.dashboard_notifications', true),
                    'webhook_notifications' => config('notifications.webhook_enabled', false),
                    'webhook_url' => config('notifications.webhook_url', '')
                ],
                'performance' => [
                    'cache_enabled' => config('cache.default') !== 'null',
                    'cache_ttl' => config('cache.ttl', 3600),
                    'query_cache' => config('database.query_cache', true),
                    'asset_minification' => config('app.asset_minification', true),
                    'gzip_compression' => config('app.gzip_compression', true)
                ],
                'backup' => [
                    'auto_backup_enabled' => config('backup.auto_backup', true),
                    'backup_frequency' => config('backup.frequency', 'daily'),
                    'backup_retention_days' => config('backup.retention_days', 30),
                    'backup_storage' => config('backup.storage', 'local'),
                    'backup_encryption' => config('backup.encryption', false)
                ]
            ];
        });

        return view('admin.settings', compact('settings'));
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'general.site_name' => 'sometimes|string|max:255',
            'general.site_description' => 'sometimes|string|max:500',
            'general.timezone' => 'sometimes|string|timezone',
            'general.date_format' => 'sometimes|string|max:20',
            'general.time_format' => 'sometimes|string|max:20',
            'security.session_timeout' => 'sometimes|integer|min:300|max:86400',
            'security.max_login_attempts' => 'sometimes|integer|min:1|max:20',
            'security.password_min_length' => 'sometimes|integer|min:6|max:50',
            'security.require_2fa' => 'sometimes|boolean',
            'security.lockout_duration' => 'sometimes|integer|min:60|max:3600',
            'security.password_expiry_days' => 'sometimes|integer|min:0|max:365',
            'security.force_password_change' => 'sometimes|boolean',
            'logging.log_retention_days' => 'sometimes|integer|min:1|max:365',
            'logging.log_user_activities' => 'sometimes|boolean',
            'logging.log_system_events' => 'sometimes|boolean',
            'logging.log_level' => 'sometimes|string|in:debug,info,warning,error,critical',
            'logging.log_database_queries' => 'sometimes|boolean',
            'logging.log_slow_queries' => 'sometimes|boolean',
            'notifications.email_notifications' => 'sometimes|boolean',
            'notifications.security_alerts' => 'sometimes|boolean',
            'notifications.system_alerts' => 'sometimes|boolean',
            'notifications.dashboard_notifications' => 'sometimes|boolean',
            'notifications.webhook_notifications' => 'sometimes|boolean',
            'notifications.webhook_url' => 'sometimes|nullable|url',
            'performance.cache_enabled' => 'sometimes|boolean',
            'performance.cache_ttl' => 'sometimes|integer|min:60|max:86400',
            'performance.query_cache' => 'sometimes|boolean',
            'performance.asset_minification' => 'sometimes|boolean',
            'performance.gzip_compression' => 'sometimes|boolean',
            'backup.auto_backup_enabled' => 'sometimes|boolean',
            'backup.backup_frequency' => 'sometimes|string|in:daily,weekly,monthly',
            'backup.backup_retention_days' => 'sometimes|integer|min:1|max:365',
            'backup.backup_storage' => 'sometimes|string|in:local,s3',
            'backup.backup_encryption' => 'sometimes|boolean'
        ]);

        try {
            $settings = $request->all();
            $updatedSettings = [];

            // Update configuration (in a real app, you'd use a settings table)
            foreach ($settings as $category => $categorySettings) {
                foreach ($categorySettings as $key => $value) {
                    $configKey = $this->getConfigKey($category, $key);
                    if ($configKey) {
                        config([$configKey => $value]);
                        $updatedSettings[$configKey] = $value;
                    }
                }
            }

            // Clear cache
            Cache::forget('system_settings');

            // Log the settings update
            SystemLog::create([
                'level' => 'info',
                'message' => 'System settings updated',
                'context' => [
                    'updated_by' => auth()->user()->username,
                    'updated_settings' => $updatedSettings
                ],
                'source' => 'settings_controller'
            ]);

            return redirect()->route('admin.settings')
                ->with('success', 'Settings updated successfully');

        } catch (\Exception $e) {
            SystemLog::create([
                'level' => 'error',
                'message' => 'Failed to update system settings',
                'context' => [
                    'error' => $e->getMessage(),
                    'updated_by' => auth()->user()->username
                ],
                'source' => 'settings_controller'
            ]);

            return redirect()->route('admin.settings')
                ->with('error', 'Failed to update settings: ' . $e->getMessage());
        }
    }

    /**
     * Export settings backup
     */
    public function backup()
    {
        try {
            $settings = Cache::remember('system_settings', 3600, function () {
                return $this->getAllSettings();
            });

            $backupData = [
                'exported_at' => now()->toISOString(),
                'exported_by' => auth()->user()->username,
                'settings' => $settings
            ];

            $filename = 'settings_backup_' . now()->format('Y-m-d_H-i-s') . '.json';
            $filePath = storage_path('app/backups/settings/' . $filename);

            // Ensure directory exists
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }

            file_put_contents($filePath, json_encode($backupData, JSON_PRETTY_PRINT));

            return response()->download($filePath, $filename)->deleteFileAfterSend();

        } catch (\Exception $e) {
            return redirect()->route('admin.settings')
                ->with('error', 'Failed to create settings backup: ' . $e->getMessage());
        }
    }

    /**
     * Get configuration key for a setting
     */
    private function getConfigKey(string $category, string $key): ?string
    {
        $configMap = [
            'general' => [
                'site_name' => 'app.name',
                'site_description' => 'app.description',
                'timezone' => 'app.timezone',
                'date_format' => 'app.date_format',
                'time_format' => 'app.time_format'
            ],
            'security' => [
                'session_timeout' => 'session.lifetime',
                'max_login_attempts' => 'auth.max_attempts',
                'password_min_length' => 'auth.password_min_length',
                'require_2fa' => 'auth.require_2fa',
                'lockout_duration' => 'auth.lockout_duration',
                'password_expiry_days' => 'auth.password_expiry_days',
                'force_password_change' => 'auth.force_password_change'
            ],
            'logging' => [
                'log_retention_days' => 'logging.activity_logs_retention_days',
                'log_user_activities' => 'logging.log_user_activities',
                'log_system_events' => 'logging.log_system_events',
                'log_level' => 'logging.default',
                'log_database_queries' => 'logging.log_queries',
                'log_slow_queries' => 'logging.log_slow_queries'
            ],
            'notifications' => [
                'email_notifications' => 'notifications.email_enabled',
                'security_alerts' => 'notifications.security_alerts',
                'system_alerts' => 'notifications.system_alerts',
                'dashboard_notifications' => 'notifications.dashboard_notifications',
                'webhook_notifications' => 'notifications.webhook_enabled',
                'webhook_url' => 'notifications.webhook_url'
            ],
            'performance' => [
                'cache_enabled' => 'cache.enabled',
                'cache_ttl' => 'cache.ttl',
                'query_cache' => 'database.query_cache',
                'asset_minification' => 'app.asset_minification',
                'gzip_compression' => 'app.gzip_compression'
            ],
            'backup' => [
                'auto_backup_enabled' => 'backup.auto_backup',
                'backup_frequency' => 'backup.frequency',
                'backup_retention_days' => 'backup.retention_days',
                'backup_storage' => 'backup.storage',
                'backup_encryption' => 'backup.encryption'
            ]
        ];

        return $configMap[$category][$key] ?? null;
    }

    /**
     * Get all settings
     */
    private function getAllSettings(): array
    {
        return [
            'general' => [
                'site_name' => config('app.name'),
                'site_description' => config('app.description'),
                'timezone' => config('app.timezone'),
                'date_format' => config('app.date_format'),
                'time_format' => config('app.time_format'),
                'maintenance_mode' => app()->isDownForMaintenance(),
                'debug_mode' => config('app.debug')
            ],
            'security' => [
                'session_timeout' => config('session.lifetime'),
                'max_login_attempts' => config('auth.max_attempts'),
                'password_min_length' => config('auth.password_min_length'),
                'require_2fa' => config('auth.require_2fa'),
                'lockout_duration' => config('auth.lockout_duration'),
                'password_expiry_days' => config('auth.password_expiry_days'),
                'force_password_change' => config('auth.force_password_change')
            ],
            'logging' => [
                'log_retention_days' => config('logging.activity_logs_retention_days'),
                'log_user_activities' => config('logging.log_user_activities'),
                'log_system_events' => config('logging.log_system_events'),
                'log_level' => config('logging.default'),
                'log_database_queries' => config('logging.log_queries'),
                'log_slow_queries' => config('logging.log_slow_queries')
            ],
            'notifications' => [
                'email_notifications' => config('notifications.email_enabled'),
                'security_alerts' => config('notifications.security_alerts'),
                'system_alerts' => config('notifications.system_alerts'),
                'dashboard_notifications' => config('notifications.dashboard_notifications'),
                'webhook_notifications' => config('notifications.webhook_enabled'),
                'webhook_url' => config('notifications.webhook_url')
            ],
            'performance' => [
                'cache_enabled' => config('cache.default') !== 'null',
                'cache_ttl' => config('cache.ttl'),
                'query_cache' => config('database.query_cache'),
                'asset_minification' => config('app.asset_minification'),
                'gzip_compression' => config('app.gzip_compression')
            ],
            'backup' => [
                'auto_backup_enabled' => config('backup.auto_backup'),
                'backup_frequency' => config('backup.frequency'),
                'backup_retention_days' => config('backup.retention_days'),
                'backup_storage' => config('backup.storage'),
                'backup_encryption' => config('backup.encryption')
            ]
        ];
    }
} 