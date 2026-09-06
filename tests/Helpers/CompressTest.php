<?php

namespace Dumbo\Tests\Helpers;

use Dumbo\Context;
use Dumbo\Helpers\Compress;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class CompressTest extends TestCase
{
    private function context(array $headers = []): Context
    {
        return new Context(
            new ServerRequest("GET", "/", $headers),
            [],
            "/"
        );
    }

    public function testCompressesTheResponseReturnedByTheHandler()
    {
        $body = str_repeat("x", 2000);
        $middleware = Compress::compress(["threshold" => 1]);

        $response = $middleware(
            $this->context(["Accept-Encoding" => "gzip"]),
            fn() => new Response(200, ["Content-Type" => "text/plain"], $body)
        );

        $this->assertSame("gzip", $response->getHeaderLine("Content-Encoding"));
        $this->assertStringContainsString(
            "Accept-Encoding",
            $response->getHeaderLine("Vary")
        );
        $this->assertSame($body, gzdecode((string) $response->getBody()));
    }

    public function testLeavesTheResponseAloneWithoutAcceptEncoding()
    {
        $middleware = Compress::compress(["threshold" => 1]);

        $response = $middleware(
            $this->context(),
            fn() => new Response(
                200,
                ["Content-Type" => "text/plain"],
                str_repeat("x", 2000)
            )
        );

        $this->assertFalse($response->hasHeader("Content-Encoding"));
        $this->assertSame(str_repeat("x", 2000), (string) $response->getBody());
    }

    public function testSkipsAlreadyEncodedResponses()
    {
        $middleware = Compress::compress(["threshold" => 1]);

        $response = $middleware(
            $this->context(["Accept-Encoding" => "gzip"]),
            fn() => new Response(
                200,
                ["Content-Type" => "text/plain", "Content-Encoding" => "br"],
                str_repeat("x", 2000)
            )
        );

        $this->assertSame("br", $response->getHeaderLine("Content-Encoding"));
    }
}
