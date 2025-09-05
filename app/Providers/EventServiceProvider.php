<?php

namespace App\Providers;

use App\Events\DeliveryConfirmed;
use App\Events\AgentUpdatedEvent;
use App\Events\StockAllocatedEvent;
use App\Events\ComplianceActionEvent;
use App\Listeners\ProcessInventoryDeduction;
use App\Listeners\SyncAgentToRoleSystem;
use App\Listeners\SyncStockToBinSystem;
use App\Listeners\HandleComplianceAction;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        DeliveryConfirmed::class => [
            ProcessInventoryDeduction::class,
        ],
        AgentUpdatedEvent::class => [
            SyncAgentToRoleSystem::class,
        ],
        StockAllocatedEvent::class => [
            SyncStockToBinSystem::class,
        ],
        ComplianceActionEvent::class => [
            HandleComplianceAction::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
