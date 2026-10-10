<?php

namespace Dumbo\Tests\Middleware;

use Dumbo\Context;
use Dumbo\Middleware\CORS;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class CORSTest extends TestCase
{
    private function send(callable $middleware, string $method, array $headers)
    {
        return $middleware(
            new Context(new ServerRequest($method, "/", $headers), [], "/"),
            fn() => new Response(200, [], "ok")
        );
    }

    public function testAddsHeadersForAllowedOrigin()
    {
        $response = $this->send(
            CORS::cors(["origin" => ["https://a.test"], "credentials" => true]),
            "GET",
            ["Origin" => "https://a.test"]
        );

        $this->assertSame("https://a.test", $response->getHeaderLine("Access-Control-Allow-Origin"));
        $this->assertSame("true", $response->getHeaderLine("Access-Control-Allow-Credentials"));
        $this->assertSame("Origin", $response->getHeaderLine("Vary"));
    }

    public function testOmitsAllowOriginForDisallowedOrigin()
    {
        $response = $this->send(
            CORS::cors(["origin" => ["https://a.test"]]),
            "GET",
            ["Origin" => "https://evil.test"]
        );

        $this->assertFalse($response->hasHeader("Access-Control-Allow-Origin"));
    }

    public function testAnswersPreflightRequests()
    {
        $response = $this->send(
            CORS::cors(),
            "OPTIONS",
            [
                "Origin" => "https://a.test",
                "Access-Control-Request-Method" => "PUT",
                "Access-Control-Request-Headers" => "X-Custom",
            ]
        );

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame("*", $response->getHeaderLine("Access-Control-Allow-Origin"));
        $this->assertSame("PUT", $response->getHeaderLine("Access-Control-Allow-Methods"));
        $this->assertSame("X-Custom", $response->getHeaderLine("Access-Control-Allow-Headers"));
        $this->assertSame("86400", $response->getHeaderLine("Access-Control-Max-Age"));
    }

    public function testPassesThroughRequestsWithoutAnOrigin()
    {
        $response = $this->send(CORS::cors(), "GET", []);

        $this->assertFalse($response->hasHeader("Access-Control-Allow-Origin"));
    }
}
