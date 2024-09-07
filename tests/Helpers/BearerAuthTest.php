<?php

namespace Dumbo\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use Dumbo\Helpers\BearerAuth;
use Dumbo\Context;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\Response;

class BearerAuthTest extends TestCase
{
    private function createMockContext($authHeader = null): Context
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->method("getHeader")
            ->with("Authorization")
            ->willReturn($authHeader ? [$authHeader] : []);

        return new Context($request, [], "");
    }

    public function testSimpleBearerAuthSuccess()
    {
        $middleware = BearerAuth::bearerAuth("valid-token");
        $context = $this->createMockContext("Bearer valid-token");

        $called = false;
        $next = function ($context) use (&$called) {
            $called = true;
            return new Response(200);
        };

        $response = $middleware($context, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSimpleBearerAuthFailure()
    {
        $middleware = BearerAuth::bearerAuth("valid-token");
        $context = $this->createMockContext("Bearer invalid-token");

        $next = function ($context) {
            return new Response(200);
        };

        $response = $middleware($context, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString(
            "Unauthorized request",
            (string) $response->getBody()
        );
    }

    public function testAdvancedBearerAuthWithTokensSuccess()
    {
        $middleware = BearerAuth::bearerAuth([
            "tokens" => ["token1", "token2"],
        ]);
        $context = $this->createMockContext("Bearer token2");

        $called = false;
        $next = function ($context) use (&$called) {
            $called = true;
            return new Response(200);
        };

        $response = $middleware($context, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAdvancedBearerAuthWithVerifyTokenSuccess()
    {
        $middleware = BearerAuth::bearerAuth([
            "verifyToken" => function ($token) {
                return $token === "valid-token";
            },
        ]);
        $context = $this->createMockContext("Bearer valid-token");

        $called = false;
        $next = function ($context) use (&$called) {
            $called = true;
            return new Response(200);
        };

        $response = $middleware($context, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAdvancedBearerAuthFailure()
    {
        $middleware = BearerAuth::bearerAuth([
            "tokens" => ["token1", "token2"],
        ]);
        $context = $this->createMockContext("Bearer invalid-token");

        $next = function ($context) {
            return new Response(200);
        };

        $response = $middleware($context, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString(
            "Invalid token",
            (string) $response->getBody()
        );
    }

    public function testMissingAuthorizationHeader()
    {
        $middleware = BearerAuth::bearerAuth("valid-token");
        $context = $this->createMockContext();

        $next = function ($context) {
            return new Response(200);
        };

        $response = $middleware($context, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString(
            "Unauthorized request",
            (string) $response->getBody()
        );
    }

    public function testInvalidAuthorizationHeaderFormat()
    {
        $middleware = BearerAuth::bearerAuth("valid-token");
        $context = $this->createMockContext("InvalidFormat token");

        $next = function ($context) {
            return new Response(200);
        };

        $response = $middleware($context, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString(
            "Unauthorized request",
            (string) $response->getBody()
        );
    }

    public function testCustomRealm()
    {
        $middleware = BearerAuth::bearerAuth([
            "tokens" => ["valid-token"],
            "realm" => "Custom Realm",
        ]);
        $context = $this->createMockContext("Bearer invalid-token");

        $next = function ($context) {
            return new Response(200);
        };

        $response = $middleware($context, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString(
            'Bearer realm="Custom Realm"',
            $response->getHeaderLine("WWW-Authenticate")
        );
    }
}
