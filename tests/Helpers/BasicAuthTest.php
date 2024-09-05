<?php

namespace Dumbo\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use Dumbo\Helpers\BasicAuth;
use Dumbo\Context;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\Response;

class BasicAuthTest extends TestCase
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

    public function testSimpleBasicAuthSuccess()
    {
        $middleware = BasicAuth::basicAuth("user:password");
        $context = $this->createMockContext(
            "Basic " . base64_encode("user:password")
        );

        $called = false;
        $next = function ($context) use (&$called) {
            $called = true;
            return new Response(200);
        };

        $response = $middleware($context, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSimpleBasicAuthFailure()
    {
        $middleware = BasicAuth::basicAuth("user:password");
        $context = $this->createMockContext(
            "Basic " . base64_encode("wrong:credentials")
        );

        $next = function ($context) {
            return new Response(200);
        };

        $response = $middleware($context, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString(
            'Basic realm="Restricted Area"',
            $response->getHeaderLine("WWW-Authenticate")
        );
    }

    public function testAdvancedBasicAuthWithUsersSuccess()
    {
        $middleware = BasicAuth::basicAuth([
            "users" => [
                ["username" => "alice", "password" => "pass123"],
                ["username" => "bob", "password" => "secret"],
            ],
        ]);
        $context = $this->createMockContext(
            "Basic " . base64_encode("alice:pass123")
        );

        $called = false;
        $next = function ($context) use (&$called) {
            $called = true;
            return new Response(200);
        };

        $response = $middleware($context, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAdvancedBasicAuthWithVerifyUserSuccess()
    {
        $middleware = BasicAuth::basicAuth([
            "verifyUser" => function ($username, $password) {
                return $username === "admin" && $password === "secret";
            },
        ]);
        $context = $this->createMockContext(
            "Basic " . base64_encode("admin:secret")
        );

        $called = false;
        $next = function ($context) use (&$called) {
            $called = true;
            return new Response(200);
        };

        $response = $middleware($context, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAdvancedBasicAuthFailure()
    {
        $middleware = BasicAuth::basicAuth([
            "users" => [["username" => "alice", "password" => "pass123"]],
        ]);
        $context = $this->createMockContext(
            "Basic " . base64_encode("alice:wrongpass")
        );

        $next = function ($context) {
            return new Response(200);
        };

        $response = $middleware($context, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString(
            'Basic realm="Restricted Area"',
            $response->getHeaderLine("WWW-Authenticate")
        );
    }
}
