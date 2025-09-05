<?php

namespace App\Listeners;

use App\Events\FraudDetected;
use App\Http\Controllers\Api\CommunicationController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Http\Request;

class SendFraudAlert implements ShouldQueue
{
    use InteractsWithQueue;

    protected $communicationController;

    /**
     * Create the event listener.
     */
    public function __construct(CommunicationController $communicationController)
    {
        $this->communicationController = $communicationController;
    }

    /**
     * Handle the event.
     */
    public function handle(FraudDetected $event): void
    {
        // Auto-send fraud alert via WhatsApp/SMS
        $this->communicationController->sendFraudAlert(new Request([
            'staff_name' => $event->staffName,
            'fraud_type' => $event->fraudPattern->type,
            'amount' => $event->riskAmount,
            'auto_actions' => explode(', ', $event->fraudPattern->auto_action_taken),
        ]));
    }
}
