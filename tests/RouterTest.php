<?php

namespace Dumbo\Tests;

use PHPUnit\Framework\TestCase;
use Dumbo\Router;
use Dumbo\Context;
use Nyholm\Psr7\ServerRequest;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testBasicRouting()
    {
        $this->router->addRoute("GET", "/test", function (Context $context) {
            return $context->text("Test Route");
        });

        $request = new ServerRequest("GET", "/test");
        $route = $this->router->dispatch($request)["route"];

        $this->assertNotNull($route);
        $this->assertEquals("/test", $route["routePath"]);
    }

    public function testNestedRoutes()
    {
        $this->router->addRoute("GET", "/api/users", function (
            Context $context
        ) {
            return $context->text("Users List");
        });

        $this->router->addRoute("GET", "/api/users/:id", function (
            Context $context
        ) {
            return $context->text("User Details");
        });

        $request1 = new ServerRequest("GET", "/api/users");
        $route1 = $this->router->dispatch($request1)["route"];

        $this->assertNotNull($route1);
        $this->assertEquals("/api/users", $route1["routePath"]);

        $request2 = new ServerRequest("GET", "/api/users/123");
        $route2 = $this->router->dispatch($request2)["route"];

        $this->assertNotNull($route2);
        $this->assertEquals("/api/users/:id", $route2["routePath"]);
        $this->assertEquals(["id" => "123"], $route2["params"]);
    }

    public function testMiddlewareApplication()
    {
        $middleware = function (Context $context, callable $next) {
            $context->set("middlewareApplied", true);
            return $next($context);
        };

        $this->router->addRoute(
            "GET",
            "/protected",
            function (Context $context) {
                return $context->text("Protected Route");
            },
            [$middleware]
        );

        $request = new ServerRequest("GET", "/protected");
        $route = $this->router->dispatch($request)["route"];

        $this->assertNotNull($route);
        $this->assertCount(1, $route["middleware"]);
        $this->assertSame($middleware, $route["middleware"][0]);
    }

    public function testPathPreparation()
    {
        $this->router->addRoute("GET", "/users/:id/posts/:postId", function (
            Context $context
        ) {
            return $context->text("User Post");
        });

        $request = new ServerRequest("GET", "/users/123/posts/456");
        $route = $this->router->dispatch($request)["route"];

        $this->assertNotNull($route);
        $this->assertEquals("/users/:id/posts/:postId", $route["routePath"]);
        $this->assertEquals(
            ["id" => "123", "postId" => "456"],
            $route["params"]
        );
    }

    public function testMethodNotAllowed()
    {
        $this->router->addRoute("GET", "/method-test", function (
            Context $context
        ) {
            return $context->text("GET Method");
        });

        $request = new ServerRequest("POST", "/method-test");
        $route = $this->router->dispatch($request)["route"];

        $this->assertNull($route);
    }

    public function testRouteNotFound()
    {
        $request = new ServerRequest("GET", "/non-existent-route");
        $route = $this->router->dispatch($request)["route"];

        $this->assertNull($route);
    }

    public function testDispatchReportsAllowedMethodsForMethodMismatch()
    {
        $handler = function (Context $context) {
            return $context->text("Users");
        };

        $this->router->addRoute("GET", "/users", $handler);
        $this->router->addRoute("POST", "/users", $handler);

        $dispatch = $this->router->dispatch(
            new ServerRequest("DELETE", "/users")
        );

        $this->assertNull($dispatch["route"]);
        $this->assertEquals(["GET", "POST"], $dispatch["allowedMethods"]);
    }

    public function testDispatchReportsNoAllowedMethodsForUnknownPath()
    {
        $dispatch = $this->router->dispatch(
            new ServerRequest("GET", "/non-existent-route")
        );

        $this->assertNull($dispatch["route"]);
        $this->assertEquals([], $dispatch["allowedMethods"]);
    }

    public function testDispatchReturnsMatchedRoute()
    {
        $this->router->addRoute("GET", "/users/:id", function (
            Context $context
        ) {
            return $context->text("User");
        });

        $dispatch = $this->router->dispatch(
            new ServerRequest("GET", "/users/123")
        );

        $this->assertNotNull($dispatch["route"]);
        $this->assertEquals("/users/:id", $dispatch["route"]["routePath"]);
        $this->assertEquals(["id" => "123"], $dispatch["route"]["params"]);
        $this->assertEquals([], $dispatch["allowedMethods"]);
    }

    public function testMultipleMiddleware()
    {
        $middleware1 = function (Context $context, callable $next) {
            $context->set("middleware1", true);
            return $next($context);
        };

        $middleware2 = function (Context $context, callable $next) {
            $context->set("middleware2", true);
            return $next($context);
        };

        $this->router->addRoute(
            "GET",
            "/multi-middleware",
            function (Context $context) {
                return $context->text("Multiple Middleware");
            },
            [$middleware1, $middleware2]
        );

        $request = new ServerRequest("GET", "/multi-middleware");
        $route = $this->router->dispatch($request)["route"];

        $this->assertNotNull($route);
        $this->assertCount(2, $route["middleware"]);
        $this->assertSame($middleware1, $route["middleware"][0]);
        $this->assertSame($middleware2, $route["middleware"][1]);
    }

    public function testParamsCanBeConstrainedWithARegex()
    {
        $this->router->addRoute("GET", "/posts/{id:\\d+}", function (
            Context $context
        ) {
            return $context->text("Post");
        });

        $match = $this->router->dispatch(new ServerRequest("GET", "/posts/42"));
        $miss = $this->router->dispatch(new ServerRequest("GET", "/posts/abc"));

        $this->assertEquals(["id" => "42"], $match["route"]["params"]);
        $this->assertNull($miss["route"]);
    }

    private function params(string $route, string $path): ?array
    {
        $router = new Router();
        $router->addRoute("GET", $route, fn(Context $c) => $c->text("ok"));

        return $router->dispatch(new ServerRequest("GET", $path))["route"]["params"] ?? null;
    }

    public function testHonoStyleRegexParams()
    {
        $this->assertEquals(["id" => "42"], $this->params("/posts/:id{[0-9]+}", "/posts/42"));
        $this->assertNull($this->params("/posts/:id{[0-9]+}", "/posts/abc"));
        $this->assertEquals(["year" => "2026"], $this->params("/y/:year{[0-9]{4}}", "/y/2026"));
        $this->assertEquals(["path" => "a/b.png"], $this->params("/img/:path{.+\\.png}", "/img/a/b.png"));
    }

    public function testOptionalParams()
    {
        $this->assertEquals([], $this->params("/animals/:type?", "/animals"));
        $this->assertEquals(["type" => "cat"], $this->params("/animals/:type?", "/animals/cat"));
        $this->assertEquals(["a" => "1", "b" => "2"], $this->params("/x/:a?/:b?", "/x/1/2"));
    }

    public function testOptionalParamsMustComeLast()
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Router())->addRoute("GET", "/x/:a?/y", fn() => null);
    }

    public function testWildcards()
    {
        $this->assertEquals([], $this->params("/static/*", "/static"));
        $this->assertEquals([], $this->params("/static/*", "/static/css/app.css"));
        $this->assertEquals([], $this->params("/wild/*/card", "/wild/any/thing/card"));
        $this->assertNull($this->params("/wild/*/card", "/wild/card"));
    }

    public function testFastRoutePlaceholdersAreLeftAlone()
    {
        $this->assertEquals(["lang" => "en"], $this->params("/{lang:en|fr}", "/en"));
        $this->assertEquals(["rest" => "a/b"], $this->params("/files/{rest:.*}", "/files/a/b"));
    }

    public function testSeveralWildcardsInOneRoute()
    {
        $this->assertEquals([], $this->params("/a/*/b/*", "/a/x/b/y/z"));
        $this->assertEquals([], $this->params("/a/*/b/*", "/a/x/b"));
    }

    public function testOptionalParamAtTheRoot()
    {
        $this->assertEquals([], $this->params("/:id?", "/"));
        $this->assertEquals(["id" => "5"], $this->params("/:id?", "/5"));
    }

    public function testDuplicateParamNamesThrowWhenAdded()
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Router())->addRoute("GET", "/:id/:id", fn() => null);
    }

    public function testRoutesWithAVariableFirstSegmentStillMatchInOrder()
    {
        $this->router->addRoute("GET", "/:lang/about", fn() => null);
        $this->router->addRoute("GET", "/en/:page", fn() => null);
        $this->router->addRoute("GET", "/*", fn() => null);

        $this->assertEquals("/:lang/about", $this->routePath("/en/about"));
        $this->assertEquals("/en/:page", $this->routePath("/en/contact"));
        $this->assertEquals("/*", $this->routePath("/fr/contact"));
        $this->assertEquals("/*", $this->routePath("/"));
    }

    public function testStaticRoutesAndVariableFirstSegmentsMix()
    {
        $this->router->addRoute("GET", "/about", fn() => null);
        $this->router->addRoute("GET", "/:page", fn() => null);

        $this->assertEquals("/about", $this->routePath("/about"));
        $this->assertEquals("/:page", $this->routePath("/contact"));
    }

    public function testOptionalFirstSegmentAndTheRoot()
    {
        $this->router->addRoute("GET", "/", fn() => null);
        $this->router->addRoute("GET", "/users/:id?", fn() => null);
        $this->router->addRoute("GET", "/{lang:en|fr}", fn() => null);

        $this->assertEquals("/", $this->routePath("/"));
        $this->assertEquals("/users/:id?", $this->routePath("/users"));
        $this->assertEquals("/users/:id?", $this->routePath("/users/1"));
        $this->assertEquals("/{lang:en|fr}", $this->routePath("/fr"));
        $this->assertNull($this->routePath("/de"));
    }

    public function testAllowedMethodsIncludeRoutesWithAVariableFirstSegment()
    {
        $this->router->addRoute("GET", "/users/:id", fn() => null);
        $this->router->addRoute("DELETE", "/:type/:id", fn() => null);

        $result = $this->router->dispatch(new ServerRequest("POST", "/users/1"));

        $this->assertNull($result["route"]);
        $this->assertEqualsCanonicalizing(["GET", "DELETE"], $result["allowedMethods"]);
    }

    public function testRoutesAddedAfterDispatchingAreMatched()
    {
        $this->router->addRoute("GET", "/users", fn() => null);
        $this->assertNull($this->routePath("/users/1"));

        $this->router->addRoute("GET", "/users/:id", fn() => null);
        $this->assertEquals("/users/:id", $this->routePath("/users/1"));
    }

    public function testManyRoutesWithParamsUnderOnePrefix()
    {
        for ($i = 0; $i < 120; $i++) {
            $this->router->addRoute("GET", "/api/r$i/:id", fn() => null);
        }

        $route = $this->router->dispatch(new ServerRequest("GET", "/api/r119/7"))["route"];

        $this->assertEquals("/api/r119/:id", $route["routePath"]);
        $this->assertEquals(["id" => "7"], $route["params"]);
        $this->assertEquals("/api/r60/:id", $this->routePath("/api/r60/1"));
    }

    public function testHeadFallsBackToGet()
    {
        $this->router->addRoute("GET", "/users/:id", fn() => null);

        $route = $this->router->dispatch(new ServerRequest("HEAD", "/users/1"))["route"];

        $this->assertEquals("/users/:id", $route["routePath"]);
    }

    public function testFirstRouteAddedWinsForTheSamePath()
    {
        $this->router->addRoute("GET", "/users/:id", fn() => "first");
        $this->router->addRoute("GET", "/users/:userId", fn() => "second");
        $this->router->addRoute("GET", "/about", fn() => "first");
        $this->router->addRoute("GET", "/about", fn() => "second");

        $this->assertEquals("first", $this->router->dispatch(new ServerRequest("GET", "/users/1"))["route"]["handler"]());
        $this->assertEquals("first", $this->router->dispatch(new ServerRequest("GET", "/about"))["route"]["handler"]());
    }

    public function testParamRegexesCannotHaveCapturingGroups()
    {
        $this->router->addRoute("GET", "/:lang{(en|fr)}", fn() => null);

        $this->expectException(\InvalidArgumentException::class);

        $this->routePath("/en");
    }

    public function testParamRegexesCanHaveNonCapturingGroups()
    {
        $this->router->addRoute("GET", "/:lang{(?:en|fr)}/:page", fn() => null);

        $route = $this->router->dispatch(new ServerRequest("GET", "/fr/about"))["route"];

        $this->assertEquals(["lang" => "fr", "page" => "about"], $route["params"]);
    }

    public function testMalformedOptionalPartsThrowWhenAdded()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->router->addRoute("GET", "/users[/{id}]/posts", fn() => null);
    }

    private function routePath(string $path): ?string
    {
        return $this->router->dispatch(new ServerRequest("GET", $path))["route"]["routePath"] ?? null;
    }
}
