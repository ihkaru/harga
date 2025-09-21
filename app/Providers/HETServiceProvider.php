<?php

namespace App\Providers;

use App\Services\HETService;
use Illuminate\Support\ServiceProvider;

class HETServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(HETService::class, function ($app) {
            return new HETService(base_path('het.csv'));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}