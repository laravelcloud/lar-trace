<?php
namespace LaravelCloud\Trace\TraceLaravel;

use Illuminate\Support\Facades\Facade;
use LaravelCloud\Trace\Trace\TracingService;

class TracingFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return TracingService::class;
    }
}
