<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ZohoInventoryService;
use Illuminate\Support\Facades\Log;

class SyncZohoInventory extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'zoho:sync-inventory 
                            {--da-id= : Sync specific DA inventory by ID}
                            {--low-stock : Only show DAs with low stock}
                            {--stats : Show sync statistics}
                            {--dry-run : Show what would be synced without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Sync DA inventory from Zoho (source of truth)';

    protected $zohoInventoryService;

    /**
     * Create a new command instance.
     */
    public function __construct(ZohoInventoryService $zohoInventoryService)
    {
        parent::__construct();
        $this->zohoInventoryService = $zohoInventoryService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Zoho Inventory Sync Command');
        $this->info('================================');

        try {
            // Handle different command options
            if ($this->option('stats')) {
                return $this->showStatistics();
            }

            if ($this->option('low-stock')) {
                return $this->showLowStockDas();
            }

            if ($this->option('da-id')) {
                return $this->syncSpecificDa();
            }

            // Default: sync all DAs
            return $this->syncAllDas();

        } catch (\Exception $e) {
            $this->error('❌ Command failed: ' . $e->getMessage());
            Log::error('Zoho inventory sync command failed', [
                'error' => $e->getMessage(),
                'options' => $this->options()
            ]);
            return 1;
        }
    }

    /**
     * Sync all DAs inventory
     */
    protected function syncAllDas(): int
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('📦 Syncing all DA inventory from Zoho...');
        
        if (!$isDryRun) {
            $result = $this->zohoInventoryService->syncAllDaInventory();
        } else {
            // For dry run, just get current summaries
            $result = [
                'success' => true,
                'results' => [],
                'summary' => ['total' => 0, 'successful' => 0, 'failed' => 0]
            ];
        }

        if (!$result['success']) {
            $this->error('❌ Sync failed: ' . ($result['error'] ?? 'Unknown error'));
            return 1;
        }

        // Display results
        $this->displaySyncResults($result);

        $this->info('✅ Sync completed successfully!');
        return 0;
    }

    /**
     * Sync specific DA inventory
     */
    protected function syncSpecificDa(): int
    {
        $daId = $this->option('da-id');
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info("📦 Syncing DA {$daId} inventory from Zoho...");

        if (!$isDryRun) {
            $result = $this->zohoInventoryService->forceSyncDaInventory($daId);
        } else {
            $result = ['success' => true, 'data' => []];
        }

        if (!$result['success']) {
            $this->error('❌ DA sync failed: ' . ($result['error'] ?? 'Unknown error'));
            return 1;
        }

        if (!$isDryRun && isset($result['data'])) {
            $this->displaySingleDaResult($result['data']);
        }

        $this->info('✅ DA sync completed successfully!');
        return 0;
    }

    /**
     * Show DAs with low stock
     */
    protected function showLowStockDas(): int
    {
        $this->info('🔍 Checking for DAs with low stock...');

        $result = $this->zohoInventoryService->getDasWithLowStock();

        if (!$result['success']) {
            $this->error('❌ Failed to get low stock DAs: ' . ($result['error'] ?? 'Unknown error'));
            return 1;
        }

        if (empty($result['data'])) {
            $this->info('✅ No DAs with low stock found!');
            return 0;
        }

        $this->warn("⚠️  Found {$result['count']} DAs with low stock:");
        
        $headers = ['DA ID', 'DA Code', 'Bin Name', 'Shampoo', 'Pomade', 'Conditioner', 'Sets', 'Status'];
        $rows = [];

        foreach ($result['data'] as $da) {
            $rows[] = [
                $da['da_id'],
                $da['da_code'],
                $da['bin_name'],
                $da['inventory']['shampoo_count'],
                $da['inventory']['pomade_count'],
                $da['inventory']['conditioner_count'],
                $da['available_sets'],
                $da['status']
            ];
        }

        $this->table($headers, $rows);
        return 0;
    }

    /**
     * Show sync statistics
     */
    protected function showStatistics(): int
    {
        $this->info('📊 Zoho Inventory Sync Statistics');

        $result = $this->zohoInventoryService->getSyncStatistics();

        if (!$result['success']) {
            $this->error('❌ Failed to get statistics: ' . ($result['error'] ?? 'Unknown error'));
            return 1;
        }

        $stats = $result['data'];

        $this->info('Last 24 Hours:');
        $this->line("  Total Syncs: {$stats['last_24_hours']['total_syncs']}");
        $this->line("  Successful: {$stats['last_24_hours']['successful_syncs']}");
        $this->line("  Failed: {$stats['last_24_hours']['failed_syncs']}");
        $this->line("  Success Rate: {$stats['last_24_hours']['success_rate']}%");

        $this->info('System Status:');
        $this->line("  Active DAs: {$stats['active_das']}");
        $this->line("  Last Sync: " . ($stats['last_sync'] ? $stats['last_sync']->format('Y-m-d H:i:s') : 'Never'));

        return 0;
    }

    /**
     * Display sync results table
     */
    protected function displaySyncResults(array $result): void
    {
        $summary = $result['summary'];
        
        $this->info('📊 Sync Summary:');
        $this->line("  Total DAs: {$summary['total']}");
        $this->line("  Successful: {$summary['successful']}");
        $this->line("  Failed: {$summary['failed']}");

        if (!empty($result['results'])) {
            $headers = ['DA ID', 'DA Code', 'Bin Name', 'Status', 'Error'];
            $rows = [];

            foreach ($result['results'] as $daResult) {
                $rows[] = [
                    $daResult['da_id'],
                    $daResult['da_code'],
                    $daResult['bin_name'],
                    $daResult['success'] ? '✅ Success' : '❌ Failed',
                    $daResult['error'] ?? '-'
                ];
            }

            $this->table($headers, $rows);
        }
    }

    /**
     * Display single DA result
     */
    protected function displaySingleDaResult(array $data): void
    {
        $this->info('📦 Sync Result:');
        $this->line("  Bin ID: {$data['bin_id']}");
        $this->line("  Bin Name: {$data['bin_name']}");
        $this->line("  Synced At: {$data['synced_at']}");

        if (isset($data['inventory_counts'])) {
            $counts = $data['inventory_counts'];
            $this->line("  Shampoo: {$counts['shampoo_count']}");
            $this->line("  Pomade: {$counts['pomade_count']}");
            $this->line("  Conditioner: {$counts['conditioner_count']}");
            $this->line("  Total Items: {$counts['total_items']}");
        }
    }
} 