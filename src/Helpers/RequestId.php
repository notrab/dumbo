<?php

namespace Dumbo\Helpers;

use Dumbo\Context;
use Psr\Http\Message\ResponseInterface;

class RequestId
{
    private const DEFAULT_HEADER_NAME = "X-Request-Id";
    private const DEFAULT_LIMIT_LENGTH = 255;

    /**
     * Create a middleware that generates and sets a unique request ID
     *
     * @param array{
     *     headerName?: string,
     *     limitLength?: int,
     *     generator?: callable(Context): string
     * } $options Configuration options
     * @return callable(Context, callable): ResponseInterface The middleware
     */
    public static function requestId(array $options = []): callable
    {
        $headerName = $options["headerName"] ?? self::DEFAULT_HEADER_NAME;
        $limitLength = $options["limitLength"] ?? self::DEFAULT_LIMIT_LENGTH;
        $generator = $options["generator"] ?? null;

        return function (
            Context $context,
            callable $next
        ) use ($headerName, $limitLength, $generator): ResponseInterface {
            $requestId = $context->req->header($headerName);

            if (!$requestId) {
                $requestId = $generator
                    ? $generator($context)
                    : self::generateRequestId();
                $requestId = substr($requestId, 0, $limitLength);
            }

            $context->set("requestId", $requestId);

            $response = $next($context);

            return $response->withHeader($headerName, $requestId);
        };
    }

    /**
     * Generate a unique request ID
     *
     * @return string The generated request ID
     */
    private static function generateRequestId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
