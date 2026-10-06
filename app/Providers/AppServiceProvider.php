<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        // TEMPORARY: log every query slower than 500 ms. Remove this block when you're done testing.
        DB::listen(function ($query) {
            if ($query->time > 500) {
                Log::warning(sprintf('SLOW QUERY %.0f ms: %s', $query->time, $query->sql));
            }
        });
    }
}