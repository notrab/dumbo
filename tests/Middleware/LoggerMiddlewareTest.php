<?php

namespace Dumbo\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Dumbo\Middleware\LoggerMiddleware;
use Dumbo\Context;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class LoggerMiddlewareTest extends TestCase
{
    private function createMockContext(string $method, string $path): Context
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn($method);

        return new Context($request, [], "");
    }

    public function testLoggerInstance()
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $middleware = LoggerMiddleware::logger($loggerMock);

        $this->assertInstanceOf(LoggerMiddleware::class, $middleware);
    }

    public function testInvokeLogsIncomingAndOutgoingRequest()
    {
        $loggerMock = $this->createMock(LoggerInterface::class);

        $messages = [];
        $loggerMock->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message) use (&$messages) {
                $messages[] = $message;
            });

        $context = $this->createMockContext('GET', '/test-path');

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);

        $middleware = LoggerMiddleware::logger($loggerMock);

        $next = fn () => $responseMock;

        $middleware($context, $next);

        $this->assertCount(2, $messages);

        $this->assertStringContainsString(LoggerMiddleware::LOG_PREFIX_INCOMING, $messages[0]);
        $this->assertStringContainsString('GET /test-path', $messages[0]);

        $this->assertStringContainsString(LoggerMiddleware::LOG_PREFIX_OUTGOING, $messages[1]);
        $this->assertStringContainsString('GET /test-path', $messages[1]);
        $this->assertStringContainsString('200', $messages[1]);
    }
}
