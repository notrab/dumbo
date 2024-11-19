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
     * @param array $options
     * @return Closure
     */
    public static function csrf(array $options = []): Closure
    {
        $options = self::mergeOptions($options);

        self::validateOptions($options);

        if (!is_callable($options['getToken']) || !is_callable($options['setToken'])) {
            throw new InvalidArgumentException('getToken and setToken must be callable');
        }

        if ($options['errorHandler'] !== null && !is_callable($options['errorHandler'])) {
            throw new InvalidArgumentException('errorHandler must be callable');
        }

        return function (Context $ctx, callable $next) use ($options) {
            if (self::isSafeMethod($ctx->req->method())) {
                return self::handleSafeMethod($ctx, $next, $options);
            }


            if (!self::validateToken($ctx, $options)) {
                return self::handleError($ctx, $options['errorHandler']);
            }

            return $next($ctx);
        };
    }

    private static function mergeOptions(array $options): array
    {
        return array_merge([
            'tokenName' => self::TOKEN_NAME,
            'headerName' => self::HEADER_NAME,
            'tokenLength' => self::TOKEN_LENGTH,
            'useHeader' => false,
            'getToken' => null,
            'setToken' => null,
            'errorHandler' => null
        ], $options);
    }

    private static function validateOptions(array $options): void
    {
        if (!is_callable($options['getToken']) || !is_callable($options['setToken'])) {
            throw new InvalidArgumentException('getToken and setToken must be callable');
        }

        if ($options['errorHandler'] !== null && !is_callable($options['errorHandler'])) {
            throw new InvalidArgumentException('errorHandler must be callable');
        }
    }

    private static function isSafeMethod(string $method): bool
    {
        return in_array(strtoupper($method), self::SAFE_METHODS, true);
    }

    private static function handleSafeMethod(Context $ctx, callable $next, array $options): mixed
    {
        $token = $options['getToken']($ctx);

        if (!$token) {
            $token = self::generateToken($options['tokenLength']);
            $options['setToken']($ctx, $token);
        }

        if ($options['useHeader']) {
            $ctx->header($options['headerName'], $token);
        }

        if ($ctx->get($options['tokenName']) === null) {
            $ctx->set($options['tokenName'], $token);
        }

        return $next($ctx);
    }

    private static function validateToken(Context $ctx, array $options): bool
    {
        $storedToken = $options['getToken']($ctx);

        if (!$storedToken) {
            return false;
        }

        $receivedToken = $options['useHeader'] ? $ctx->req->header($options['headerName']) : $ctx->req->body()[$options['tokenName']];

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
