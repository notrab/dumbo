<?php

namespace Dumbo\Tests\Middleware;

use Dumbo\Context;
use Dumbo\Dumbo;
use Dumbo\Middleware\BodyLimit;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class BodyLimitTest extends TestCase
{
    private function app(?callable $onError = null): Dumbo
    {
        $app = new Dumbo();
        $app->use(BodyLimit::bodyLimit(10, $onError));
        $app->post("/", fn(Context $context) => $context->text("ok"));

        return $app;
    }

    public function testAllowsBodiesWithinTheLimit()
    {
        $response = $this->app()->handle(
            new ServerRequest("POST", "/", ["Content-Length" => "10"], "0123456789")
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRejectsBodiesOverTheLimit()
    {
        $response = $this->app()->handle(
            new ServerRequest("POST", "/", ["Content-Length" => "11"], "01234567890")
        );

        $this->assertSame(413, $response->getStatusCode());
        $this->assertStringContainsString("BODY_TOO_LARGE", (string) $response->getBody());
    }

    public function testUsesTheCustomErrorHandler()
    {
        $app = $this->app(fn(Context $context, int $max) => $context->text("max $max", 400));

        $response = $app->handle(
            new ServerRequest("POST", "/", ["Content-Length" => "11"], "01234567890")
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame("max 10", (string) $response->getBody());
    }
}
