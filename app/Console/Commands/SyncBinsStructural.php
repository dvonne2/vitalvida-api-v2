<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BinSyncService;

class SyncBinsStructural extends Command
{
    protected $signature = 'bins:sync-structural {--dry-run : Show what would be synced}';
    protected $description = 'Sync bin structure between Laravel and Zoho';

    public function handle(BinSyncService $binSyncService)
    {
        $this->info('🔄 Starting Structural Bin Synchronization');
        $this->newLine();

        try {
            if (!$this->option('dry-run')) {
                if (!$this->confirm('Proceed with bin synchronization?')) {
                    $this->info('Synchronization cancelled.');
                    return Command::SUCCESS;
                }

                $results = $binSyncService->syncAllBins();
                
                $this->info('✅ Sync completed!');
                $this->line('Bins synced: ' . $results['laravel_bins_synced']);
                $this->line('Created in Zoho: ' . $results['zoho_bins_created']);
                $this->line('Updated in Zoho: ' . $results['zoho_bins_updated']);
                
                if (!empty($results['errors'])) {
                    $this->error('❌ Errors encountered:');
                    foreach ($results['errors'] as $error) {
                        $this->line("  • {$error}");
                    }
                }
            } else {
                $this->info('🔍 Dry run - no changes made');
                $this->displayCurrentState();
            }

        } catch (\Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function displayCurrentState()
    {
        $laravelBins = \App\Models\Bin::count();
        $laravelBinsWithZoho = \App\Models\Bin::whereNotNull('zoho_storage_id')->count();
        
        $this->table([
            'Metric', 'Count'
        ], [
            ['Total Laravel Bins', $laravelBins],
            ['Bins with Zoho ID', $laravelBinsWithZoho],
            ['Bins needing sync', $laravelBins - $laravelBinsWithZoho],
        ]);
    }
}
