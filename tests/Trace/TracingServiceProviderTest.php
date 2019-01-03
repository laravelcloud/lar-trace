<?php
namespace LaravelCloud\TraceTests\Trace;

class TracingServiceProviderTest extends \Orchestra\Testbench\TestCase
{
    public function testIsBound()
    {
        $this->assertEquals(app()->bound('trace'), true);
    }

    /**
     * @depends testIsBound
     */
    public function testEnvironment()
    {
        $this->assertEquals(app('trace')->environment, 'testing');
    }

}