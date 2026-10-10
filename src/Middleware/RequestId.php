<?php

namespace Dumbo\Middleware;

use Dumbo\Context;
use Psr\Http\Message\ResponseInterface;

class RequestId
{
    /**
     * Create a middleware that reuses the incoming request ID header or
     * generates one, stores it as the "requestId" context variable and echoes
     * it on the response.
     *
     * @param array{headerName?: string, limitLength?: int, generator?: callable(Context): string} $options
     */
    public static function requestId(array $options = []): callable
    {
        $headerName = $options["headerName"] ?? "X-Request-Id";
        $limitLength = $options["limitLength"] ?? 255;
        $generator = $options["generator"] ?? fn() => bin2hex(random_bytes(16));

        return function (Context $context, callable $next) use ($headerName, $limitLength, $generator): ResponseInterface {
            $requestId = $context->req->header($headerName) ?: substr($generator($context), 0, $limitLength);

            $context->set("requestId", $requestId);

            return $next($context)->withHeader($headerName, $requestId);
        };
    }
}
