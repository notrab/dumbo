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
        return function (Context $ctx, callable $next) use ($type, $mustRevalidate, $maxAge, $strictEtag): ResponseInterface {

            $request = $ctx->req;

            if ($request->method() != 'GET') {
                return $next($ctx);
            }

            $etag = $request->header('If-None-Match') ?: sprintf('"W/%s"', md5($ctx->req->getUri()));
            $lastModified = gmdate('D, d M Y H:i:s') . ' GMT';
            $cacheControlHeader = sprintf('%s, max-age=%d%s', $type, $maxAge, $mustRevalidate ? ', must-revalidate' : '');

            $ifModifiedSince = $request->header('If-Modified-Since');

            if ($etag === $request->header('If-None-Match') || ($ifModifiedSince && strtotime($ifModifiedSince) >= strtotime($lastModified))) {
                return $ctx->getResponse()
                    ->withStatus(self::HTTP_NOT_MODIFIED)
                    ->withHeader('Cache-Control', $cacheControlHeader)
                    ->withHeader('ETag', $etag)
                    ->withHeader('Last-Modified', $lastModified);
            }

            $nextResponse = $next($ctx);

            if (!$nextResponse->hasHeader('Cache-Control')) {
                $nextResponse = $nextResponse->withHeader('Cache-Control', $cacheControlHeader);
            }

            return $nextResponse
                ->withHeader('ETag', $etag)
                ->withHeader('Last-Modified', $lastModified);
        };
    }
}