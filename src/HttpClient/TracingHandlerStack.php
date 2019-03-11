<?php

namespace LaravelCloud\Trace\HttpClient;

use LaravelCloud\Trace\TraceLaravel\TracingService;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zipkin\DefaultTracing;
use Zipkin\Propagation\RequestHeaders;
use Zipkin\Span;

/**
 * 携带zipkin trace的header
 */
class TracingHandlerStack
{
    public static function start()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                /**
                 * @var DefaultTracing $tracing
                 * @var Span $span
                 */
                $tracing = app(TracingService::class)->getTracing();

                if (!empty($tracing)) {
                    $span = app(TracingService::class)->getGlobalSpan();
                    $injector = $tracing->getPropagation()->getInjector(new RequestHeaders);
                    $injector($span->getContext(), $request);
                }

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) {
                        return $response;
                    }
                );
            };
        };
    }
}
