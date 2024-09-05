<?php

namespace Dumbo\Helpers;

use Dumbo\Context;
use Psr\Http\Message\ResponseInterface;

class BearerAuth
{
    private const STATUS_UNAUTHORIZED = 401;
    private const HEADER_AUTHORIZATION = "Authorization";
    private const HEADER_WWW_AUTHENTICATE = "WWW-Authenticate";

    /**
     * Create a middleware that checks for a Bearer token in the Authorization header
     *
     * @param string|array $options The token or options for the bearer authentication middleware
     * @param string|null $failureMessage The message to return when the token is invalid (only for simple token check)
     * @return callable The middleware
     * @throws \InvalidArgumentException If the options are invalid
     */
    public static function bearerAuth(
        $options,
        ?string $failureMessage = null
    ): callable {
        if (is_string($options)) {
            return self::simpleBearerAuth(
                $options,
                $failureMessage ?? "Unauthorized request."
            );
        } elseif (is_array($options)) {
            return self::advancedBearerAuth($options);
        } else {
            throw new \InvalidArgumentException(
                "Invalid options provided for bearer auth middleware"
            );
        }
    }

    private static function simpleBearerAuth(
        string $token,
        string $failureMessage
    ): callable {
        return function (Context $context, callable $next) use (
            $token,
            $failureMessage
        ): ResponseInterface {
            $authHeader = $context->req->header(self::HEADER_AUTHORIZATION);

            if (!$authHeader) {
                return $context->json(
                    ["error" => $failureMessage],
                    self::STATUS_UNAUTHORIZED
                );
            }

            $parts = explode(" ", $authHeader, 2);
            if (count($parts) !== 2 || strtolower($parts[0]) !== "bearer") {
                return $context->json(
                    ["error" => $failureMessage],
                    self::STATUS_UNAUTHORIZED
                );
            }

            if ($parts[1] !== $token) {
                return $context->json(
                    ["error" => $failureMessage],
                    self::STATUS_UNAUTHORIZED
                );
            }

            return $next($context);
        };
    }

    /**
     * Create a middleware for advanced bearer authentication
     *
     * @param array $options The options for advanced bearer authentication
     * @return callable The middleware
     * @throws \InvalidArgumentException If the options are invalid
     */
    private static function advancedBearerAuth(array $options): callable
    {
        if (!isset($options["verifyToken"]) && empty($options["tokens"])) {
            throw new \InvalidArgumentException(
                'Bearer auth middleware requires either "verifyToken" function or "tokens" array'
            );
        }

        $realm = $options["realm"] ?? "API";

        return function (
            Context $context,
            callable $next
        ) use ($options, $realm): ResponseInterface {
            $authHeader = $context->req->header(self::HEADER_AUTHORIZATION);

            if (!$authHeader) {
                return self::unauthorizedResponse(
                    $context,
                    $realm,
                    "Authorization header missing"
                );
            }

            $parts = explode(" ", $authHeader, 2);
            if (count($parts) !== 2 || strtolower($parts[0]) !== "bearer") {
                return self::unauthorizedResponse(
                    $context,
                    $realm,
                    "Invalid Authorization header format"
                );
            }

            $token = $parts[1];

            if (isset($options["verifyToken"])) {
                if ($options["verifyToken"]($token, $context)) {
                    return $next($context);
                }
            } elseif (isset($options["tokens"])) {
                if (in_array($token, $options["tokens"], true)) {
                    return $next($context);
                }
            }

            return self::unauthorizedResponse(
                $context,
                $realm,
                "Invalid token"
            );
        };
    }

    private static function unauthorizedResponse(
        Context $context,
        string $realm,
        string $error
    ): ResponseInterface {
        return $context->json(["error" => $error], self::STATUS_UNAUTHORIZED, [
            self::HEADER_WWW_AUTHENTICATE => sprintf(
                'Bearer realm="%s"',
                $realm
            ),
        ]);
    }
}
