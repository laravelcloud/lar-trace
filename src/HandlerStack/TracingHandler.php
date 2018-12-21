<?php
namespace LaravelCloud\Trace\HandlerStack;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 携带zipkin trace的header
 */
class TracingHandler
{
    public static function start()
    {
        return function (callable $handler) {

            return function (RequestInterface $request, array $options) use ($handler) {

                foreach ((array)getallheaders() as $name => $value) {
                    if (strtoupper(substr($name,0, 5)) == 'X-B3-')
                        $request = $request->withHeader($name, $value);
                }

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($request, $handler) {
                        return $response;
                    }
                );
            };
        };
    }

}