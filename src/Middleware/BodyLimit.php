<?php

namespace Dumbo\Middleware;

use Dumbo\Context;
use Dumbo\HTTPException;
use Psr\Http\Message\ResponseInterface;

class BodyLimit
{
    /**
     * Create a middleware that rejects requests whose Content-Length exceeds
     * $maxSize bytes, with $onError($context, $maxSize) or a 413 HTTPException.
     */
    public static function bodyLimit(int $maxSize, ?callable $onError = null): callable
    {
        return function (Context $context, callable $next) use ($maxSize, $onError): ResponseInterface {
            $contentLength = $context->req->header("Content-Length");

            if ($contentLength === null || (int) $contentLength <= $maxSize) {
                return $next($context);
            }

            if ($onError !== null) {
                return $onError($context, $maxSize);
            }

            throw new HTTPException(
                413,
                "Request entity too large. The body must not be larger than $maxSize bytes.",
                "BODY_TOO_LARGE",
                ["max_size" => $maxSize]
            );
        };
    }
}
