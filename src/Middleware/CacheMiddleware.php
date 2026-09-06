<?php

namespace Dumbo\Middleware;

use Dumbo\Context;
use Psr\Http\Message\ResponseInterface;

class CacheMiddleware
{
    const HTTP_NOT_MODIFIED = 304;

    public static function withHeaders(
        string $type = 'private',
        bool   $mustRevalidate = false,
        int    $maxAge = 86400,
        bool   $strictEtag = false,
    ): callable
    {
        return function (Context $ctx, callable $next) use (
            $type,
            $mustRevalidate,
            $maxAge,
            $strictEtag
        ): ResponseInterface {

            $request = $ctx->req;

            if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
                return $next($ctx);
            }

            $etag = self::generateEtag($ctx, $strictEtag);
            $lastModified = gmdate(DATE_RFC7231);
            $cacheControlHeader = sprintf('%s, max-age=%d%s', $type, $maxAge, $mustRevalidate ? ', must-revalidate' : '');

            $ifNoneMatch = $request->header('If-None-Match');
            $ifModifiedSince = $request->header('If-Modified-Since');

            if ($ifNoneMatch && self::etagMatches($etag, $ifNoneMatch)) {
                return $ctx->getResponse()
                    ->withStatus(self::HTTP_NOT_MODIFIED)
                    ->withHeader('Cache-Control', $cacheControlHeader)
                    ->withHeader('ETag', $etag)
                    ->withHeader('Last-Modified', $lastModified);
            }

            if ($ifModifiedSince && strtotime($ifModifiedSince) >= strtotime($lastModified)) {
                return $ctx->getResponse()
                    ->withStatus(self::HTTP_NOT_MODIFIED)
                    ->withHeader('Cache-Control', $cacheControlHeader)
                    ->withHeader('ETag', $etag)
                    ->withHeader('Last-Modified', $lastModified);
            }

            $response = $next($ctx);

            if (!$response->hasHeader('Cache-Control')) {
                $response = $response->withHeader('Cache-Control', $cacheControlHeader);
            }

            return $response
                ->withHeader('ETag', $etag)
                ->withHeader('Last-Modified', $lastModified);
        };
    }

    /**
     * Check an ETag against an If-None-Match header, which may hold a
     * comma separated list of candidates or the "*" wildcard.
     */
    private static function etagMatches(string $etag, string $ifNoneMatch): bool
    {
        $candidates = array_map('trim', explode(',', $ifNoneMatch));

        if (in_array('*', $candidates, true)) {
            return true;
        }

        $weak = static fn(string $value): string => ltrim($value, 'W/');

        foreach ($candidates as $candidate) {
            if ($weak($candidate) === $weak($etag)) {
                return true;
            }
        }

        return false;
    }

    private static function generateEtag(Context $ctx, bool $strict): string
    {
        $identifier = $strict
            ? $ctx->req->method() . $ctx->req->path() . serialize($ctx->req->query())
            : $ctx->req->path();

        return sprintf('W/"%s"', md5($identifier));
    }
}
