<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryConfirmed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $orderNumber;
    public $deliveryData;
    public $confirmedBy;

    public function __construct(string $orderNumber, array $deliveryData, int $confirmedBy)
    {
        $this->orderNumber = $orderNumber;
        $this->deliveryData = $deliveryData;
        $this->confirmedBy = $confirmedBy;
    }
}
