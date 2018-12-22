<?php

namespace LaravelCloud\Trace\TraceLaravel;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Zipkin\Endpoint;
use Zipkin\Propagation\DefaultSamplingFlags;
use Zipkin\Reporters\Http;
use Zipkin\Samplers\PercentageSampler;

class TracingMiddleware
{
    /**
     * The application instance.
     *
     * @var Application $app
     */
    protected $app;

    /**
     * Create a new middleware instance.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * 开启tracing记录
     * @param $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $rate = config('trace.rate');
        $endpointUrl = config('trace.endpoint_url');
        $serviceName = config('trace.service_name');

        $uri = $request->getRequestUri();
        $query = $request->query->all();
        $method = $request->getMethod();
        $headers = $request->headers->all() ?: [];
        $httpHost = $request->getHttpHost();
        $spanId = $request->header('X-B3-SpanId') ?? null;
        $name = "{$method} {$uri}";

        $carrier = array_map(function ($header) {
            return $header[0];
        }, $headers);

        $sampler = PercentageSampler::create($rate);
        $endpoint = Endpoint::create($serviceName);
        $reporter = new Http(Http\CurlFactory::create(), [
            'endpoint_url' => $endpointUrl
        ]);

        $service = $this->app->make(TracingService::class);
        $service->createTracing($endpoint, $sampler, $reporter);

        $tracing = $service->getTracing();

        $tracer = $tracing->getTracer();
        if (empty($spanId)) {
            $defaultSamplingFlags = DefaultSamplingFlags::createAsSampled();
            $span = $tracer->newTrace($defaultSamplingFlags);
        } else {
            $extractor = $tracing->getPropagation()->getExtractor(new Map());
            $extractedContext = $extractor($carrier);
            $span = $tracer->nextSpan($extractedContext);
        }

        $span->start(\Zipkin\Timestamp\now());
        $span->setName($name);
        $span->setKind(\Zipkin\Kind\SERVER);
        $span->annotate('request_started', \Zipkin\Timestamp\now());
        $span->tag(\Zipkin\Tags\HTTP_HOST, $httpHost);
        $span->tag(\Zipkin\Tags\HTTP_METHOD, $method);
        $span->tag(\Zipkin\Tags\HTTP_PATH, $uri);
        $span->tag('http.type', 'http-request');
        $span->tag('http.env', $this->app->environment());

        if (!empty($query) && is_array($query)) {
            $queryParams = Arr::dot($query, 'http.query.');

            foreach ($queryParams as $k => $v) {
                $span->tag($k, $v);
            }
        }

        return $next($request);
    }

    /**
     * 请求结束后开始上报tracing数据
     * @param $request
     * @param $response
     */
    public function terminate($request, $response)
    {
        $tracer = app('trace')->getTracer();

        $span = $tracer->getCurrentSpan();
        $span->annotate('request_finished', \Zipkin\Timestamp\now());
        $span->tag(\Zipkin\Tags\HTTP_STATUS_CODE, $response->getStatusCode());
        if ($response->getStatusCode() >= 400) {
            $span->tag(\Zipkin\Tags\ERROR, $response->getReasonPhrase());
        }

        $span->finish();

        $tracer->flush();
    }
}
