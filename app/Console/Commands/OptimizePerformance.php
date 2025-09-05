<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\WeeklyPerformance;

class OptimizePerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'optimize:performance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize application performance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting performance optimization...');
        
        // Clear and rebuild caches
        $this->call('cache:clear');
        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('view:cache');
        
        // Optimize composer autoloader
        $this->call('optimize');
        
        // Clear expired notifications
        $this->cleanExpiredNotifications();
        
        // Archive old performance data
        $this->archiveOldPerformanceData();
        
        // Optimize database tables
        $this->optimizeDatabaseTables();
        
        $this->info('Performance optimization completed.');
    }
    
    private function cleanExpiredNotifications()
    {
        try {
            $deleted = DB::table('notifications')
                ->where('created_at', '<', now()->subDays(30))
                ->delete();
                
            $this->info("Cleaned {$deleted} expired notifications");
        } catch (\Exception $e) {
            $this->error("Failed to clean notifications: " . $e->getMessage());
        }
    }
    
    private function archiveOldPerformanceData()
    {
        try {
            $archived = WeeklyPerformance::where('week_start', '<', now()->subMonths(6))
                ->update(['archived' => true]);
                
            $this->info("Archived {$archived} old performance records");
        } catch (\Exception $e) {
            $this->error("Failed to archive performance data: " . $e->getMessage());
        }
    }
    
    private function optimizeDatabaseTables()
    {
        try {
            $tables = ['orders', 'telesales_agents', 'delivery_agents', 'weekly_performances'];
            
            foreach ($tables as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    DB::statement("OPTIMIZE TABLE {$table}");
                    $this->info("Optimized table: {$table}");
                }
            }
        } catch (\Exception $e) {
            $this->error("Failed to optimize database tables: " . $e->getMessage());
        }
    }
} 