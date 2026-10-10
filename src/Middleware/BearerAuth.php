<?php

namespace Dumbo\Middleware;

use Dumbo\Context;
use Psr\Http\Message\ResponseInterface;

class BearerAuth
{
    /**
     * Create a middleware that requires a Bearer token.
     *
     * Pass a single token, or an array of options:
     *  - tokens: array of valid tokens
     *  - verifyToken: callable(string $token, Context $context): bool
     *  - realm: string (default "API")
     *  - message: string to respond with on failure
     *
     * @throws \InvalidArgumentException If the options are invalid
     */
    public static function bearerAuth(string|array $options, ?string $failureMessage = null): callable
    {
        if (is_string($options)) {
            $options = [
                "tokens" => [$options],
                "message" => $failureMessage ?? "Unauthorized request.",
            ];
        }

        if (!isset($options["verifyToken"]) && empty($options["tokens"])) {
            throw new \InvalidArgumentException(
                'Bearer auth middleware requires either "verifyToken" function or "tokens" array'
            );
        }

        $realm = $options["realm"] ?? "API";
        $message = $options["message"] ?? null;
        $verify = $options["verifyToken"] ?? function (string $token) use ($options): bool {
            foreach ($options["tokens"] as $validToken) {
                if (hash_equals($validToken, $token)) {
                    return true;
                }
            }

            return false;
        };

        return function (Context $context, callable $next) use ($realm, $message, $verify): ResponseInterface {
            $header = $context->req->header("Authorization");

            if (preg_match('/^Bearer\s+(.+)$/i', $header ?? "", $matches) && $verify($matches[1], $context)) {
                return $next($context);
            }

            $error = $message ?? ($header ? "Invalid token" : "Authorization header missing");

            return $context->json(["error" => $error], 401, [
                "WWW-Authenticate" => sprintf('Bearer realm="%s"', $realm),
            ]);
        };
    }
}
