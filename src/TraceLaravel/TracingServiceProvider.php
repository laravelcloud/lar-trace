<?php

namespace LaravelCloud\Trace\TraceLaravel;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use LaravelCloud\Trace\Trace\TracingService;

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

    public static $globalSpanAbstract = 'trace.global.span';

    /**
     * TracingServiceProvider constructor.
     * @param $app
     */
    public function __construct($app)
    {
        parent::__construct($app);
    }

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

        app()->terminating(function () use ($service) {
            $service->getGlobalSpan()->annotate('request_finished', \Zipkin\Timestamp\now());
            $service->getGlobalSpan()->finish();
            $service->getTracing()->getTracer()->flush();

            Log::info("end tracing");
        });
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

        $carrier = array_map(function ($header) {
            return $header[0] ?? null;
        }, (array)request()->headers);

        $this->app->singleton(TracingService::class, function () use ($carrier) {
            $tracingService = new TracingService();
            $tracingService->createTracing(config(static::$abstract));
            $tracingService->createGlobalSpan($carrier);
            return $tracingService;
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
