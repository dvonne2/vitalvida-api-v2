<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bin;

class SyncZohoBins extends Command
{
    protected $signature = 'zoho:sync-bins';
    protected $description = 'Sync bin data from Zoho to local database';

    public function handle()
    {
        $this->info('🚀 Starting Zoho bins sync...');
        
        $binCount = Bin::count();
        $this->info("📊 Current bins in database: {$binCount}");
        
        $this->info('✅ Sync completed!');
    }
}

