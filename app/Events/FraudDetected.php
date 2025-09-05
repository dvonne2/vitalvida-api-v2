<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FraudDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $fraudPattern;
    public $staffName;
    public $riskAmount;

    /**
     * Create a new event instance.
     */
    public function __construct($fraudPattern)
    {
        $this->fraudPattern = $fraudPattern;
        $this->staffName = $fraudPattern->staff->name ?? 'Unknown';
        $this->riskAmount = $fraudPattern->risk_amount;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('gm-portal'),
        ];
    }

    public function broadcastAs()
    {
        return 'fraud.detected';
    }

    public function broadcastWith()
    {
        return [
            'type' => 'FRAUD_DETECTED',
            'staff_name' => $this->staffName,
            'risk_amount' => $this->riskAmount,
            'confidence' => $this->fraudPattern->confidence_score,
            'timestamp' => now()->format('g:i A'),
            'auto_actions' => $this->fraudPattern->auto_action_taken,
        ];
    }
}
