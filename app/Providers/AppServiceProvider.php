<?php

namespace App\Providers;

use App\Listeners\AuditAuthEvents;
use App\Services\Access;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Audit Event Listener
        Event::subscribe(AuditAuthEvents::class);

        // Register custom @module Blade directive
        Blade::if('module', fn (string $module, string $action = 'view') => 
            Access::can(auth()->user(), $module, $action)
        );
    }
}