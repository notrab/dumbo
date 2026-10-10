<?php

use PHPUnit\Framework\TestCase;
use Dumbo\Context;
use Dumbo\Dumbo;
use GuzzleHttp\Psr7\ServerRequest;

class DumboTest extends TestCase
{
    public function testEnvironmentDefaultsToDevelopment()
    {
        unset($_SERVER["DUMBO_ENV"]);
        putenv("DUMBO_ENV");

        $app = new Dumbo();

        $this->assertSame(Dumbo::ENV_DEVELOPMENT, $app->getEnvironment());
        $this->assertTrue($app->isDevelopment());
    }

    public function testEnvironmentIsReadFromServerThenEnv()
    {
        putenv("DUMBO_ENV=testing");
        $this->assertSame(Dumbo::ENV_TESTING, (new Dumbo())->getEnvironment());

        $_SERVER["DUMBO_ENV"] = "production";
        $this->assertSame(Dumbo::ENV_PRODUCTION, (new Dumbo())->getEnvironment());

        unset($_SERVER["DUMBO_ENV"]);
        putenv("DUMBO_ENV");
    }

    public function testEnvironmentCanBePassedExplicitly()
    {
        $app = new Dumbo(Dumbo::ENV_PRODUCTION);

        $this->assertSame(Dumbo::ENV_PRODUCTION, $app->getEnvironment());
        $this->assertFalse($app->isDevelopment());
    }

    public function testErrorsAreHiddenOutsideDevelopment()
    {
        $app = new Dumbo(Dumbo::ENV_PRODUCTION);
        $app->get("/", fn() => throw new \RuntimeException("secret"));

        $response = $app->handle(new ServerRequest("GET", "/"));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringNotContainsString("secret", (string) $response->getBody());
    }

    public function testErrorHandlerReceivesErrors()
    {
        $app = new Dumbo();
        $app->onError(fn($error, Context $c) => $c->text($error->getMessage(), 418));
        $app->get("/", fn() => throw new \TypeError("boom"));

        $response = $app->handle(new ServerRequest("GET", "/"));

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame("boom", (string) $response->getBody());
    }

    public function testMethodNotAllowedReturns405WithAllowHeader()
    {
        $app = new Dumbo();
        $app->get("/users", fn(Context $context) => $context->text("users"));
        $app->post("/users", fn(Context $context) => $context->text("created"));

        $response = $app->handle(new ServerRequest("DELETE", "/users"));

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals("GET, POST", $response->getHeaderLine("Allow"));
        $this->assertEquals(
            "405 Method Not Allowed",
            (string) $response->getBody()
        );
    }

    public function testUnknownPathStillReturns404()
    {
        $app = new Dumbo();
        $app->get("/users", fn(Context $context) => $context->text("users"));

        $response = $app->handle(new ServerRequest("GET", "/nope"));

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertFalse($response->hasHeader("Allow"));
        $this->assertEquals("404 Not Found", (string) $response->getBody());
    }

    public function testHeadRequestFallsBackToGetRoute()
    {
        $app = new Dumbo();
        $app->get("/users", fn(Context $context) => $context->text("users"));

        $response = $app->handle(new ServerRequest("HEAD", "/users"));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testMiddlewareRunsForMethodNotAllowed()
    {
        $app = new Dumbo();
        $app->use(
            fn(Context $context, callable $next) => $next($context)->withHeader(
                "X-Middleware",
                "ran"
            )
        );
        $app->get("/users", fn(Context $context) => $context->text("users"));

        $response = $app->handle(new ServerRequest("DELETE", "/users"));

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals("ran", $response->getHeaderLine("X-Middleware"));
    }

    public function testNestedAppRunsParentAndOwnMiddlewareOnce()
    {
        $calls = [];

        $app = new Dumbo();
        $app->use(function (Context $context, callable $next) use (&$calls) {
            $calls[] = "parent";
            return $next($context);
        });

        $api = new Dumbo();
        $api->use(function (Context $context, callable $next) use (&$calls) {
            $calls[] = "api";
            return $next($context);
        });
        $api->get("/users", fn(Context $context) => $context->text("users"));

        $app->route("/api", $api);
        $response = $app->handle(new ServerRequest("GET", "/api/users"));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(["parent", "api"], $calls);
    }

    public function testNestedAppKeepsPathScopedMiddleware()
    {
        $api = new Dumbo();
        $api->use(
            "/admin",
            fn(Context $context, callable $next) => $context->text("denied", 403)
        );
        $api->get("/admin/:id", fn(Context $context) => $context->text("admin"));
        $api->get("/public", fn(Context $context) => $context->text("public"));

        $app = new Dumbo();
        $app->route("/api", $api);

        $admin = $app->handle(new ServerRequest("GET", "/api/admin/1"));
        $public = $app->handle(new ServerRequest("GET", "/api/public"));

        $this->assertEquals(403, $admin->getStatusCode());
        $this->assertEquals(200, $public->getStatusCode());
    }

    public function testPathScopedMiddlewareMatchesWholeSegments()
    {
        $app = new Dumbo();
        $app->use(
            "/admin",
            fn(Context $context, callable $next) => $context->text("denied", 403)
        );
        $app->get("/admin", fn(Context $context) => $context->text("admin"));
        $app->get("/admin/users", fn(Context $context) => $context->text("users"));
        $app->get("/administrator", fn(Context $context) => $context->text("ok"));

        $this->assertEquals(
            403,
            $app->handle(new ServerRequest("GET", "/admin"))->getStatusCode()
        );
        $this->assertEquals(
            403,
            $app->handle(new ServerRequest("GET", "/admin/users"))->getStatusCode()
        );
        $this->assertEquals(
            200,
            $app->handle(new ServerRequest("GET", "/administrator"))->getStatusCode()
        );
    }

    public function testHandlerResponseReplacesMiddlewareBody()
    {
        $app = new Dumbo();
        $app->use(function (Context $context, callable $next) {
            $context->text("fallback");
            return $next($context);
        });
        $app->get("/", fn(Context $context) => $context->json(["ok" => true]));

        $response = $app->handle(new ServerRequest("GET", "/"));

        $this->assertSame('{"ok":true}', (string) $response->getBody());
    }

    public function testTrailingSlashRedirectsGetWith301()
    {
        $app = new Dumbo();
        $app->get("/users", fn(Context $context) => $context->text("users"));

        $response = $app->handle(new ServerRequest("GET", "/users/?page=2"));

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame("/users?page=2", $response->getHeaderLine("Location"));
    }

    public function testTrailingSlashRedirectsOtherMethodsWith308()
    {
        $app = new Dumbo();
        $app->post("/users", fn(Context $context) => $context->text("created"));

        $response = $app->handle(new ServerRequest("POST", "/users/"));

        $this->assertSame(308, $response->getStatusCode());
        $this->assertSame("/users", $response->getHeaderLine("Location"));
    }

    public function testNotFoundHandlerReplacesDefault404()
    {
        $app = new Dumbo();
        $app->notFound(fn(Context $c) => $c->json(["error" => "missing"], 404));

        $response = $app->handle(new ServerRequest("GET", "/nope"));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"error":"missing"}', (string) $response->getBody());
    }

    public function testNotFoundHandlerRunsAfterMiddleware()
    {
        $app = new Dumbo();
        $app->use(function (Context $c, callable $next) {
            $c->set("user", "jamie");
            return $next($c)->withHeader("X-Middleware", "ran");
        });
        $app->notFound(fn(Context $c) => $c->text("no page for " . $c->get("user"), 404));

        $response = $app->handle(new ServerRequest("GET", "/nope"));

        $this->assertSame("no page for jamie", (string) $response->getBody());
        $this->assertSame("ran", $response->getHeaderLine("X-Middleware"));
    }

    public function testContextNotFoundUsesTheAppHandler()
    {
        $app = new Dumbo();
        $app->notFound(fn(Context $c) => $c->text("custom", 404));
        $app->get("/users/:id", fn(Context $c) => $c->notFound());

        $response = $app->handle(new ServerRequest("GET", "/users/1"));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame("custom", (string) $response->getBody());
    }

    public function testContextNotFoundDefaultsToPlain404()
    {
        $app = new Dumbo();
        $app->get("/users/:id", fn(Context $c) => $c->notFound());

        $response = $app->handle(new ServerRequest("GET", "/users/1"));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame("404 Not Found", (string) $response->getBody());
    }

    public function testOnRegistersSeveralMethods()
    {
        $app = new Dumbo();
        $app->on(["GET", "post"], "/form", fn(Context $c) => $c->text($c->req->method()));

        $this->assertSame("GET", (string) $app->handle(new ServerRequest("GET", "/form"))->getBody());
        $this->assertSame("POST", (string) $app->handle(new ServerRequest("POST", "/form"))->getBody());
        $this->assertSame(405, $app->handle(new ServerRequest("PUT", "/form"))->getStatusCode());
    }

    public function testRouteMethodsAreChainable()
    {
        $app = (new Dumbo())
            ->use(fn(Context $c, callable $next) => $next($c))
            ->get("/a", fn(Context $c) => $c->text("a"))
            ->post("/b", fn(Context $c) => $c->text("b"));

        $this->assertSame("a", (string) $app->handle(new ServerRequest("GET", "/a"))->getBody());
        $this->assertSame("b", (string) $app->handle(new ServerRequest("POST", "/b"))->getBody());
    }

    public function testPathScopedMiddlewareAcceptsAWildcard()
    {
        $app = new Dumbo();
        $app->use("/admin/*", fn(Context $c, callable $next) => $c->text("denied", 403));
        $app->get("/admin", fn(Context $c) => $c->text("admin"));
        $app->get("/admin/users", fn(Context $c) => $c->text("users"));
        $app->get("/public", fn(Context $c) => $c->text("public"));

        $this->assertSame(403, $app->handle(new ServerRequest("GET", "/admin"))->getStatusCode());
        $this->assertSame(403, $app->handle(new ServerRequest("GET", "/admin/users"))->getStatusCode());
        $this->assertSame(200, $app->handle(new ServerRequest("GET", "/public"))->getStatusCode());
    }
}
