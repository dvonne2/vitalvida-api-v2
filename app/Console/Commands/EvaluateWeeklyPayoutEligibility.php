<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EvaluateWeeklyPayoutEligibility extends Command
{
    protected $signature = 'payouts:evaluate-weekly-eligibility';
    protected $description = 'Evaluate delivery agents eligibility for weekly payouts based on Friday photo submission deadline';

    public function handle()
    {
        $this->info('Starting weekly payout eligibility evaluation...');
        
        $today = Carbon::now();
        if (!$today->isFriday()) {
            $this->warn('This command should only run on Fridays. Current day: ' . $today->format('l'));
            return 1;
        }

        $deadline = Carbon::today()->setTime(12, 0, 0);
        if ($today->lt($deadline)) {
            $this->warn('Command run before deadline. Current time: ' . $today->format('H:i:s') . ', Deadline: 12:00:00');
            return 1;
        }

        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        
        $this->info("Evaluating submissions for week: {$weekStart->format('Y-m-d')} to {$weekEnd->format('Y-m-d')}");

        $deliveryAgents = DB::table('delivery_agents')->select('id', 'name', 'zone')->get();
        
        $qualified = 0;
        $disqualified = 0;
        $errors = 0;

        foreach ($deliveryAgents as $agent) {
            try {
                $validSubmission = DB::table('photo_submissions')
                    ->where('delivery_agent_id', $agent->id)
                    ->where('created_at', '>=', $weekStart)
                    ->where('created_at', '<=', $deadline)
                    ->where('is_valid', true)
                    ->where('has_timestamp', true)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($validSubmission) {
                    DB::table('delivery_agents')
                        ->where('id', $agent->id)
                        ->update([
                            'eligible_for_next_payout' => true,
                            'last_valid_submission_at' => $validSubmission->created_at,
                            'disqualification_reason' => null,
                            'disqualified_at' => null,
                            'updated_at' => now()
                        ]);
                    
                    $qualified++;
                    $this->line("✅ {$agent->name} ({$agent->zone}) - QUALIFIED");
                } else {
                    DB::table('delivery_agents')
                        ->where('id', $agent->id)
                        ->update([
                            'eligible_for_next_payout' => false,
                            'last_valid_submission_at' => null,
                            'disqualification_reason' => 'No valid photo submitted before Friday 12:00 PM deadline',
                            'disqualified_at' => now(),
                            'updated_at' => now()
                        ]);
                    
                    $disqualified++;
                    $this->line("❌ {$agent->name} ({$agent->zone}) - DISQUALIFIED");
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("Error processing {$agent->name}: " . $e->getMessage());
                
                DB::table('system_logs')->insert([
                    'type' => 'payout_eligibility_error',
                    'message' => "Error evaluating eligibility for agent {$agent->id}: " . $e->getMessage(),
                    'created_at' => now()
                ]);
            }
        }

        $this->sendNotifications($qualified, $disqualified);

        DB::table('system_logs')->insert([
            'type' => 'payout_eligibility_summary',
            'message' => "Weekly eligibility evaluation completed. Qualified: {$qualified}, Disqualified: {$disqualified}, Errors: {$errors}",
            'created_at' => now()
        ]);

        $this->info("✅ Evaluation completed!");
        $this->info("Qualified: {$qualified}");
        $this->info("Disqualified: {$disqualified}");
        $this->info("Errors: {$errors}");

        return 0;
    }

    private function sendNotifications($qualified, $disqualified)
    {
        $this->info('Sending notifications...');

        $qualifiedAgents = DB::table('delivery_agents')
            ->where('eligible_for_next_payout', true)
            ->where('disqualified_at', null)
            ->get();

        foreach ($qualifiedAgents as $agent) {
            $this->sendNotification($agent, 'Your photo submission qualifies you for Monday payout.');
        }

        $disqualifiedAgents = DB::table('delivery_agents')
            ->where('eligible_for_next_payout', false)
            ->whereNotNull('disqualified_at')
            ->get();

        foreach ($disqualifiedAgents as $agent) {
            $this->sendNotification($agent, 'You missed the photo submission deadline. You are not eligible for payout this Monday.');
        }

        $this->info("Notifications sent to {$qualified} qualified and {$disqualified} disqualified agents");
    }

    private function sendNotification($agent, $message)
    {
        DB::table('notifications')->insert([
            'delivery_agent_id' => $agent->id,
            'type' => 'payout_eligibility',
            'message' => $message,
            'sent_at' => now(),
            'created_at' => now()
        ]);
    }
}
