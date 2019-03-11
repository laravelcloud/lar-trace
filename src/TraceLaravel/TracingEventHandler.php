<?php

namespace LaravelCloud\Trace\TraceLaravel;

use Exception;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;

class TracingEventHandler
{
    /**
     * Maps event handler function to event names.
     *
     * @var array
     */
    protected static $eventHandlerMap = array(
        'router.matched' => 'routerMatched', // Until Laravel 5.1
        'illuminate.query' => 'query',         // Until Laravel 5.1

        RouteMatched::class => 'routeMatched',  // Since Laravel 5.2
        QueryExecuted::class => 'queryExecuted', // Since Laravel 5.2

        JobProcessed::class => 'queueJobProcessed', // since Laravel 5.2
        JobProcessing::class => 'queueJobProcessing', // since Laravel 5.2

        CommandStarting::class => 'commandStarting', // Since Laravel 5.5
        CommandFinished::class => 'commandFinished', // Since Laravel 5.5

        RequestHandled::class => 'request',
    );
    /**
     * Indicates if we should we add query bindings to the tracing.
     *
     * @var bool
     */
    private $sqlBindings = false;
    /**
     * @var TracingService
     */
    private $tracingService;

    /**
     * TracingEventHandler constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->tracingService = app(TracingService::class);
        $this->sqlBindings = ($config['sql_bindings'] ?? false) === true;
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
     * Pass through the event and capture any errors.
     *
     * @param string $method
     * @param array $arguments
     */
    /**
     * @param $method
     * @param $arguments
     * @throws TracingException
     */
    public function __call($method, $arguments)
    {
        if (!method_exists($this, $method . 'handler')) {
            throw new TracingException('Missing event handler:' . $method . 'handler');
        }

        try {
            call_user_func_array(array($this, $method . 'Handler'), $arguments);
        } catch (Exception $exception) {
            // Ignore
        }
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

        $span = $this->tracingService->getGlobalSpan();
        $span->start(\Zipkin\Timestamp\now());
        $span->setName((string)config('app.name'));
        $span->setKind(\Zipkin\Kind\SERVER);
        $span->annotate(\Zipkin\Timestamp\now(), 'request_started');
        $span->tag('http.type', app()->runningInConsole() ? 'console' : 'http-request');
        $span->tag('http.env', app()->environment());
        $span->tag(\Zipkin\Tags\HTTP_ROUTE, $routeName);
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
        $child = $this->tracingService->newChild('sql:' . $query, [
            'query.connectionName' => $connectionName,
            'query.sql' => $query,
            'query.bindings' => $this->sqlBindings ? json_encode($bindings) : '******',
            'query.time' => $time
        ]);
        $child->start((int)(\Zipkin\Timestamp\now() - $query->time * 1000));
        $child->finish();
    }

    /**
     * Since Laravel 5.2
     *
     * @param QueryExecuted $query
     */
    protected function queryExecutedHandler(QueryExecuted $query)
    {
        $name = $query->connectionName . ':' . $query->connection->getDatabaseName();
        $child = $this->tracingService->newChild($name, [
            'query.connectionName' => $query->connectionName,
            'query.database' => $query->connection->getDatabaseName(),
            'query.sql' => $query->sql,
            'query.bindings' => $this->sqlBindings ? json_encode($query->bindings) : '******',
            'query.time' => $query->time
        ]);
        $child->start((int)(\Zipkin\Timestamp\now() - $query->time * 1000));
        $child->finish();
    }

    /**
     * Since Laravel 5.2
     *
     * @param JobProcessed $event
     */
    protected function queueJobProcessedHandler(JobProcessed $event)
    {
    }

    /**
     * Since Laravel 5.2
     *
     * @param JobProcessing $event
     */
    protected function queueJobProcessingHandler(JobProcessing $event)
    {
    }

    /**
     * Since Laravel 5.5
     *
     * @param CommandStarting $event
     */
    protected function commandStartingHandler(CommandStarting $event)
    {
    }

    /**
     * Since Laravel 5.5
     *
     * @param CommandFinished $event
     */
    protected function commandFinishedHandler(CommandFinished $event)
    {
    }

    /**
     * @param RequestHandled $event
     */
    protected function requestHandler(RequestHandled $event)
    {
        $params = $event->request->except(config('trace.except'));
        $params = Arr::dot($params, 'http.query.');

        $span = $this->tracingService->getGlobalSpan();
        $span->tag(\Zipkin\Tags\HTTP_HOST, $event->request->getHttpHost());
        $span->tag(\Zipkin\Tags\HTTP_METHOD, $event->request->method());
        $span->tag(\Zipkin\Tags\HTTP_PATH, $event->request->path());
        $span->tag(\Zipkin\Tags\HTTP_URL, $event->request->fullUrl());
        $span->tag(\Zipkin\Tags\HTTP_STATUS_CODE, $event->response->getStatusCode());
        if ($event->response->getStatusCode() >= 400) {
            $span->tag(\Zipkin\Tags\ERROR, $event->response->getContent());
        } else {
            $span->tag('http.response', $event->response->getContent());
        }

        foreach ((array)$params as $k => $v) {
            $span->tag($k, $v);
        }

        $span->annotate('request_finished', \Zipkin\Timestamp\now());
        $span->finish();
        $this->tracingService->getTracing()->getTracer()->flush();
    }
}
