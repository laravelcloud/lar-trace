<?php
namespace LaravelCloud\Trace\TraceLaravel;

use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use LaravelCloud\Trace\Trace\TracingService;
use Zipkin\Recording\Span;

class TracingEventHandler
{
    /**
     * Indicates if we should we add query bindings to the tracing.
     *
     * @var bool
     */
    private $sqlBindings;

    /**
     * Maps event handler function to event names.
     *
     * @var array
     */
    protected static $eventHandlerMap = array(
        'router.matched' => 'routerMatched', // Until Laravel 5.1
        'illuminate.query' => 'query',         // Until Laravel 5.1
        'illuminate.log' => 'log',           // Until Laravel 5.3

        RouteMatched::class => 'routeMatched',  // Since Laravel 5.2
        QueryExecuted::class => 'queryExecuted', // Since Laravel 5.2

        JobProcessed::class => 'queueJobProcessed', // since Laravel 5.2
        JobProcessing::class => 'queueJobProcessing', // since Laravel 5.2

        MessageLogged::class => 'messageLogged', // Since Laravel 5.4

        CommandStarting::class => 'commandStarting', // Since Laravel 5.5
        CommandFinished::class => 'commandFinished', // Since Laravel 5.5

        RequestHandled::class => 'request',
    );

    /**
     * Maps authentication event handler function to event names.
     *
     * @var array
     */
    protected static $authEventHandlerMap = array(
        Authenticated::class => 'authenticated', // Since Laravel 5.3
    );

    /**
     * TracingEventHandler constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->sqlBindings = isset($config['trace.sql_bindings']) ? $config['trace.sql_bindings'] === true : false;
    }

    /**
     * Attach all event handlers.
     *
     * @param Dispatcher $events
     */
    public function subscribe(Dispatcher $events)
    {
        foreach (static::$eventHandlerMap as $eventName => $handler) {
            $events->listen($eventName, array($this, $handler));
        }
    }

    /**
     * Attach all authentication event handlers.
     *
     * @param Dispatcher $events
     */
    public function subscribeAuthEvents(Dispatcher $events)
    {
        foreach (static::$authEventHandlerMap as $eventName => $handler) {
            $events->listen($eventName, array($this, $handler));
        }
    }

    /**
     * Pass through the event and capture any errors.
     *
     * @param string $method
     * @param array  $arguments
     */
    public function __call($method, $arguments)
    {
        try {
            call_user_func_array(array($this, $method . 'handler'), $arguments);
        } catch (Exception $exception) {
            // Ignore
        }
    }

    /**
     * Until Laravel 5.1
     *
     * @param Route $route
     */
    protected function routerMatchedHandler(Route $route)
    {
        if ($route->getName()) {
            // someaction (route name/alias)
            $routeName = $route->getName();
        } elseif ($route->getActionName()) {
            // SomeController@someAction (controller action)
            $routeName = $route->getActionName();
        }
        if (empty($routeName) || $routeName === 'Closure') {
            // /someaction // Fallback to the url
            $routeName = $route->uri();
        }

        /**
         * @var Span $span
         */
        $span = app('trace.global.span');
        $span->tag(\Zipkin\Tags\HTTP_ROUTE, $routeName);
    }

    /**
     * Since Laravel 5.2
     *
     * @param RouteMatched $match
     */
    protected function routeMatchedHandler(RouteMatched $match)
    {
        $this->routerMatchedHandler($match->route);
    }

    /**
     * Until Laravel 5.1
     *
     * @param $query
     * @param $bindings
     * @param $time
     * @param $connectionName
     */
    protected function queryHandler($query, $bindings, $time, $connectionName)
    {
        $data = array('connectionName' => $connectionName);

        if ($this->sqlBindings) {
            $data['bindings'] = $bindings;
        }

        /**
         * @var Span $span
         */
        $span = app('trace.global.span');
        $span->annotate(\Zipkin\Timestamp\now(), $query->sql);
    }

    /**
     * Since Laravel 5.2
     *
     * @param QueryExecuted $query
     */
    protected function queryExecutedHandler(QueryExecuted $query)
    {
        $data = array('connectionName' => $query->connectionName);

        if ($this->sqlBindings) {
            $data['bindings'] = $query->bindings;
        }

        /**
         * @var Span $span
         */
        $span = app('trace.global.span');
        $span->annotate(\Zipkin\Timestamp\now(), $query->sql);
    }

    /**
     * Until Laravel 5.3
     *
     * @param $level
     * @param $message
     * @param $context
     */
    protected function logHandler($level, $message, $context)
    {
        return;
    }

    /**
     * Since Laravel 5.4
     *
     * @param MessageLogged $logEntry
     */
    protected function messageLoggedHandler(MessageLogged $logEntry)
    {
        return;
    }

    /**
     * Since Laravel 5.3
     *
     * @param Authenticated $event
     */
    protected function authenticatedHandler(Authenticated $event)
    {
        return;
    }

    /**
     * Since Laravel 5.2
     *
     * @param JobProcessed $event
     */
    protected function queueJobProcessedHandler(JobProcessed $event)
    {
        return;
    }

    /**
     * Since Laravel 5.2
     *
     * @param JobProcessing $event
     */
    protected function queueJobProcessingHandler(JobProcessing $event)
    {
        return;
    }

    /**
     * Since Laravel 5.5
     *
     * @param CommandStarting $event
     */
    protected function commandStartingHandler(CommandStarting $event)
    {
        return;
    }

    /**
     * Since Laravel 5.5
     *
     * @param CommandFinished $event
     */
    protected function commandFinishedHandler(CommandFinished $event)
    {
       return;
    }

    protected function requestHandler(RequestHandled $event)
    {
        $params = $event->request->except(config('trace.except'));
        $params = Arr::dot($params, 'http.query.');

        /**
         * @var Span $span
         */
        $span = app('trace.global.span');
        $span->tag(\Zipkin\Tags\HTTP_HOST,          $event->request->getHttpHost());
        $span->tag(\Zipkin\Tags\HTTP_METHOD,        $event->request->method());
        $span->tag(\Zipkin\Tags\HTTP_PATH,          $event->request->path());
        $span->tag(\Zipkin\Tags\HTTP_URL,           $event->request->fullUrl());
        $span->tag(\Zipkin\Tags\HTTP_STATUS_CODE,   $event->response->getStatusCode());
        $span->tag(\Zipkin\Tags\ERROR,              $event->response->getContent());

        foreach ((array)$params as $k => $v) {
            $span->tag($k, $v);
        }
    }

}