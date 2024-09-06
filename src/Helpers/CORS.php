<?php

namespace Dumbo\Helpers;

use Dumbo\Context;
use Psr\Http\Message\ResponseInterface;

class CORS
{
    /**
     * Configure CORS headers.
     *
     * @param array $$options optional - The CORS options
     * @return callable The middleware function for handling CORS.
     */
    public static function cors(array $options = []): callable
    {
        $defaultOptions = [
            "origin" => "*",
            "allow_methods" => [
                "GET",
                "HEAD",
                "PUT",
                "PATCH",
                "POST",
                "DELETE",
            ],
            "allow_headers" => [
                "X-Requested-With",
                "Content-Type",
                "Accept",
                "Origin",
                "Authorization",
            ],
            "expose_headers" => [],
            "credentials" => false,
            "max_age" => 86400,
        ];

        $corsOptions = array_merge($defaultOptions, $options);

        return function (
            Context $context,
            callable $next
        ) use ($corsOptions): ResponseInterface {
            $request = $context->req;
            $origin = $request->header("Origin");

            if (!$origin) {
                return $next($context);
            }

            $allowedOrigin = self::getAllowedOrigin(
                $corsOptions["origin"],
                $origin,
                $context
            );

            if ($request->method() === "OPTIONS") {
                $response = $context->text("", 204);
                $response = $response->withHeader(
                    "Access-Control-Allow-Origin",
                    $allowedOrigin
                );

                $requestMethod = $request->header(
                    "Access-Control-Request-Method"
                );
                if (
                    $requestMethod &&
                    in_array($requestMethod, $corsOptions["allow_methods"])
                ) {
                    $response = $response->withHeader(
                        "Access-Control-Allow-Methods",
                        $requestMethod
                    );
                } else {
                    $response = $response->withHeader(
                        "Access-Control-Allow-Methods",
                        implode(", ", $corsOptions["allow_methods"])
                    );
                }

                $requestHeaders = $request->header(
                    "Access-Control-Request-Headers"
                );
                if ($requestHeaders) {
                    $response = $response->withHeader(
                        "Access-Control-Allow-Headers",
                        $requestHeaders
                    );
                } elseif (!empty($corsOptions["allow_headers"])) {
                    $response = $response->withHeader(
                        "Access-Control-Allow-Headers",
                        implode(", ", $corsOptions["allow_headers"])
                    );
                }

                if ($corsOptions["max_age"] !== null) {
                    $response = $response->withHeader(
                        "Access-Control-Max-Age",
                        (string) $corsOptions["max_age"]
                    );
                }
            } else {
                $response = $next($context);
                $response = $response->withHeader(
                    "Access-Control-Allow-Origin",
                    $allowedOrigin
                );

                if (!empty($corsOptions["expose_headers"])) {
                    $response = $response->withHeader(
                        "Access-Control-Expose-Headers",
                        implode(", ", $corsOptions["expose_headers"])
                    );
                }
            }

            if ($corsOptions["credentials"]) {
                $response = $response->withHeader(
                    "Access-Control-Allow-Credentials",
                    "true"
                );
            }

            return $response;
        };
    }

    /**
     * Handle preflight requests
     *
     * @param ResponseInterface $response The current response
     * @param array $options CORS configuration options
     * @return ResponseInterface The updated response
     */
    private static function handlePreflightRequest(
        ResponseInterface $response,
        array $options
    ): ResponseInterface {
        $response = $response->withHeader(
            "Access-Control-Allow-Methods",
            implode(", ", $options["allow_methods"])
        );

        if (!empty($options["allow_headers"])) {
            $response = $response->withHeader(
                "Access-Control-Allow-Headers",
                implode(", ", $options["allow_headers"])
            );
        }

        if (isset($options["max_age"])) {
            $response = $response->withHeader(
                "Access-Control-Max-Age",
                (string) $options["max_age"]
            );
        }

        return $response;
    }

    /**
     * Get the allowed origin based on the configuration
     *
     * @param string|array|callable $originConfig The origin configuration
     * @param string $requestOrigin The origin of the request
     * @param Context $context The request context
     * @return string The allowed origin
     */
    private static function getAllowedOrigin(
        $originConfig,
        string $requestOrigin,
        Context $context
    ): string {
        if (is_callable($originConfig)) {
            return $originConfig($requestOrigin, $context);
        }

        if (is_array($originConfig)) {
            return in_array($requestOrigin, $originConfig)
                ? $requestOrigin
                : "";
        }

        return $originConfig;
    }
}
