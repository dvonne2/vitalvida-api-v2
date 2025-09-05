<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\EBulkSMSService;

class EBulkSMSServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(EBulkSMSService::class, function ($app) {
            return new EBulkSMSService();
        });

        $this->app->alias(EBulkSMSService::class, 'ebulksms');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
