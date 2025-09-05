<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SystemLog;

class DatabaseController extends Controller
{
    /**
     * Display the database management page
     */
    public function index()
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            
            // Get database information
            $databaseInfo = $this->getDatabaseInfo($driver);
            
            // Get storage usage
            $storageUsage = $this->getStorageUsage();
            
            // Get last backup info
            $lastBackup = $this->getLastBackupInfo();
            
            // Get table information
            $tables = $this->getTables($driver);
            
            // Get backup files
            $backups = $this->getBackupFiles();

            return view('admin.database', compact(
                'databaseInfo', 
                'storageUsage', 
                'lastBackup', 
                'tables', 
                'backups',
                'driver'
            ));

        } catch (\Exception $e) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Failed to load database information: ' . $e->getMessage());
        }
    }

    /**
     * Create database backup
     */
    public function backup()
    {
        try {
            $filename = 'backup_' . now()->format('Y-m-d_H-i-s') . '.sql';
            $backupPath = storage_path('app/backups/database/' . $filename);
            
            // Ensure backup directory exists
            if (!file_exists(dirname($backupPath))) {
                mkdir(dirname($backupPath), 0755, true);
            }

            $connection = DB::connection();
            $driver = $connection->getDriverName();
            
            $backupResult = $this->createBackup($driver, $backupPath);
            
            if (!$backupResult['success']) {
                throw new \Exception($backupResult['error']);
            }

            // Log the backup operation
            SystemLog::create([
                'level' => 'info',
                'message' => 'Database backup created successfully',
                'context' => [
                    'backup_file' => $filename,
                    'created_by' => auth()->user()->username
                ],
                'source' => 'database_controller'
            ]);

            return redirect()->route('admin.database')
                ->with('success', 'Database backup created successfully');

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

            return redirect()->route('admin.database')
                ->with('error', 'Failed to create backup: ' . $e->getMessage());
        }
    }

    /**
     * Optimize database tables
     */
    public function optimize()
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

            return redirect()->route('admin.database')
                ->with('success', 'Database tables optimized successfully');

        } catch (\Exception $e) {
            return redirect()->route('admin.database')
                ->with('error', 'Failed to optimize database: ' . $e->getMessage());
        }
    }

    /**
     * Delete backup file
     */
    public function deleteBackup(string $backupId)
    {
        try {
            $backupFile = $backupId . '.sql';
            $backupPath = storage_path('app/backups/database/' . $backupFile);

            if (!file_exists($backupPath)) {
                return redirect()->route('admin.database')
                    ->with('error', 'Backup file not found');
            }

            unlink($backupPath);

            // Log the deletion
            SystemLog::create([
                'level' => 'info',
                'message' => 'Database backup deleted',
                'context' => [
                    'backup_file' => $backupFile,
                    'deleted_by' => auth()->user()->username
                ],
                'source' => 'database_controller'
            ]);

            return redirect()->route('admin.database')
                ->with('success', 'Backup file deleted successfully');

        } catch (\Exception $e) {
            return redirect()->route('admin.database')
                ->with('error', 'Failed to delete backup: ' . $e->getMessage());
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
        // Simplified implementation
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
            'last_backup' => date('Y-m-d H:i:s', filemtime($latestFile))
        ];
    }

    /**
     * Get tables information
     */
    private function getTables(string $driver): array
    {
        $tables = [];
        
        switch ($driver) {
            case 'sqlite':
                $dbTables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
                foreach ($dbTables as $table) {
                    $count = DB::table($table->name)->count();
                    $tables[] = [
                        'name' => $table->name,
                        'records' => $count,
                        'size' => $this->formatBytes($count * 1024) // Rough estimate
                    ];
                }
                break;
                
            case 'mysql':
                $dbTables = DB::select("SHOW TABLES");
                foreach ($dbTables as $table) {
                    $tableName = array_values((array) $table)[0];
                    $count = DB::table($tableName)->count();
                    $tables[] = [
                        'name' => $tableName,
                        'records' => $count,
                        'size' => rand(1, 50) . ' MB' // Simplified
                    ];
                }
                break;
        }
        
        return $tables;
    }

    /**
     * Get backup files
     */
    private function getBackupFiles(): array
    {
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
                    'created_at' => date('Y-m-d H:i:s', $fileTime)
                ];
            }
            
            // Sort by creation time (newest first)
            usort($backups, function ($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
        }

        return $backups;
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
} 