<?php

namespace Dumbo\Tests;

use Dumbo\Context;
use Dumbo\Middleware\CsrfMiddleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class CsrfMiddlewareTest extends TestCase
{
    private function createMockContext($method = 'GET', $headers = [], $path = '/'): Context
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

        return new Context($request, [], $path);
    }

    public function testAllowsSafeMethodsWithoutOrigin()
    {
        $middleware = CsrfMiddleware::csrf();
        $context = $this->createMockContext();

        $next = function ($ctx) {
            return new Response(200, [], 'Response body');
        };

        $response = $middleware($context, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBlocksUnsafeMethodWithoutOrigin()
    {
        $middleware = CsrfMiddleware::csrf();
        $context = $this->createMockContext('POST', ['Content-Type' => ['application/x-www-form-urlencoded']]);

        $next = function ($ctx) {
            return new Response(200, [], 'Response body');
        };

        $response = $middleware($context, $next);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllowsUnsafeMethodWithCorrectOrigin()
    {
        $middleware = CsrfMiddleware::csrf(['origin' => 'https://example.com']);
        $context = $this->createMockContext('POST', [
            'Content-Type' => ['application/x-www-form-urlencoded'],
            'Origin' => ['https://example.com']
        ]);

        $next = function ($ctx) {
            return new Response(200, [], 'Response body');
        };

        $response = $middleware($context, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBlocksUnsafeMethodWithIncorrectOrigin()
    {
        $middleware = CsrfMiddleware::csrf(['origin' => 'https://example.com']);
        $context = $this->createMockContext('POST', [
            'Content-Type' => ['application/x-www-form-urlencoded'],
            'Origin' => ['https://malicious.com']
        ]);

        $next = function ($ctx) {
            return new Response(200, [], 'Response body');
        };

        $response = $middleware($context, $next);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllowsUnsafeMethodWithCorrectOriginInArray()
    {
        $middleware = CsrfMiddleware::csrf(['origin' => ['https://example.com', 'https://subdomain.example.com']]);
        $context = $this->createMockContext('POST', [
            'Content-Type' => ['application/x-www-form-urlencoded'],
            'Origin' => ['https://subdomain.example.com']
        ]);

        $next = function ($ctx) {
            return new Response(200, [], 'Response body');
        };

        $response = $middleware($context, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAllowsUnsafeMethodWithCustomOriginFunction()
    {
        $middleware = CsrfMiddleware::csrf([
            'origin' => function($origin) {
                return str_contains($origin, '.example.com');
            }
        ]);
        $context = $this->createMockContext('POST', [
            'Content-Type' => ['application/x-www-form-urlencoded'],
            'Origin' => ['https://custom.example.com']
        ]);

        $next = function ($ctx) {
            return new Response(200, [], 'Response body');
        };

        $response = $middleware($context, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIgnoresNonFormRequests()
    {
        $middleware = CsrfMiddleware::csrf();
        $context = $this->createMockContext('POST', ['Content-Type' => ['application/json']]);

        $next = function ($ctx) {
            return new Response(200, [], 'Response body');
        };

        $response = $middleware($context, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }
}