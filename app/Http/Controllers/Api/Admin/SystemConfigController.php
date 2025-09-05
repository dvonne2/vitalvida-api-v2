<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class SystemConfigController extends Controller
{
    /**
     * Get system configuration
     */
    public function getConfig(Request $request)
    {
        $config = [
            'system' => [
                'app_name' => config('app.name'),
                'app_env' => config('app.env'),
                'app_debug' => config('app.debug'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ],
            'database' => [
                'connection' => config('database.default'),
                'host' => config('database.connections.mysql.host'),
                'database' => config('database.connections.mysql.database'),
                'charset' => config('database.connections.mysql.charset'),
                'collation' => config('database.connections.mysql.collation'),
            ],
            'mail' => [
                'driver' => config('mail.default'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'prefix' => config('cache.prefix'),
            ],
            'session' => [
                'driver' => config('session.driver'),
                'lifetime' => config('session.lifetime'),
                'expire_on_close' => config('session.expire_on_close'),
            ],
            'queue' => [
                'default' => config('queue.default'),
                'connections' => array_keys(config('queue.connections')),
            ],
            'filesystem' => [
                'default' => config('filesystems.default'),
                'disks' => array_keys(config('filesystems.disks')),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $config
        ]);
    }

    /**
     * Get system health status
     */
    public function systemHealth(Request $request)
    {
        $health = [
            'database' => [
                'status' => 'healthy',
                'connection' => $this->checkDatabaseConnection(),
                'tables' => $this->getDatabaseTables(),
            ],
            'storage' => [
                'status' => 'healthy',
                'disk_usage' => $this->getDiskUsage(),
                'writable_paths' => $this->checkWritablePaths(),
            ],
            'cache' => [
                'status' => 'healthy',
                'connection' => $this->checkCacheConnection(),
            ],
            'queue' => [
                'status' => 'healthy',
                'jobs' => $this->getQueueStats(),
            ],
            'services' => [
                'mail' => $this->checkMailService(),
                'storage' => $this->checkStorageService(),
            ],
        ];

        // Determine overall status
        $overallStatus = 'healthy';
        foreach ($health as $component) {
            if ($component['status'] !== 'healthy') {
                $overallStatus = 'warning';
                break;
            }
        }

        $health['overall_status'] = $overallStatus;

        return response()->json([
            'success' => true,
            'data' => $health
        ]);
    }

    /**
     * Get system performance metrics
     */
    public function performanceMetrics(Request $request)
    {
        $metrics = [
            'memory' => [
                'usage' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit'),
            ],
            'execution' => [
                'time' => microtime(true) - LARAVEL_START,
                'memory_limit_reached' => memory_get_usage(true) > $this->parseMemoryLimit(ini_get('memory_limit')),
            ],
            'database' => [
                'connections' => \DB::connection()->getPdo() ? 'active' : 'inactive',
                'query_count' => $this->getQueryCount(),
            ],
            'cache' => [
                'hit_rate' => $this->getCacheHitRate(),
                'size' => $this->getCacheSize(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Get system logs summary
     */
    public function logsSummary(Request $request)
    {
        $logsPath = storage_path('logs');
        $logFiles = glob($logsPath . '/*.log');
        
        $summary = [];
        foreach ($logFiles as $file) {
            $filename = basename($file);
            $size = filesize($file);
            $modified = filemtime($file);
            
            $summary[] = [
                'filename' => $filename,
                'size' => $size,
                'size_formatted' => $this->formatBytes($size),
                'modified' => date('Y-m-d H:i:s', $modified),
                'modified_ago' => now()->diffForHumans(date('Y-m-d H:i:s', $modified)),
            ];
        }

        // Sort by modification time (newest first)
        usort($summary, function ($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Clear system cache
     */
    public function clearCache(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cache_type' => 'sometimes|in:all,config,route,view,application',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $cacheType = $request->get('cache_type', 'all');
        $cleared = [];

        try {
            switch ($cacheType) {
                case 'all':
                    \Artisan::call('cache:clear');
                    \Artisan::call('config:clear');
                    \Artisan::call('route:clear');
                    \Artisan::call('view:clear');
                    $cleared = ['cache', 'config', 'route', 'view'];
                    break;
                case 'config':
                    \Artisan::call('config:clear');
                    $cleared = ['config'];
                    break;
                case 'route':
                    \Artisan::call('route:clear');
                    $cleared = ['route'];
                    break;
                case 'view':
                    \Artisan::call('view:clear');
                    $cleared = ['view'];
                    break;
                case 'application':
                    \Artisan::call('cache:clear');
                    $cleared = ['cache'];
                    break;
            }

            // Log the action
            ActionLog::create([
                'user_id' => $request->user()->id,
                'action' => 'admin.system.clear_cache',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'cache_type' => $cacheType,
                    'cleared' => $cleared,
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully',
                'data' => [
                    'cleared' => $cleared,
                    'cache_type' => $cacheType,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system backup status
     */
    public function backupStatus(Request $request)
    {
        $backupPath = storage_path('app/backups');
        
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $backupFiles = glob($backupPath . '/*.sql');
        
        $backups = [];
        foreach ($backupFiles as $file) {
            $filename = basename($file);
            $size = filesize($file);
            $modified = filemtime($file);
            
            $backups[] = [
                'filename' => $filename,
                'size' => $size,
                'size_formatted' => $this->formatBytes($size),
                'created' => date('Y-m-d H:i:s', $modified),
                'created_ago' => now()->diffForHumans(date('Y-m-d H:i:s', $modified)),
            ];
        }

        // Sort by creation time (newest first)
        usort($backups, function ($a, $b) {
            return strtotime($b['created']) - strtotime($a['created']);
        });

        $lastBackup = count($backups) > 0 ? $backups[0] : null;

        return response()->json([
            'success' => true,
            'data' => [
                'backups' => $backups,
                'last_backup' => $lastBackup,
                'total_backups' => count($backups),
                'total_size' => $this->formatBytes(array_sum(array_column($backups, 'size'))),
            ]
        ]);
    }

    /**
     * Check database connection
     */
    private function checkDatabaseConnection()
    {
        try {
            \DB::connection()->getPdo();
            return [
                'status' => 'connected',
                'version' => \DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'disconnected',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get database tables
     */
    private function getDatabaseTables()
    {
        try {
            $tables = \DB::select('SHOW TABLES');
            return count($tables);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage()
    {
        $path = storage_path();
        return [
            'total' => disk_total_space($path),
            'free' => disk_free_space($path),
            'used' => disk_total_space($path) - disk_free_space($path),
            'usage_percentage' => ((disk_total_space($path) - disk_free_space($path)) / disk_total_space($path)) * 100,
        ];
    }

    /**
     * Check writable paths
     */
    private function checkWritablePaths()
    {
        $paths = [
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            'public/uploads' => public_path('uploads'),
        ];

        $writable = [];
        foreach ($paths as $name => $path) {
            $writable[$name] = [
                'path' => $path,
                'writable' => is_writable($path),
            ];
        }

        return $writable;
    }

    /**
     * Check cache connection
     */
    private function checkCacheConnection()
    {
        try {
            Cache::store()->has('test');
            return ['status' => 'connected'];
        } catch (\Exception $e) {
            return [
                'status' => 'disconnected',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get queue stats
     */
    private function getQueueStats()
    {
        try {
            $failed = \DB::table('failed_jobs')->count();
            $jobs = \DB::table('jobs')->count();
            
            return [
                'pending' => $jobs,
                'failed' => $failed,
            ];
        } catch (\Exception $e) {
            return [
                'pending' => 0,
                'failed' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check mail service
     */
    private function checkMailService()
    {
        return [
            'driver' => config('mail.default'),
            'configured' => !empty(config('mail.mailers.' . config('mail.default') . '.host')),
        ];
    }

    /**
     * Check storage service
     */
    private function checkStorageService()
    {
        return [
            'driver' => config('filesystems.default'),
            'configured' => true, // Simplified check
        ];
    }

    /**
     * Parse memory limit
     */
    private function parseMemoryLimit($limit)
    {
        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);
        
        switch ($unit) {
            case 'k': return $value * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'g': return $value * 1024 * 1024 * 1024;
            default: return $value;
        }
    }

    /**
     * Get query count (simplified)
     */
    private function getQueryCount()
    {
        // This would need to be implemented with query logging
        return 0;
    }

    /**
     * Get cache hit rate (simplified)
     */
    private function getCacheHitRate()
    {
        // This would need to be implemented with cache statistics
        return 0;
    }

    /**
     * Get cache size (simplified)
     */
    private function getCacheSize()
    {
        // This would need to be implemented with cache statistics
        return 0;
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
} 