<?php

namespace Dumbo\Middleware;

use Closure;
use Dumbo\Context;

class CsrfMiddleware
{
    const SAFE_METHODS = [
        'GET',
        'HEAD',
        'OPTIONS',
        'TRACE'
    ];

    const FORM_CONTENT_TYPES = [
        'application/x-www-form-urlencoded',
        'multipart/form-data',
        'text/plain'
    ];

    public static function csrf(array $options = []): Closure
    {
        return function (Context $ctx, callable $next) use ($options) {
            $method = $ctx->req->method();
            $contentType = $ctx->req->header('Content-Type');
            $origin = $ctx->req->header('Origin');

            if (self::isSafeMethod($method)) {
                return $next($ctx);
            }

            if (self::isFormRequest($contentType) && !self::isAllowedOrigin($origin, $ctx, $options)) {
                return $ctx->text('Forbidden ', 403);
            }

            return $next($ctx);
        };
    }

    private static function isSafeMethod(?string $method): bool
    {
        return !($method == null) && in_array(strtoupper($method), self::SAFE_METHODS);
    }

    private static function isFormRequest(string $contentType): bool
    {
        return in_array($contentType, self::FORM_CONTENT_TYPES);
    }

    private static function isAllowedOrigin(?string $origin, Context $ctx, array $options): bool
    {
        if ($origin === null) {
            return false;
        }

        if (!isset($options['origin'])) {
            return $origin === self::getRequestOrigin($ctx);
        }

        $allowedOrigins = $options['origin'];

        if (is_string($allowedOrigins)) {
            return $origin === $allowedOrigins;
        }

        if (is_array($allowedOrigins)) {
            return in_array($origin, $allowedOrigins);
        }

        if (is_callable($allowedOrigins)) {
            return $allowedOrigins($origin, $ctx);
        }

        return false;
    }

    private static function getRequestOrigin(Context $ctx): string
    {
        $url = $ctx->req->routePath();
        $parsedUrl = parse_url($url);
        dd( $parsedUrl['scheme'] . '://' . $parsedUrl['host']);
        return $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
    }

}