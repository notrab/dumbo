<?php

namespace Dumbo\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Dumbo\Dumbo;
use Dumbo\Middleware\RequestIdMiddleware;
use GuzzleHttp\Psr7\ServerRequest;

class RequestIdMiddlewareTest extends TestCase
{
    public function testRequestIdMiddleware()
    {
        $app = new Dumbo();

        $app->use(
            RequestIdMiddleware::requestId([
                "headerName" => "X-Custom-Request-Id",
                "limitLength" => 128,
                "generator" => function ($context) {
                    return uniqid("custom-", true);
                },
            ])
        );

        $app->get("/", function ($context) {
            $requestId = $context->get("requestId");
            return $context->text("Your request ID is: " . $requestId);
        });

        $request = new ServerRequest("GET", "/");

        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader("X-Custom-Request-Id"));
        $requestId = $response->getHeaderLine("X-Custom-Request-Id");
        $this->assertStringStartsWith("custom-", $requestId);
        $this->assertLessThanOrEqual(128, strlen($requestId));
        $this->assertStringContainsString(
            $requestId,
            (string) $response->getBody()
        );
    }

    public function testRequestIdMiddlewareWithExistingHeader()
    {
        $app = new Dumbo();

        $app->use(
            RequestIdMiddleware::requestId([
                "headerName" => "X-Custom-Request-Id",
            ])
        );

        $app->get("/", function ($context) {
            $requestId = $context->get("requestId");
            return $context->text("Your request ID is: " . $requestId);
        });

        $existingRequestId = "existing-id-12345";
        $request = new ServerRequest("GET", "/", [
            "X-Custom-Request-Id" => $existingRequestId,
        ]);

        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader("X-Custom-Request-Id"));
        $this->assertEquals(
            $existingRequestId,
            $response->getHeaderLine("X-Custom-Request-Id")
        );
        $this->assertStringContainsString(
            $existingRequestId,
            (string) $response->getBody()
        );
    }

    public function testNoRequestIdHeaderWhenMiddlewareNotAdded()
    {
        $app = new Dumbo();

        $app->get("/", function ($context) {
            return $context->text("Hello, World!");
        });

        $request = new ServerRequest("GET", "/");

        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader("X-Request-Id"));
        $this->assertEquals("Hello, World!", (string) $response->getBody());
    }
}
