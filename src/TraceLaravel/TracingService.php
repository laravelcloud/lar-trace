<?php

namespace LaravelCloud\Trace\TraceLaravel;

use Zipkin\Endpoint;
use Zipkin\Reporter;
use Zipkin\Reporters\Http;
use Zipkin\Sampler;
use Zipkin\Samplers\PercentageSampler;
use Zipkin\Span;
use Zipkin\Tracing;
use Zipkin\TracingBuilder;

class TracingService
{
    protected $carrier;
    protected $getter;
    protected $config;
    /**
     * @var Tracing
     */
    private $tracing;
    /**
     * @var Span
     */
    private $trace;

    public function __construct(array $config, $carrier, $getter)
    {
        $this->config = $config;
        $this->getter = $getter;
        $this->carrier = $carrier;

        $this->createTracing();
    }

    /**
     * @return Tracing
     */
    public function createTracing(): Tracing
    {
        return $this->tracing = TracingBuilder::create()
            ->havingLocalEndpoint($this->getEndpoint())
            ->havingSampler($this->getSampler())
            ->havingReporter($this->getReporter())
            ->build();
    }

    /**
     * @return Endpoint
     */
    public function getEndpoint(): Endpoint
    {
        return Endpoint::create($this->config['service_name'] ?? null);
    }

    /**
     * @return Sampler
     */
    protected function getSampler(): Sampler
    {
        return PercentageSampler::create((float)$this->config['rate']);
    }

    /**
     * @return Reporter
     */
    protected function getReporter(): Reporter
    {
        return new Http(Http\CurlFactory::create(), [
            'endpoint_url' => $this->config['endpoint_url']
        ]);
    }

    /**
     * @return Span
     */
    public function createTrace(): Span
    {
        $extractor = $this->getTracing()->getPropagation()->getExtractor($this->getter);
        $extractedContext = $extractor($this->carrier);
        return $this->trace = $this->getTracing()->getTracer()->nextSpan($extractedContext);
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
     */
    public function injector($trace, &$carrier)
    {
        if ($trace instanceof Span) {
            $injector = $this->getTracing()->getPropagation()->getInjector($this->getter);
            $injector($trace->getContext(), $carrier);
        }
    }

    public function hasTrace()
    {
        return empty($this->getTrace());
    }

    /**
     * @return Span
     */
    public function getTrace(): Span
    {
        return $this->trace;
    }

    /**
     * @return mixed
     */
    public function getCarrier()
    {
        return $this->carrier;
    }

    /**
     * @param $name
     * @param array $tags
     * @return Span
     */
    public function newChild($name, $tags = []): Span
    {
        $childSpan = $this->getTracing()->getTracer()->newChild($this->getTrace()->getContext());
        $childSpan->setKind(\Zipkin\Kind\CLIENT);
        $childSpan->setName($name);

        foreach ((array)$tags as $key => $tag) {
            $childSpan->tag($key, (string)$tag);
        }

        return $childSpan;
    }

    /**
     * flush
     */
    public function flush()
    {
        $this->getTracing()->getTracer()->flush();
    }
}
