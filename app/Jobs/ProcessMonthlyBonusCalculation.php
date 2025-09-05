<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\BonusCalculationService;
use App\Models\User;
use App\Notifications\MonthlyBonusCalculationComplete;

class ProcessMonthlyBonusCalculation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private \Carbon\Carbon $month,
        private bool $recalculate = false
    ) {}

    public function handle(BonusCalculationService $bonusService): void
    {
        try {
            Log::info('Starting automated bonus calculation job', [
                'month' => $this->month->format('Y-m'),
                'recalculate' => $this->recalculate
            ]);

            // Calculate bonuses for the month
            $results = $bonusService->calculateMonthlyBonuses($this->month);

            // Send notifications to management about results
            $this->notifyManagement($results);

            Log::info('Automated bonus calculation completed', [
                'month' => $this->month->format('Y-m'),
                'total_employees' => $results['summary']['total_employees'],
                'total_amount' => $results['summary']['total_amount']
            ]);
        } catch (\Exception $e) {
            Log::error('Automated bonus calculation failed', [
                'month' => $this->month->format('Y-m'),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function notifyManagement(array $results): void
    {
        $management = User::whereIn('role', ['fc', 'gm', 'ceo'])->get();
        
        foreach ($management as $manager) {
            $manager->notify(new MonthlyBonusCalculationComplete($results));
        }
    }
} 