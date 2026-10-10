<?php

namespace Dumbo\Middleware;

use Dumbo\Context;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;

class Cache
{
    /**
     * Create a middleware that adds Cache-Control and a weak ETag (derived from
     * the response body) to GET and HEAD responses, and answers conditional
     * requests with 304 Not Modified.
     *
     * @param array{type?: string, maxAge?: int, mustRevalidate?: bool} $options
     */
    public static function cache(array $options = []): callable
    {
        $cacheControl = sprintf(
            "%s, max-age=%d%s",
            $options["type"] ?? "private",
            $options["maxAge"] ?? 86400,
            ($options["mustRevalidate"] ?? false) ? ", must-revalidate" : ""
        );

        return function (Context $context, callable $next) use (
            $cacheControl
        ): ResponseInterface {
            if (!in_array($context->req->method(), ["GET", "HEAD"], true)) {
                return $next($context);
            }

            $response = $next($context);

            // Hashing a body that can't be rewound, like a stream, would consume it
            if ($response->getStatusCode() !== 200 || !$response->getBody()->isSeekable()) {
                return $response;
            }

            if (!$response->hasHeader("Cache-Control")) {
                $response = $response->withHeader("Cache-Control", $cacheControl);
            }

            if (!$response->hasHeader("ETag")) {
                $etag = sprintf('W/"%s"', md5((string) $response->getBody()));
                $response = $response->withHeader("ETag", $etag);
            }

            return self::notModified($context, $response)
                ? $response->withStatus(304)->withBody(Utils::streamFor(""))
                : $response;
        };
    }

    private static function notModified(
        Context $context,
        ResponseInterface $response
    ): bool {
        $ifNoneMatch = $context->req->header("If-None-Match");

        // If-None-Match takes precedence over If-Modified-Since (RFC 9110)
        if ($ifNoneMatch !== null) {
            $etag = self::opaqueTag($response->getHeaderLine("ETag"));

            foreach (explode(",", $ifNoneMatch) as $candidate) {
                $candidate = trim($candidate);

                if ($candidate === "*" || self::opaqueTag($candidate) === $etag) {
                    return true;
                }
            }

            return false;
        }

        $ifModifiedSince = strtotime($context->req->header("If-Modified-Since") ?? "");
        $lastModified = strtotime($response->getHeaderLine("Last-Modified"));

        return $ifModifiedSince && $lastModified && $ifModifiedSince >= $lastModified;
    }

    /**
     * Strip the weak indicator so tags compare weakly.
     */
    private static function opaqueTag(string $tag): string
    {
        return str_starts_with($tag, "W/") ? substr($tag, 2) : $tag;
    }
}
