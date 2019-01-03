<?php
namespace LaravelCloud\TraceTests\Trace;

class TracingServiceProviderTest
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