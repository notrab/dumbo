<?php

namespace Dumbo\Tests\Middleware;

use Dumbo\Context;
use Dumbo\Middleware\Cache;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    private function send(
        string $body = "Response body",
        array $headers = [],
        string $method = "GET",
        array $responseHeaders = []
    ) {
        $middleware = Cache::cache([
            "type" => "public",
            "maxAge" => 60,
            "mustRevalidate" => true,
        ]);
        $context = new Context(new ServerRequest($method, "/", $headers), [], "/");

        return $middleware(
            $context,
            fn() => new Response(200, $responseHeaders, $body)
        );
    }

    public function testAddsCacheControlAndEtag()
    {
        $response = $this->send();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            "public, max-age=60, must-revalidate",
            $response->getHeaderLine("Cache-Control")
        );
        $this->assertEquals(
            'W/"' . md5("Response body") . '"',
            $response->getHeaderLine("ETag")
        );
    }

    public function testReturns304WhenEtagMatches()
    {
        $etag = $this->send()->getHeaderLine("ETag");

        $response = $this->send("Response body", ["If-None-Match" => $etag]);

        $this->assertEquals(304, $response->getStatusCode());
        $this->assertEquals("", (string) $response->getBody());
    }

    public function testReturns200WhenTheContentChanges()
    {
        $etag = $this->send("old")->getHeaderLine("ETag");

        $response = $this->send("new", ["If-None-Match" => $etag]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("new", (string) $response->getBody());
    }

    public function testReturns304WhenIfModifiedSinceMatchesLastModified()
    {
        $lastModified = gmdate(DATE_RFC7231, time() - 60);

        $response = $this->send(
            "Response body",
            ["If-Modified-Since" => gmdate(DATE_RFC7231)],
            "GET",
            ["Last-Modified" => $lastModified]
        );

        $this->assertEquals(304, $response->getStatusCode());
        $this->assertEquals($lastModified, $response->getHeaderLine("Last-Modified"));
    }

    public function testDoesNotCacheNonGetRequests()
    {
        $response = $this->send("Response body", [], "POST");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader("Cache-Control"));
        $this->assertFalse($response->hasHeader("ETag"));
    }
}
