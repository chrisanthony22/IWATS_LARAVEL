<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event; // <--- ADDED THIS IMPORT
use Illuminate\Support\ServiceProvider;
use App\Listeners\AuditAuthEvents;

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
        Event::subscribe(AuditAuthEvents::class);     // logs login, logout, failed login, lockout, password reset, register
    }
}