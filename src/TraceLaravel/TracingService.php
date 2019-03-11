<?php

namespace LaravelCloud\Trace\TraceLaravel;

use Zipkin\Span;
use Zipkin\Tracing;
use Zipkin\Endpoint;
use Zipkin\TracingBuilder;
use Zipkin\Reporters\Http;
use Zipkin\Propagation\Getter;
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
        $rate           = (float)$config['rate'];
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

    /**
     * @return Tracing
     */
    public function getTracing(): Tracing
    {
        return $this->tracing;
    }

    /**
     * @param $carrier
     * @param Getter $getter
     * @return Span
     */
    public function createGlobalSpan($carrier, Getter $getter): Span
    {
        $extractor = $this->getTracing()->getPropagation()->getExtractor($getter);
        $extractedContext = $extractor($carrier);
        $this->globalSpan = $this->getTracing()->getTracer()->nextSpan($extractedContext);

        return $this->globalSpan;
    }

    /**
     * @return Span
     */
    public function getGlobalSpan(): Span
    {
        return $this->globalSpan;
    }

    /**
     * new child
     * @param $name
     * @param array $tags
     * @return Span
     */
    public function newChild($name, $tags = []): Span
    {
        $tracer = $this->getTracing()->getTracer();
        $childSpan = $tracer->newChild($this->getGlobalSpan()->getContext());
        $childSpan->setKind(\Zipkin\Kind\CLIENT);
        $childSpan->setName($name);

        foreach ((array)$tags as $key => $tag) {
            $childSpan->tag($key, (string)$tag);
        }

        return $childSpan;
    }
}
