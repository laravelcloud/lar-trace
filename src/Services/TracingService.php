<?php
namespace LaravelCloud\Trace\Services;

use Zipkin\Tracing;
use Zipkin\TracingBuilder;

class TracingService
{
    private $tracing;

    public function createTracing($endpoint, $sampler, $reporter) {

        $this->tracing = TracingBuilder::create()
            ->havingLocalEndpoint($endpoint)
            ->havingSampler($sampler)
            ->havingReporter($reporter)
            ->build();
    }

    public function getTracing(): Tracing
    {
        return $this->tracing;
    }
}