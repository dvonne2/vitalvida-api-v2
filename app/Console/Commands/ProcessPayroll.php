<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessMonthlyPayroll;

class ProcessPayroll extends Command
{
    protected $signature = 'payroll:process {month?}';
    protected $description = 'Process monthly payroll for all employees';

    public function handle(): void
    {
        $month = $this->argument('month') 
            ? \Carbon\Carbon::createFromFormat('Y-m', $this->argument('month')) 
            : \Carbon\Carbon::now()->subMonth();

        $this->info("Processing payroll for {$month->format('F Y')}...");

        ProcessMonthlyPayroll::dispatch($month);

        $this->info('Payroll processing job dispatched successfully.');
    }
} 