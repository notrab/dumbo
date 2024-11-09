<?php

namespace Dumbo\Tests\Middleware;

use Dumbo\Context;
use Dumbo\Middleware\CacheMiddleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class CacheMiddlewareTest extends TestCase
{
    private function createMockContext($method = 'GET', $headers = [], $path = '/', $queryParams = []): Context
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $request->method('getUri')->willReturn($uri);

        $request->method('getHeader')
            ->willReturnCallback(function ($headerName) use ($headers) {
                return $headers[$headerName] ?? [];
            });

        $request->method('getHeaders')->willReturn($headers);

        $request->method('getQueryParams')->willReturn($queryParams);

        return new Context($request, [], $path);
    }

    public function testHeadersAreModifiedByMiddleware()
    {
        $middleware = CacheMiddleware::withHeaders('public', true, 60);
        $context = $this->createMockContext();

        $next = function ($ctx) {
            return new Response(200, [], 'Response body');
        };

        $response = $middleware($context, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('public, max-age=60, must-revalidate', $response->getHeaderLine('Cache-Control'));
        $this->assertNotEmpty($response->getHeaderLine('ETag'));
        $this->assertNotEmpty($response->getHeaderLine('Last-Modified'));
    }

    public function testReturns304WhenETagMatches()
    {
        $etag = 'W/"' . md5('/') . '"';
        $middleware = CacheMiddleware::withHeaders('public', true, 60);
        $context = $this->createMockContext('GET', ['If-None-Match' => [$etag]]);

        $next = function ($ctx) {
            return new Response(200, [], 'Response body');
        };

        $response = $middleware($context, $next);

        $this->assertEquals(304, $response->getStatusCode());
        $this->assertEquals($etag, $response->getHeaderLine('ETag'));
    }

    public function testReturns304WhenIfModifiedSinceMatches()
    {
        $lastModified = gmdate('D, d M Y H:i:s') . ' GMT';
        $middleware = CacheMiddleware::withHeaders('public', true, 60);
        $context = $this->createMockContext('GET', ['If-Modified-Since' => [$lastModified]]);

        $next = function ($ctx) use ($lastModified) {
            return (new Response(200, [], 'Response body'))
                ->withHeader('Last-Modified', $lastModified);
        };

        $response = $middleware($context, $next);

        $this->assertEquals(304, $response->getStatusCode());
        $this->assertEquals($lastModified, $response->getHeaderLine('Last-Modified'));
    }

    public function testDoesNotCacheNonGetRequests()
    {
        $middleware = CacheMiddleware::withHeaders('public', true, 60);
        $context = $this->createMockContext('POST');

        $next = function ($ctx) {
            return new Response(200, [], 'Response body');
        };

        $response = $middleware($context, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($response->getHeaderLine('Cache-Control'));
    }

    public function testGeneratesCorrectETagForStrictMode()
    {
        $middleware = CacheMiddleware::withHeaders('public', true, 60, true);

        $context = $this->createMockContext('GET', [], '/test', ['name' => 'Dumbo']);

        $next = function ($ctx) {
            return new Response(200, [], 'Response body');
        };

        $expectedEtag = 'W/"' . md5('GET/test' . serialize(['name' => 'Dumbo'])) . '"';

        $response = $middleware($context, $next);

        $this->assertEquals($expectedEtag, $response->getHeaderLine('ETag'));
    }
}
