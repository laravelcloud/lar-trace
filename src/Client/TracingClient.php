<?php
namespace LaravelCloud\Trace\Client;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use LaravelCloud\Trace\HandlerStack\TracingHandler;

class TracingClient extends Client
{

    public function __construct(array $config = [])
    {
        $stack = HandlerStack::create();
        $stack->unshift(TracingHandler::start($config), 'lar_tracing_start');

        $config = empty($config) ? [] : $config;
        $config['handler'] = $stack;

        parent::__construct($config);
    }

}