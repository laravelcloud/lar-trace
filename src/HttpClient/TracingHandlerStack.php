<?php

namespace LaravelCloud\Trace\HttpClient;

use LaravelCloud\Trace\TraceLaravel\TracingService;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 携带zipkin trace的header
 */
class TracingHandlerStack
{
    public static function start()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {

                $service = app('trace');
                $service->injector($service->getTrace(), $request);

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($request, $handler) {
                        return $response;
                    }
                );
            };
        };
    }
}
