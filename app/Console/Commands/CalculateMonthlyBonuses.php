<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessMonthlyBonusCalculation;

class CalculateMonthlyBonuses extends Command
{
    protected $signature = 'bonuses:calculate {month?} {--recalculate}';
    protected $description = 'Calculate monthly bonuses for all employees';

    public function handle(): void
    {
        $month = $this->argument('month') 
            ? \Carbon\Carbon::createFromFormat('Y-m', $this->argument('month')) 
            : \Carbon\Carbon::now()->subMonth();
        
        $recalculate = $this->option('recalculate');

        $this->info("Calculating bonuses for {$month->format('F Y')}...");

        ProcessMonthlyBonusCalculation::dispatch($month, $recalculate);

        $this->info('Bonus calculation job dispatched successfully.');
    }
} 