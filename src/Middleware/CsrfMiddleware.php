<?php

namespace Dumbo\Middleware;

use Closure;
use Dumbo\Context;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;


class CsrfMiddleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];
    private const TOKEN_LENGTH = 32;
    private const TOKEN_NAME = 'csrf_token';
    private const HEADER_NAME = 'X-CSRF-TOKEN';

    /**
     * @param array $options{tokenName: string, headerName: string, tokenLength: int, useHeader: bool, getToken: callable, setToken: callable, errorHandler: callable}
     * @return Closure
     */
    public static function csrf(array $options = []): Closure
    {
        $tokenName = $options['tokenName'] ?? self::TOKEN_NAME;
        $headerName = $options['headerName'] ?? self::HEADER_NAME;
        $tokenLength = $options['tokenLength'] ?? self::TOKEN_LENGTH;
        $useHeader = $options['useHeader'] ?? false;
        $getToken = $options['getToken'] ?? null;
        $setToken = $options['setToken'] ?? null;
        $errorHandler = $options['errorHandler'] ?? null;

        if (!is_callable($getToken) || !is_callable($setToken)) {
            throw new InvalidArgumentException('getToken and setToken must be callable');
        }

        if ($errorHandler !== null && !is_callable($errorHandler)) {
            throw new InvalidArgumentException('errorHandler must be callable');
        }

        return function (Context $ctx, callable $next) use ($tokenName, $headerName, $useHeader, $getToken, $setToken, $errorHandler, $tokenLength) {
            if (self::isSafeMethod($ctx->req->method())) {
                return self::handleSafeMethod($ctx, $next, $tokenName, $headerName, $useHeader, $getToken, $setToken, $tokenLength);
            }


            if (!self::validateToken($ctx, $tokenName, $headerName, $useHeader, $getToken)) {
                return self::handleError($ctx, $errorHandler);
            }

            return $next($ctx);
        };
    }

    private static function isSafeMethod(string $method): bool
    {
        return in_array(strtoupper($method), self::SAFE_METHODS, true);
    }

    private static function handleSafeMethod(Context $ctx, callable $next, string $tokenName, string $headerName, bool $useHeader, callable $getToken, callable $setToken, int $tokenLength): mixed
    {
        $token = $getToken($ctx);

        if (!$token) {
            $token = self::generateToken($tokenLength);
            $setToken($ctx, $token);
        }

        if ($useHeader) {
            $ctx->header($headerName, $token);
        }

        return $next($ctx);
    }

    private static function validateToken(Context $ctx, string $tokenName, string $headerName, bool $useHeader, callable $getToken): bool
    {
        $storedToken = $getToken($ctx);

        if (!$storedToken) {
            return false;
        }

        $receivedToken = $useHeader ? $ctx->req->header($headerName) : $ctx->req->body()[$tokenName];

        return $receivedToken !== null && hash_equals($storedToken, $receivedToken);
    }

    private static function generateToken(int $length): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    private static function handleError(Context $ctx, ?callable $errorHandler): ResponseInterface
    {
        if ($errorHandler) {
            return $errorHandler($ctx);
        }

        return $ctx->json([
            'error' => 'Invalid CSRF token',
        ], 403);
    }
}