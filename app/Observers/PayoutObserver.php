<?php

namespace App\Observers;

use App\Models\Payout;
use App\Models\AgentActivityLog;

class PayoutObserver
{
    /**
     * Handle the Payout "created" event.
     */
    public function created(Payout $payout): void
    {
        // Log payout creation
        if ($payout->delivery_agent_id) {
            AgentActivityLog::logActivity(
                $payout->delivery_agent_id,
                'status_change',
                "Payout created for order {$payout->order_id}",
                [
                    'payout_id' => $payout->id,
                    'amount' => $payout->amount,
                    'status' => $payout->status
                ]
            );
        }
    }

    /**
     * Handle the Payout "updated" event.
     */
    public function updated(Payout $payout): void
    {
        $changes = $payout->getChanges();
        
        if (isset($changes['status']) && $payout->delivery_agent_id) {
            AgentActivityLog::logActivity(
                $payout->delivery_agent_id,
                'status_change',
                "Payout status changed to {$payout->status}",
                [
                    'payout_id' => $payout->id,
                    'old_status' => $payout->getOriginal('status'),
                    'new_status' => $payout->status,
                    'amount' => $payout->amount
                ]
            );

            // Update agent's total earnings if payout is approved
            if ($payout->status === 'approved') {
                $payout->deliveryAgent()->increment('total_earnings', $payout->amount);
            }
        }
    }
}
