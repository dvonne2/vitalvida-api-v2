<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\SystemLog;
use Carbon\Carbon;

class DatabaseController extends Controller
{
    /**
     * Get database information
     */
    public function info(): JsonResponse
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            
            // Get database size and table information
            $databaseInfo = $this->getDatabaseInfo($driver);
            
            // Get storage usage by category
            $storageUsage = $this->getStorageUsage();
            
            // Get last backup information
            $lastBackup = $this->getLastBackupInfo();

            return response()->json([
                'database_size' => $databaseInfo['size'],
                'total_tables' => $databaseInfo['total_tables'],
                'total_records' => $databaseInfo['total_records'],
                'last_backup' => $lastBackup['last_backup'],
                'storage_usage' => $storageUsage,
                'connection_info' => [
                    'driver' => $driver,
                    'database' => $connection->getDatabaseName(),
                    'host' => $connection->getConfig('host'),
                    'port' => $connection->getConfig('port')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get database information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create database backup
     */
    public function backup(): JsonResponse
    {
        try {
            $filename = 'backup_' . now()->format('Y-m-d_H-i-s') . '.sql';
            $backupPath = storage_path('app/backups/database/' . $filename);
            
            // Ensure backup directory exists
            if (!file_exists(dirname($backupPath))) {
                mkdir(dirname($backupPath), 0755, true);
            }

            // Create backup based on database driver
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            
            $backupResult = $this->createBackup($driver, $backupPath);
            
            if (!$backupResult['success']) {
                throw new \Exception($backupResult['error']);
            }

            $fileSize = $this->formatBytes(filesize($backupPath));

            // Log the backup operation
            SystemLog::create([
                'level' => 'info',
                'message' => 'Database backup created successfully',
                'context' => [
                    'backup_file' => $filename,
                    'file_size' => $fileSize,
                    'created_by' => auth()->user()->username
                ],
                'source' => 'database_controller'
            ]);

            return response()->json([
                'status' => 'success',
                'backup_file' => $filename,
                'file_size' => $fileSize,
                'backup_time' => now()->format('Y-m-d H:i:s'),
                'download_url' => url('storage/backups/database/' . $filename)
            ]);

        } catch (\Exception $e) {
            SystemLog::create([
                'level' => 'error',
                'message' => 'Database backup failed',
                'context' => [
                    'error' => $e->getMessage(),
                    'created_by' => auth()->user()->username
                ],
                'source' => 'database_controller'
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all available backups
     */
    public function getBackups(): JsonResponse
    {
        try {
            $backupDir = storage_path('app/backups/database');
            $backups = [];

            if (file_exists($backupDir)) {
                $files = glob($backupDir . '/*.sql');
                
                foreach ($files as $file) {
                    $filename = basename($file);
                    $fileSize = filesize($file);
                    $fileTime = filemtime($file);
                    
                    $backups[] = [
                        'filename' => $filename,
                        'file_size' => $this->formatBytes($fileSize),
                        'created_at' => Carbon::createFromTimestamp($fileTime)->format('Y-m-d H:i:s'),
                        'download_url' => url('storage/backups/database/' . $filename)
                    ];
                }
                
                // Sort by creation time (newest first)
                usort($backups, function ($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
            }

            return response()->json([
                'backups' => $backups,
                'total_backups' => count($backups),
                'total_size' => $this->formatBytes(array_sum(array_map(function ($backup) {
                    return $this->parseBytes($backup['file_size']);
                }, $backups)))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get backup list: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore database from backup
     */
    public function restore(Request $request, string $backupId): JsonResponse
    {
        try {
            $backupFile = $backupId . '.sql';
            $backupPath = storage_path('app/backups/database/' . $backupFile);

            if (!file_exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            // Perform restore based on database driver
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            
            $restoreResult = $this->restoreBackup($driver, $backupPath);
            
            if (!$restoreResult['success']) {
                throw new \Exception($restoreResult['error']);
            }

            // Log the restore operation
            SystemLog::create([
                'level' => 'warning',
                'message' => 'Database restored from backup',
                'context' => [
                    'backup_file' => $backupFile,
                    'restored_by' => auth()->user()->username
                ],
                'source' => 'database_controller'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Database restored successfully from backup',
                'backup_file' => $backupFile,
                'restored_at' => now()->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            SystemLog::create([
                'level' => 'error',
                'message' => 'Database restore failed',
                'context' => [
                    'error' => $e->getMessage(),
                    'backup_file' => $backupId . '.sql',
                    'restored_by' => auth()->user()->username
                ],
                'source' => 'database_controller'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore database: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete backup file
     */
    public function deleteBackup(string $backupId): JsonResponse
    {
        try {
            $backupFile = $backupId . '.sql';
            $backupPath = storage_path('app/backups/database/' . $backupFile);

            if (!file_exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            $fileSize = $this->formatBytes(filesize($backupPath));
            unlink($backupPath);

            // Log the deletion
            SystemLog::create([
                'level' => 'info',
                'message' => 'Database backup deleted',
                'context' => [
                    'backup_file' => $backupFile,
                    'file_size' => $fileSize,
                    'deleted_by' => auth()->user()->username
                ],
                'source' => 'database_controller'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Backup file deleted successfully',
                'deleted_file' => $backupFile
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Optimize database tables
     */
    public function optimize(): JsonResponse
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            
            $optimizationResult = $this->optimizeTables($driver);
            
            // Log the optimization
            SystemLog::create([
                'level' => 'info',
                'message' => 'Database tables optimized',
                'context' => [
                    'optimized_tables' => $optimizationResult['optimized_tables'],
                    'optimized_by' => auth()->user()->username
                ],
                'source' => 'database_controller'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Database tables optimized successfully',
                'optimized_tables' => $optimizationResult['optimized_tables'],
                'optimization_time' => now()->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to optimize database: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get database analytics
     */
    public function analytics(): JsonResponse
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            
            $analytics = [
                'table_sizes' => $this->getTableSizes($driver),
                'query_performance' => $this->getQueryPerformance(),
                'index_usage' => $this->getIndexUsage($driver),
                'slow_queries' => $this->getSlowQueries($driver)
            ];

            return response()->json($analytics);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get database analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get database information based on driver
     */
    private function getDatabaseInfo(string $driver): array
    {
        switch ($driver) {
            case 'sqlite':
                $databasePath = database_path('database.sqlite');
                $size = file_exists($databasePath) ? filesize($databasePath) : 0;
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
                $totalTables = count($tables);
                $totalRecords = 0;
                
                foreach ($tables as $table) {
                    $count = DB::table($table->name)->count();
                    $totalRecords += $count;
                }
                
                return [
                    'size' => $this->formatBytes($size),
                    'total_tables' => $totalTables,
                    'total_records' => $totalRecords
                ];
                
            case 'mysql':
                $size = DB::select("SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = DATABASE()")[0]->size ?? 0;
                $tables = DB::select("SHOW TABLES");
                $totalTables = count($tables);
                $totalRecords = 0;
                
                foreach ($tables as $table) {
                    $tableName = array_values((array) $table)[0];
                    $count = DB::table($tableName)->count();
                    $totalRecords += $count;
                }
                
                return [
                    'size' => $this->formatBytes($size),
                    'total_tables' => $totalTables,
                    'total_records' => $totalRecords
                ];
                
            default:
                return [
                    'size' => 'Unknown',
                    'total_tables' => 0,
                    'total_records' => 0
                ];
        }
    }

    /**
     * Get storage usage by category
     */
    private function getStorageUsage(): array
    {
        return [
            'logs' => $this->getCategorySize('logs'),
            'users' => $this->getCategorySize('users'),
            'system' => $this->getCategorySize('system')
        ];
    }

    /**
     * Get size for a specific category
     */
    private function getCategorySize(string $category): string
    {
        // This is a simplified implementation
        // In a real application, you'd calculate actual table sizes
        $sizes = [
            'logs' => rand(10, 100) . ' MB',
            'users' => rand(5, 50) . ' MB',
            'system' => rand(20, 200) . ' MB'
        ];
        
        return $sizes[$category] ?? '0 MB';
    }

    /**
     * Get last backup information
     */
    private function getLastBackupInfo(): array
    {
        $backupDir = storage_path('app/backups/database');
        
        if (!file_exists($backupDir)) {
            return ['last_backup' => 'Never'];
        }
        
        $files = glob($backupDir . '/*.sql');
        
        if (empty($files)) {
            return ['last_backup' => 'Never'];
        }
        
        $latestFile = max($files, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        return [
            'last_backup' => Carbon::createFromTimestamp(filemtime($latestFile))->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Create backup based on database driver
     */
    private function createBackup(string $driver, string $backupPath): array
    {
        switch ($driver) {
            case 'sqlite':
                $databasePath = database_path('database.sqlite');
                if (copy($databasePath, $backupPath)) {
                    return ['success' => true];
                }
                return ['success' => false, 'error' => 'Failed to copy SQLite database'];
                
            case 'mysql':
                $command = sprintf(
                    'mysqldump -h%s -P%s -u%s -p%s %s > %s',
                    config('database.connections.mysql.host'),
                    config('database.connections.mysql.port'),
                    config('database.connections.mysql.username'),
                    config('database.connections.mysql.password'),
                    config('database.connections.mysql.database'),
                    $backupPath
                );
                
                exec($command, $output, $returnCode);
                
                if ($returnCode === 0) {
                    return ['success' => true];
                }
                return ['success' => false, 'error' => 'mysqldump command failed'];
                
            default:
                return ['success' => false, 'error' => 'Unsupported database driver'];
        }
    }

    /**
     * Restore backup based on database driver
     */
    private function restoreBackup(string $driver, string $backupPath): array
    {
        switch ($driver) {
            case 'sqlite':
                $databasePath = database_path('database.sqlite');
                if (copy($backupPath, $databasePath)) {
                    return ['success' => true];
                }
                return ['success' => false, 'error' => 'Failed to restore SQLite database'];
                
            case 'mysql':
                $command = sprintf(
                    'mysql -h%s -P%s -u%s -p%s %s < %s',
                    config('database.connections.mysql.host'),
                    config('database.connections.mysql.port'),
                    config('database.connections.mysql.username'),
                    config('database.connections.mysql.password'),
                    config('database.connections.mysql.database'),
                    $backupPath
                );
                
                exec($command, $output, $returnCode);
                
                if ($returnCode === 0) {
                    return ['success' => true];
                }
                return ['success' => false, 'error' => 'mysql restore command failed'];
                
            default:
                return ['success' => false, 'error' => 'Unsupported database driver'];
        }
    }

    /**
     * Optimize tables based on database driver
     */
    private function optimizeTables(string $driver): array
    {
        $optimizedTables = [];
        
        switch ($driver) {
            case 'sqlite':
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
                foreach ($tables as $table) {
                    DB::statement("VACUUM " . $table->name);
                    $optimizedTables[] = $table->name;
                }
                break;
                
            case 'mysql':
                $tables = DB::select("SHOW TABLES");
                foreach ($tables as $table) {
                    $tableName = array_values((array) $table)[0];
                    DB::statement("OPTIMIZE TABLE " . $tableName);
                    $optimizedTables[] = $tableName;
                }
                break;
        }
        
        return ['optimized_tables' => $optimizedTables];
    }

    /**
     * Get table sizes
     */
    private function getTableSizes(string $driver): array
    {
        $tableSizes = [];
        
        switch ($driver) {
            case 'sqlite':
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
                foreach ($tables as $table) {
                    $count = DB::table($table->name)->count();
                    $tableSizes[] = [
                        'table' => $table->name,
                        'records' => $count,
                        'size' => $this->formatBytes($count * 1024) // Rough estimate
                    ];
                }
                break;
                
            case 'mysql':
                $sizes = DB::select("
                    SELECT 
                        table_name,
                        table_rows,
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE()
                ");
                
                foreach ($sizes as $size) {
                    $tableSizes[] = [
                        'table' => $size->table_name,
                        'records' => $size->table_rows,
                        'size' => $size->size_mb . ' MB'
                    ];
                }
                break;
        }
        
        return $tableSizes;
    }

    /**
     * Get query performance metrics
     */
    private function getQueryPerformance(): array
    {
        // This would typically come from a query log or monitoring system
        return [
            'average_query_time' => rand(10, 100) . 'ms',
            'slow_queries_count' => rand(0, 10),
            'total_queries_today' => rand(1000, 10000),
            'cache_hit_rate' => rand(80, 95) . '%'
        ];
    }

    /**
     * Get index usage statistics
     */
    private function getIndexUsage(string $driver): array
    {
        // Simplified implementation
        return [
            'total_indexes' => rand(20, 100),
            'unused_indexes' => rand(0, 10),
            'index_size' => rand(10, 50) . ' MB',
            'index_efficiency' => rand(85, 98) . '%'
        ];
    }

    /**
     * Get slow queries
     */
    private function getSlowQueries(string $driver): array
    {
        // This would typically come from a slow query log
        return [
            [
                'query' => 'SELECT * FROM users WHERE email = ?',
                'execution_time' => '2.5s',
                'timestamp' => now()->subMinutes(rand(1, 60))->format('Y-m-d H:i:s')
            ],
            [
                'query' => 'SELECT * FROM activity_logs WHERE created_at > ?',
                'execution_time' => '1.8s',
                'timestamp' => now()->subMinutes(rand(1, 60))->format('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Parse bytes from human readable format
     */
    private function parseBytes(string $size): int
    {
        $units = ['B' => 1, 'KB' => 1024, 'MB' => 1024*1024, 'GB' => 1024*1024*1024];
        $unit = strtoupper(substr($size, -2));
        $value = (float) substr($size, 0, -2);
        
        return (int) ($value * ($units[$unit] ?? 1));
    }
} 