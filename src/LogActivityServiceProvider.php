<?php

namespace Inme\ActivityLogger;

use Illuminate\Support\ServiceProvider;

/**
 * Class LogActivityServiceProvider
 * @package Inme\ActivityLogger
 */
class LogActivityServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ => app_path('Http/Controllers/Traits'),
        ]);

    }
}
