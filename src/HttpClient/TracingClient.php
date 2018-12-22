<?php

namespace LaravelCloud\Trace\HttpClient;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

class TracingClient extends Client
{
    public function __construct(array $config = [])
    {
        $stack = HandlerStack::create();
        $stack->unshift(TracingHandlerStack::start(), 'lar_tracing_start');

        $config = empty($config) ? [] : $config;
        $config['handler'] = $stack;

        parent::__construct($config);
    }
}
