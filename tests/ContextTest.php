<?php

namespace Dumbo\Tests;

use Dumbo\Context;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;

class ContextTest extends TestCase
{
    private function context(): Context
    {
        return new Context(new ServerRequest("GET", "/"), [], "/");
    }

    public function testHeaderReplacesByDefault()
    {
        $context = $this->context();

        $context->header("X-Custom", "one");
        $context->header("X-Custom", "two");

        $this->assertSame(
            ["two"],
            $context->getResponse()->getHeader("X-Custom")
        );
    }

    public function testHeaderCanAppend()
    {
        $context = $this->context();

        $context->header("X-Custom", "one", true);
        $context->header("X-Custom", "two", true);

        $this->assertSame(
            ["one", "two"],
            $context->getResponse()->getHeader("X-Custom")
        );
    }

    public function testRedirectIsStoredOnTheContext()
    {
        $context = $this->context();

        $context->redirect("/elsewhere", 301);

        $response = $context->getResponse();
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame("/elsewhere", $response->getHeaderLine("Location"));
    }

    public function testSendWritesAStreamBody()
    {
        $response = $this->context()->send(
            Utils::streamFor("streamed"),
            "text/plain"
        );

        $this->assertSame("streamed", (string) $response->getBody());
    }

    public function testSendEncodesArrays()
    {
        $response = $this->context()->json(["a" => 1]);

        $this->assertSame('{"a":1}', (string) $response->getBody());
        $this->assertSame(
            "application/json",
            $response->getHeaderLine("Content-Type")
        );
    }
}
