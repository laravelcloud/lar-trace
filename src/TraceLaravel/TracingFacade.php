<?php
namespace LaravelCloud\Trace\TraceLaravel;

use Illuminate\Support\Facades\Facade;

class TracingFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return TracingService::class;
    }
}
