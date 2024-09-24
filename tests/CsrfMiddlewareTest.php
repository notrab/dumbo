<?php

namespace Dumbo\Tests;

use PHPUnit\Framework\TestCase;
use Dumbo\Context;
use Dumbo\Middleware\CsrfMiddleware;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

class CsrfMiddlewareTest extends TestCase
{
    private array $tokenStorage = [];

    protected function setUp(): void
    {
        $this->tokenStorage = [];
    }

    private function createContext($method, $body = [], $headers = []): Context
    {
        $jsonBody = json_encode($body);
        $stream = Utils::streamFor($jsonBody);
        $request = new ServerRequest($method, '/', $headers, $stream);
        return new Context($request, [], '/');
    }

    private function getTokenCallback(): callable
    {
        return function ($ctx) {
            return $this->tokenStorage['csrf_token'] ?? null;
        };
    }

    private function setTokenCallback(): callable
    {
        return function ($ctx, $token) {
            $this->tokenStorage['csrf_token'] = $token;
        };
    }

    public function testSafeMethodGeneratesToken()
    {
        $middleware = CsrfMiddleware::csrf([
            'getToken' => $this->getTokenCallback(),
            'setToken' => $this->setTokenCallback(),
            'tokenLength' => 64,
        ]);

        $ctx = $this->createContext('GET');
        $nextCalled = false;

        $middleware($ctx, function ($c) use (&$nextCalled) {
            $nextCalled = true;
            return new Response();
        });

        $this->assertTrue($nextCalled);
        $this->assertNotNull($this->tokenStorage['csrf_token']);
        $this->assertEquals(64, strlen($this->tokenStorage['csrf_token']));
    }

    public function testUnsafeMethodWithValidToken()
    {
        $token = bin2hex(random_bytes(32));
        $this->tokenStorage['csrf_token'] = $token;

        $middleware = CsrfMiddleware::csrf([
            'getToken' => $this->getTokenCallback(),
            'setToken' => $this->setTokenCallback(),
        ]);

        $ctx = $this->createContext('POST', ['csrf_token' => $token], ['Content-Type' => 'application/json']);
        $nextCalled = false;

        $middleware($ctx, function ($c) use (&$nextCalled) {
            $nextCalled = true;
            return new Response();
        });

        $this->assertTrue($nextCalled);
    }


    public function testUnsafeMethodWithInvalidToken()
    {
        $middleware = CsrfMiddleware::csrf([
            'getToken' => $this->getTokenCallback(),
            'setToken' => $this->setTokenCallback(),
        ]);

        $ctx = $this->createContext('POST', ['csrf_token' => 'invalid_token']);

        $response = $middleware($ctx, function () {
            $this->fail('Next middleware should not be called');
        });

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('{"error":"Invalid CSRF token"}', (string) $response->getBody());
    }

    public function testUnsafeMethodWithMissingToken()
    {
        $middleware = CsrfMiddleware::csrf([
            'getToken' => $this->getTokenCallback(),
            'setToken' => $this->setTokenCallback(),
        ]);

        $ctx = $this->createContext('POST');

        $response = $middleware($ctx, function () {
            $this->fail('Next middleware should not be called');
        });

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('{"error":"Invalid CSRF token"}', (string) $response->getBody());
    }

    public function testCustomErrorHandler()
    {
        $middleware = CsrfMiddleware::csrf([
            'getToken' => $this->getTokenCallback(),
            'setToken' => $this->setTokenCallback(),
            'errorHandler' => function ($ctx) {
                return $ctx->json(['error' => 'Custom error'], 400);
            },
        ]);

        $ctx = $this->createContext('POST', ['csrf_token' => 'invalid_token']);

        $response = $middleware($ctx, function () {
            $this->fail('Next middleware should not be called');
        });

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('{"error":"Custom error"}', (string) $response->getBody());
    }

    public function testUseHeader()
    {
        $token = bin2hex(random_bytes(32));
        $this->tokenStorage['csrf_token'] = $token;

        $middleware = CsrfMiddleware::csrf([
            'getToken' => $this->getTokenCallback(),
            'setToken' => $this->setTokenCallback(),
            'useHeader' => true,
        ]);

        $ctx = $this->createContext('POST', [], ['X-CSRF-TOKEN' => $token, 'Content-Type' => 'application/json']);
        $nextCalled = false;

        $middleware($ctx, function ($c) use (&$nextCalled) {
            $nextCalled = true;
            return new Response();
        });

        $this->assertTrue($nextCalled);
    }
}