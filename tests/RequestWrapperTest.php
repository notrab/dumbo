<?php

namespace Dumbo\Tests;

use Dumbo\RequestWrapper;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;

class RequestWrapperTest extends TestCase
{
    private $mockRequest;
    private $requestWrapper;

    protected function setUp(): void
    {
        $this->mockRequest = $this->createMock(ServerRequestInterface::class);
        $mockUri = $this->createMock(UriInterface::class);
        $mockUri->method("getPath")->willReturn("/test/path");
        $this->mockRequest->method("getUri")->willReturn($mockUri);

        $params = ["id" => "123"];
        $routePath = "/test/:id";

        $this->requestWrapper = new RequestWrapper(
            $this->mockRequest,
            $params,
            $routePath
        );
    }

    public function testPath()
    {
        $this->assertEquals("/test/path", $this->requestWrapper->path());
    }

    public function testRoutePath()
    {
        $this->assertEquals("/test/:id", $this->requestWrapper->routePath());
    }

    public function testParam()
    {
        $this->assertEquals("123", $this->requestWrapper->param("id"));
        $this->assertNull($this->requestWrapper->param("nonexistent"));
    }

    public function testQueries()
    {
        $this->mockRequest->method("getQueryParams")->willReturn([
            "filter" => "active",
            "sort" => ["name", "date"],
        ]);

        $this->assertEquals("active", $this->requestWrapper->queries("filter"));
        $this->assertEquals(
            ["name", "date"],
            $this->requestWrapper->queries("sort")
        );
        $this->assertEquals([], $this->requestWrapper->queries("nonexistent"));
    }

    public function testQuery()
    {
        $queryParams = [
            "page" => "1",
            "limit" => "10",
        ];
        $this->mockRequest->method("getQueryParams")->willReturn($queryParams);

        $this->assertEquals($queryParams, $this->requestWrapper->query());
        $this->assertEquals("1", $this->requestWrapper->query("page"));
        $this->assertNull($this->requestWrapper->query("nonexistent"));
    }

    public function testBody()
    {
        $bodyContent = '{"name": "John Doe", "email": "john@example.com"}';
        $expectedBody = ["name" => "John Doe", "email" => "john@example.com"];

        $stream = $this->createMock(StreamInterface::class);
        $stream->method("__toString")->willReturn($bodyContent);

        $this->mockRequest->method("getParsedBody")->willReturn(null);
        $this->mockRequest
            ->method("getHeaderLine")
            ->with("Content-Type")
            ->willReturn("application/json");
        $this->mockRequest->method("getBody")->willReturn($stream);

        $requestWrapper = new RequestWrapper($this->mockRequest, [], "");
        $this->assertEquals($expectedBody, $requestWrapper->body());
    }

    public function testBodyFormUrlEncoded()
    {
        $bodyContent = "name=John+Doe&email=john%40example.com";
        $expectedBody = ["name" => "John Doe", "email" => "john@example.com"];

        $stream = $this->createMock(StreamInterface::class);
        $stream->method("__toString")->willReturn($bodyContent);

        $this->mockRequest->method("getParsedBody")->willReturn(null);
        $this->mockRequest
            ->method("getHeaderLine")
            ->with("Content-Type")
            ->willReturn("application/x-www-form-urlencoded");
        $this->mockRequest->method("getBody")->willReturn($stream);

        $requestWrapper = new RequestWrapper($this->mockRequest, [], "");
        $this->assertEquals($expectedBody, $requestWrapper->body());
    }

    public function testMethod()
    {
        $this->mockRequest->method("getMethod")->willReturn("POST");
        $this->assertEquals("POST", $this->requestWrapper->method());
    }

    public function testHeaders()
    {
        $headers = [
            "Content-Type" => ["application/json"],
            "Authorization" => ["Bearer token123"],
        ];
        $this->mockRequest->method("getHeaders")->willReturn($headers);
        $this->mockRequest
            ->method("getHeader")
            ->willReturnCallback(function ($name) use ($headers) {
                return $headers[$name] ?? [];
            });

        $this->assertEquals($headers, $this->requestWrapper->headers());
        $this->assertEquals(
            ["application/json"],
            $this->requestWrapper->headers("Content-Type")
        );
        $this->assertEquals([], $this->requestWrapper->headers("nonexistent"));
    }

    public function testHeader()
    {
        $this->mockRequest
            ->method("getHeader")
            ->willReturnMap([
                ["Content-Type", ["application/json"]],
                ["Authorization", ["Bearer token123"]],
                ["nonexistent", []],
            ]);

        $this->assertEquals(
            "application/json",
            $this->requestWrapper->header("Content-Type")
        );
        $this->assertEquals(
            "Bearer token123",
            $this->requestWrapper->header("Authorization")
        );
        $this->assertNull($this->requestWrapper->header("nonexistent"));
    }
}
