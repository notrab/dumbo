<?php

namespace Dumbo\Middleware;

use Closure;
use Dumbo\Context;
use InvalidArgumentException;

class CsrfMiddleware
{
    private const SAFE_METHODS = ["GET", "HEAD", "OPTIONS", "TRACE"];

    /**
     * Create a middleware that issues a CSRF token on safe requests and
     * checks it on every other request.
     *
     * Options:
     *  - getToken: callable(Context): ?string (required) reads the stored token
     *  - setToken: callable(Context, string) (required) stores a new token
     *  - tokenName: body field and context variable name (default "csrf_token")
     *  - headerName: header to send and read the token in (default "X-CSRF-TOKEN")
     *  - useHeader: read the token from the header instead of the body
     *  - tokenLength: length of generated tokens (default 32)
     *  - errorHandler: callable(Context): ResponseInterface for invalid tokens
     *
     * @throws InvalidArgumentException If the options are invalid
     */
    public static function csrf(array $options = []): Closure
    {
        $options = array_merge([
            "tokenName" => "csrf_token",
            "headerName" => "X-CSRF-TOKEN",
            "tokenLength" => 32,
            "useHeader" => false,
            "getToken" => null,
            "setToken" => null,
            "errorHandler" => null,
        ], $options);

        if (!is_callable($options["getToken"]) || !is_callable($options["setToken"])) {
            throw new InvalidArgumentException("getToken and setToken must be callable");
        }

        if ($options["errorHandler"] !== null && !is_callable($options["errorHandler"])) {
            throw new InvalidArgumentException("errorHandler must be callable");
        }

        return function (Context $context, callable $next) use ($options) {
            $token = $options["getToken"]($context);

            if (in_array(strtoupper($context->req->method()), self::SAFE_METHODS, true)) {
                if (!$token) {
                    $token = bin2hex(random_bytes(max(1, intdiv($options["tokenLength"], 2))));
                    $options["setToken"]($context, $token);
                }

                if ($options["useHeader"]) {
                    $context->header($options["headerName"], $token);
                }

                if ($context->get($options["tokenName"]) === null) {
                    $context->set($options["tokenName"], $token);
                }

                return $next($context);
            }

            $received = $options["useHeader"]
                ? $context->req->header($options["headerName"])
                : $context->req->body()[$options["tokenName"]] ?? null;

            if ($token && is_string($received) && hash_equals($token, $received)) {
                return $next($context);
            }

            return $options["errorHandler"]
                ? $options["errorHandler"]($context)
                : $context->json(["error" => "Invalid CSRF token"], 403);
        };
    }
}
