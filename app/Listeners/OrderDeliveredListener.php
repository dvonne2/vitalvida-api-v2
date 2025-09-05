<?php

namespace App\Listeners;

use App\Events\OrderDelivered;
use App\Services\ReferralService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class OrderDeliveredListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private ReferralService $referralService
    ) {}

    public function handle(OrderDelivered $event): void
    {
        $order = $event->order;
        
        // Credit referrer reward if this order has a referral token
        if ($order->referral_ref_token) {
            $this->referralService->creditReferrerReward($order);
        }
    }
}
