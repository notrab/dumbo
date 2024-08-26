<?php

namespace Dumbo\Helpers;

use Dumbo\Context;

class BearerAuth
{
    /**
     * Create a middleware that checks for a Bearer token in the Authorization header
     *
     * @param string $token The expected Bearer token
     * @return callable The middleware
     */
    public static function bearer(string $token): callable
    {
        return function (Context $ctx, callable $next) use ($token) {
            $authHeader = $ctx->req->header("Authorization");
            if (!$authHeader) {
                return $ctx->json(
                    ["error" => "Authorization header missing"],
                    401
                );
            }

            $parts = explode(" ", $authHeader);
            if (count($parts) !== 2 || strtolower($parts[0]) !== "bearer") {
                return $ctx->json(
                    ["error" => "Invalid Authorization header format"],
                    401
                );
            }

            if ($parts[1] !== $token) {
                return $ctx->json(["error" => "Invalid token"], 401);
            }

            return $next($ctx);
        };
    }
}
