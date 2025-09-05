    protected function schedule(Schedule $schedule)
    {
        // Auto-revert stale payouts every 2 hours
        $schedule->call(function () {
            $this->autoRevertStalePayouts();
        })->everyTwoHours()->withoutOverlapping();

        // Daily cleanup at 2 AM
        $schedule->call(function () {
            $this->autoRevertStalePayouts();
        })->dailyAt('02:00');
    }

    private function autoRevertStalePayouts()
    {
        $cutoffTime = now()->subHours(48);
        
        $stalePayouts = DB::table('payouts')
            ->whereIn('status', ['intent_marked', 'receipt_confirmed'])
            ->where('created_at', '<', $cutoffTime)
            ->get();

        foreach ($stalePayouts as $payout) {
            DB::table('payouts')
                ->where('id', $payout->id)
                ->update([
                    'status' => 'auto_reverted',
                    'updated_at' => now()
                ]);

            // Log the auto-revert
            \App\Helpers\SystemLogger::logAction('payout_auto_reverted', null, 'system', [
                'payout_id' => $payout->id,
                'order_id' => $payout->order_id,
                'original_status' => $payout->status,
                'hours_stale' => now()->diffInHours($payout->created_at),
                'auto_reverted_at' => now()->toISOString()
            ]);
        }

        \Log::info("Auto-reverted {$stalePayouts->count()} stale payouts");
    }
    protected function schedule(Schedule $schedule)
    {
        // Auto-revert stale payouts every 2 hours
        $schedule->call(function () {
            $this->autoRevertStalePayouts();
        })->everyTwoHours()->withoutOverlapping();

        // Daily cleanup at 2 AM
        $schedule->call(function () {
            $this->autoRevertStalePayouts();
        })->dailyAt('02:00');
    }

    private function autoRevertStalePayouts()
    {
        $cutoffTime = now()->subHours(48);
        
        $stalePayouts = DB::table('payouts')
            ->whereIn('status', ['intent_marked', 'receipt_confirmed'])
            ->where('created_at', '<', $cutoffTime)
            ->get();

        foreach ($stalePayouts as $payout) {
            DB::table('payouts')
                ->where('id', $payout->id)
                ->update([
                    'status' => 'auto_reverted',
                    'updated_at' => now()
                ]);

            // Log the auto-revert
            \App\Helpers\SystemLogger::logAction('payout_auto_reverted', null, 'system', [
                'payout_id' => $payout->id,
                'order_id' => $payout->order_id,
                'original_status' => $payout->status,
                'hours_stale' => now()->diffInHours($payout->created_at),
                'auto_reverted_at' => now()->toISOString()
            ]);
        }

        \Log::info("Auto-reverted {$stalePayouts->count()} stale payouts");
    }
