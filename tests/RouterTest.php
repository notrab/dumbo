<?php

namespace Dumbo\Tests;

use PHPUnit\Framework\TestCase;
use Dumbo\Router;
use Dumbo\Context;
use GuzzleHttp\Psr7\ServerRequest;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testBasicRouting()
    {
        $this->router->addRoute("GET", "/test", function (Context $ctx) {
            return $ctx->text("Test Route");
        });

        $request = new ServerRequest("GET", "/test");
        $route = $this->router->findRoute($request);

        $this->assertNotNull($route);
        $this->assertEquals("/test", $route["routePath"]);
    }

    public function testNestedRoutes()
    {
        $this->router->addRoute("GET", "/api/users", function (Context $ctx) {
            return $ctx->text("Users List");
        });

        $this->router->addRoute("GET", "/api/users/:id", function (
            Context $ctx
        ) {
            return $ctx->text("User Details");
        });

        $request1 = new ServerRequest("GET", "/api/users");
        $route1 = $this->router->findRoute($request1);

        $this->assertNotNull($route1);
        $this->assertEquals("/api/users", $route1["routePath"]);

        $request2 = new ServerRequest("GET", "/api/users/123");
        $route2 = $this->router->findRoute($request2);

        $this->assertNotNull($route2);
        $this->assertEquals("/api/users/:id", $route2["routePath"]);
        $this->assertEquals(["id" => "123"], $route2["params"]);
    }

    public function testMiddlewareApplication()
    {
        $middleware = function (Context $ctx, callable $next) {
            $ctx->set("middlewareApplied", true);
            return $next($ctx);
        };

        $this->router->addRoute(
            "GET",
            "/protected",
            function (Context $ctx) {
                return $ctx->text("Protected Route");
            },
            [$middleware]
        );

        $request = new ServerRequest("GET", "/protected");
        $route = $this->router->findRoute($request);

        $this->assertNotNull($route);
        $this->assertCount(1, $route["middleware"]);
        $this->assertSame($middleware, $route["middleware"][0]);
    }

    public function testPathPreparation()
    {
        $this->router->addRoute("GET", "/users/:id/posts/:postId", function (
            Context $ctx
        ) {
            return $ctx->text("User Post");
        });

        $request = new ServerRequest("GET", "/users/123/posts/456");
        $route = $this->router->findRoute($request);

        $this->assertNotNull($route);
        $this->assertEquals("/users/:id/posts/:postId", $route["routePath"]);
        $this->assertEquals(
            ["id" => "123", "postId" => "456"],
            $route["params"]
        );
    }

    public function testMethodNotAllowed()
    {
        $this->router->addRoute("GET", "/method-test", function (Context $ctx) {
            return $ctx->text("GET Method");
        });

        $request = new ServerRequest("POST", "/method-test");
        $route = $this->router->findRoute($request);

        $this->assertNull($route);
    }

    public function testRouteNotFound()
    {
        $request = new ServerRequest("GET", "/non-existent-route");
        $route = $this->router->findRoute($request);

        $this->assertNull($route);
    }

    public function testMultipleMiddleware()
    {
        $middleware1 = function (Context $ctx, callable $next) {
            $ctx->set("middleware1", true);
            return $next($ctx);
        };

        $middleware2 = function (Context $ctx, callable $next) {
            $ctx->set("middleware2", true);
            return $next($ctx);
        };

        $this->router->addRoute(
            "GET",
            "/multi-middleware",
            function (Context $ctx) {
                return $ctx->text("Multiple Middleware");
            },
            [$middleware1, $middleware2]
        );

        $request = new ServerRequest("GET", "/multi-middleware");
        $route = $this->router->findRoute($request);

        $this->assertNotNull($route);
        $this->assertCount(2, $route["middleware"]);
        $this->assertSame($middleware1, $route["middleware"][0]);
        $this->assertSame($middleware2, $route["middleware"][1]);
    }
}
