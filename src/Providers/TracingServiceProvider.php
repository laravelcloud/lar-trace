<?php
namespace LaravelCloud\Trace\Providers;

use Illuminate\Support\ServiceProvider;
use LaravelCloud\Trace\Services\TracingService;

class TracingServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            TracingService::class,
            function ($app) {
                return new TracingService();
            }
        );
    }
}