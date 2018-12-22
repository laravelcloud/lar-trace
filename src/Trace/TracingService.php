<?php

namespace LaravelCloud\Trace\Trace;

use Zipkin\Span;
use Zipkin\Tracing;
use Zipkin\Endpoint;
use Zipkin\TracingBuilder;
use Zipkin\Reporters\Http;
use Zipkin\Propagation\Map;
use Zipkin\Samplers\PercentageSampler;

class TracingService
{
    /**
     * @var Tracing
     */
    private $tracing;

    /**
     * @var Span
     */
    private $globalSpan;

    public function createTracing($config) : Tracing
    {
        $rate           = $config['rate'];
        $endpointUrl    = $config['endpoint_url'];
        $serviceName    = $config['service_name'] ?? null;

        $sampler = PercentageSampler::create($rate);
        $endpoint = Endpoint::create($serviceName);
        $reporter = new Http(Http\CurlFactory::create(), [
            'endpoint_url' => $endpointUrl
        ]);

        $this->tracing = TracingBuilder::create()
            ->havingLocalEndpoint($endpoint)
            ->havingLocalServiceName($serviceName)
            ->havingSampler($sampler)
            ->havingReporter($reporter)
            ->build();

        return $this->tracing;
    }

    public function getTracing(): Tracing
    {
        return $this->tracing;
    }

    public function createGlobalSpan($carrier): Span
    {
        $carrier = $carrier ?: [];
        $extractor = $this->getTracing()->getPropagation()->getExtractor(new Map());
        $extractedContext = $extractor($carrier);
        $this->globalSpan = $this->getTracing()->getTracer()->nextSpan($extractedContext);

        return $this->globalSpan;
    }

    public function getGlobalSpan(): Span
    {
        return $this->globalSpan;
    }

    public function setGlobalTags($tags = [])
    {
        foreach ((array)$tags as $k => $v) {
            $this->getGlobalSpan()->tag($k, $v);
        }
    }

    public function newChild($name, $tags = []): Span
    {
        $tracer = $this->getTracing()->getTracer();
        $childSpan = $tracer->newChild($this->getGlobalSpan()->getContext());
        $childSpan->setKind(\Zipkin\Kind\CLIENT);
        $childSpan->setName($name);

        foreach ((array)$tags as $key => $tag)
        {
            $childSpan->tag($key, (string)$tag);
        }

        return $childSpan;
    }

    public function record($payload)
    {

    }
}
