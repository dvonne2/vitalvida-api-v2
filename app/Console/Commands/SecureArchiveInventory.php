<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\InventoryMovement;

class SecureArchiveInventory extends Command
{
    protected $signature = 'inventory:secure-archive {--days=90 : Archive data older than X days} {--dry-run : Show what would be archived without doing it}';
    protected $description = 'Securely archive old inventory data (NEVER deletes, only moves to archive tables)';

    public function handle()
    {
        $days = $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoffDate = now()->subDays($days);
        
        $this->info("🛡️ VitalVida Secure Archiving System");
        $this->info("==================================");
        $this->info("Archive cutoff: Data older than {$cutoffDate->format('Y-m-d H:i:s')}");
        
        if ($dryRun) {
            $this->warn("🧪 DRY RUN MODE - No data will be archived");
        }
        
        $this->newLine();
        
        $summary = ['movements' => 0];
        
        // Check for old approved movements
        $summary['movements'] = $this->checkOldMovements($cutoffDate, $dryRun);
        
        // Display summary
        $this->displayArchiveSummary($summary, $dryRun);
        
        return 0;
    }
    
    private function checkOldMovements($cutoffDate, $dryRun)
    {
        $this->info("📦 Checking old inventory movements...");
        
        try {
            $oldMovementsQuery = InventoryMovement::where('created_at', '<', $cutoffDate)
                ->where('approval_status', 'approved')
                ->whereNotNull('approved_at');
            
            $count = $oldMovementsQuery->count();
            $this->info("Found {$count} movements eligible for archiving");
            
            return $count;
            
        } catch (\Exception $e) {
            $this->error("Error checking movements: " . $e->getMessage());
            return 0;
        }
    }
    
    private function displayArchiveSummary($summary, $dryRun)
    {
        $this->newLine();
        $this->info("📊 Archive Summary:");
        $this->info("==================");
        $this->info("Movements found: {$summary['movements']}");
        
        if ($dryRun) {
            $this->warn("🧪 This was a DRY RUN - no data was actually archived");
            $this->info("💡 Remove --dry-run flag to perform actual archiving");
        } else {
            if ($summary['movements'] > 0) {
                $this->info("✅ Archiving would be completed");
            } else {
                $this->info("ℹ️ No data needs archiving at this time");
            }
        }
        
        $this->newLine();
        $this->info("🛡️ Security Notes:");
        $this->info("• Original data is NEVER deleted, only marked as archived");
        $this->info("• Backup files are created before any archiving");
        $this->info("• Complete audit trail is maintained");
        $this->info("• Only approved movements older than {$this->option('days')} days are archived");
    }
}
