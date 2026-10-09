<?php

namespace Dumbo\Helpers;

use Dumbo\Context;
use Psr\Http\Message\ResponseInterface;

class CORS
{
    /**
     * Create a middleware that adds CORS headers and answers preflight requests.
     *
     * `origin` may be "*", a single origin, an array of allowed origins, or a
     * callable receiving (string $origin, Context $context) and returning the
     * origin to allow (or null to deny).
     */
    public static function cors(array $options = []): callable
    {
        $options = array_merge(
            [
                "origin" => "*",
                "allow_methods" => ["GET", "HEAD", "PUT", "PATCH", "POST", "DELETE"],
                "allow_headers" => ["X-Requested-With", "Content-Type", "Accept", "Origin", "Authorization"],
                "expose_headers" => [],
                "credentials" => false,
                "max_age" => 86400,
            ],
            $options
        );

        return function (Context $context, callable $next) use (
            $options
        ): ResponseInterface {
            $request = $context->req;
            $origin = $request->header("Origin");

            if (!$origin) {
                return $next($context);
            }

            $headers = [];

            if ($request->method() === "OPTIONS") {
                $requestMethod = $request->header("Access-Control-Request-Method");
                $requestHeaders = $request->header("Access-Control-Request-Headers");

                $headers["Access-Control-Allow-Methods"] =
                    $requestMethod && in_array($requestMethod, $options["allow_methods"], true)
                        ? $requestMethod
                        : implode(", ", $options["allow_methods"]);

                $allowHeaders = $requestHeaders ?: implode(", ", $options["allow_headers"]);

                if ($allowHeaders !== "") {
                    $headers["Access-Control-Allow-Headers"] = $allowHeaders;
                }

                if ($options["max_age"] !== null) {
                    $headers["Access-Control-Max-Age"] = (string) $options["max_age"];
                }

                $response = $context->text("", 204);
            } else {
                if (!empty($options["expose_headers"])) {
                    $headers["Access-Control-Expose-Headers"] = implode(", ", $options["expose_headers"]);
                }

                $response = $next($context);
            }

            $allowedOrigin = self::allowedOrigin($options["origin"], $origin, $context);

            if ($allowedOrigin) {
                $headers["Access-Control-Allow-Origin"] = $allowedOrigin;
            }

            if ($options["credentials"]) {
                $headers["Access-Control-Allow-Credentials"] = "true";
            }

            foreach ($headers as $name => $value) {
                $response = $response->withHeader($name, $value);
            }

            return $options["origin"] === "*"
                ? $response
                : $response->withAddedHeader("Vary", "Origin");
        };
    }

    private static function allowedOrigin(
        string|array|callable $config,
        string $origin,
        Context $context
    ): ?string {
        if (is_callable($config)) {
            return $config($origin, $context);
        }

        if (is_array($config)) {
            return in_array($origin, $config, true) ? $origin : null;
        }

        return $config;
    }
}
