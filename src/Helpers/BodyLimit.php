<?php

namespace Dumbo\Helpers;

use Dumbo\Context;
use Dumbo\HTTPException;
use Psr\Http\Message\ResponseInterface;

class BodyLimit
{
    /**
     * Create a middleware that limits the size of the request body
     *
     * @param int $maxSize The maximum allowed size of the request body in bytes
     * @param callable|null $onError Custom error handler (optional)
     * @return callable The middleware
     */
    public static function limit(
        int $maxSize,
        callable $onError = null
    ): callable {
        return function (Context $context, callable $next) use (
            $maxSize,
            $onError
        ) {
            $contentLength = $context->req->header("Content-Length");

            if ($contentLength !== null && (int) $contentLength > $maxSize) {
                return self::handleError($context, $maxSize, $onError);
            }

            return $next($context);
        };
    }

    /**
     * Handle the error when the request body is too large
     *
     * @param Context $context The request context
     * @param int $maxSize The maximum allowed size of the request body in bytes
     * @param callable|null $onError Custom error handler (optional)
     * @return ResponseInterface The response
     *
     * If no custom error handler is provided, this method will throw an HTTPException
     * with status code 413, reason phrase "Request entity too large", and error code
     * "BODY_TOO_LARGE". The exception will also have a "max_size" parameter set to
     * the provided $maxSize value.
     */
    private static function handleError(
        Context $context,
        int $maxSize,
        callable $onError = null
    ): ResponseInterface {
        if ($onError !== null) {
            return $onError($context, $maxSize);
        }

        throw new HTTPException(
            413,
            "Request entity too large. The body must not be larger than $maxSize bytes.",
            "BODY_TOO_LARGE",
            ["max_size" => $maxSize]
        );
    }
}
