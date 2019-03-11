<?php

namespace LaravelCloud\Trace\TraceLaravel;

use Illuminate\Support\ServiceProvider;
use Zipkin\Propagation\RequestHeaders;

/**
 * Class TracingServiceProvider
 * @package LaravelCloud\Trace\TraceLaravel
 */
class TracingServiceProvider extends ServiceProvider
{
    /**
     * @var string
     */
    public static $abstract = 'trace';

    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        // Publish the configuration file
        $this->publishes(array(
            __DIR__ . '/../../config/trace.php' => config_path(static::$abstract . '.php'),
        ), 'config');

        /**
         * @var TracingService $service
         */
        $service = app(TracingService::class);
        $service->createTracing(config(static::$abstract));
        $service->createGlobalSpan(request(), new RequestHeaders());

        $this->bindEvents($this->app);
    }

    /**
     * Bind to the Laravel event dispatcher to log events.
     *
     * @param $app
     */
    protected function bindEvents($app)
    {
        $handler = new TracingEventHandler(config(static::$abstract));

        $handler->subscribe($app->events);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/trace.php', static::$abstract);

        $this->app->singleton(TracingService::class, function () {
            return new TracingService();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            static::$abstract
        ];
    }
}
