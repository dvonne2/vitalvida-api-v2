<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KPIThresholdBreached implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $department;
    public $metric;
    public $currentValue;
    public $thresholdValue;
    public $breachType;

    /**
     * Create a new event instance.
     */
    public function __construct(string $department, string $metric, $currentValue, $thresholdValue, string $breachType)
    {
        $this->department = $department;
        $this->metric = $metric;
        $this->currentValue = $currentValue;
        $this->thresholdValue = $thresholdValue;
        $this->breachType = $breachType;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('ceo-dashboard'),
            new Channel('performance-metrics'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'department' => $this->department,
            'metric' => $this->metric,
            'current_value' => $this->currentValue,
            'threshold_value' => $this->thresholdValue,
            'breach_type' => $this->breachType,
            'timestamp' => now()->toISOString(),
            'severity' => $this->breachType === 'critical' ? 'high' : 'medium'
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'kpi.threshold.breached';
    }
}
