<?php

namespace LaravelCloud\Trace\TraceLaravel;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use LaravelCloud\Trace\Trace\TracingService;
use Zipkin\Recording\Span;

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
     * @var Span $span
     */
    public function boot()
    {
        // Publish the configuration file
        $this->publishes(array(
            __DIR__ . '/../../../config/trace.php' => config_path(static::$abstract . '.php'),
        ), 'config');

        $name   = config('app.name') ?: '';

        /**
         * @var Span $span
         */
        $span = app(self::$globalSpanAbstract);
        $span->start(\Zipkin\Timestamp\now());
        $span->setName($name);
        $span->setKind(\Zipkin\Kind\SERVER);
        $span->annotate(\Zipkin\Timestamp\now(), 'request_started');
        $span->tag('http.type', app()->runningInConsole() ? 'console' : 'http-request');
        $span->tag('http.env',  app()->environment());

        app()->terminating(function () {
            app(self::$globalSpanAbstract)->annotate('request_finished', \Zipkin\Timestamp\now());
            app(self::$globalSpanAbstract)->finish();
            app(self::$abstract)->flush();
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

        // In Laravel >=5.3 we can get the user context from the auth events
        if (version_compare($app::VERSION, '5.5') >= 0) {
            $handler->subscribeAuthEvents($app->events);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../config/trace.php', static::$abstract);

        $carrier = array_map(function ($header) {
            return $header[0];
        }, (array)request()->headers);

        $tracingService = new TracingService();
        $tracingService->createTracing(config(static::$abstract));
        $tracingService->createGlobalSpan($carrier);

        $this->app->singleton(self::$abstract, function () use ($tracingService) {
            return $tracingService->getTracing();
        });

        $this->app->singleton(self::$globalSpanAbstract, function() use ($tracingService) {
            return $tracingService->getGlobalSpan();
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
