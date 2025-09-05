<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DetailedBinInventoryReport extends Command
{
    protected $signature = 'inventory:detailed-bin-report {--email=fulanihairgro2020@gmail.com}';
    protected $description = 'Generate detailed bin-by-bin inventory report';

    public function handle()
    {
        $this->info('📦 VitalVida Bin System Test');
        $this->info('Email: ' . $this->option('email'));
        $this->info('✅ Command is working!');
        return 0;
    }
}
