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

    public function testTrailingSlashIsServedWhenRemovalDisabled()
    {
        $app = new Dumbo();
        $app->setRemoveTrailingSlash(false);
        $app->get("/folder", fn(Context $context) => $context->text($context->req->path()));

        $response = $app->handle(new ServerRequest("GET", "/folder/"));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame("/folder", (string) $response->getBody());
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

    public function testRequestHelperSendsQueryHeadersAndJson()
    {
        $app = new Dumbo();
        $app->post("/users", fn(Context $c) => $c->json([
            "page" => $c->req->query("page"),
            "token" => $c->req->header("X-Token"),
            "body" => $c->req->body(),
        ]));

        $response = $app->request("/users?page=2", "POST", ["X-Token" => "t"], ["name" => "Jamie"]);

        $this->assertSame(
            '{"page":"2","token":"t","body":{"name":"Jamie"}}',
            (string) $response->getBody()
        );
    }

    public function testStatusAppliesToLaterResponses()
    {
        $app = new Dumbo();
        $app->post("/users", fn(Context $c) => $c->status(201)->json(["id" => 1]));
        $app->get("/teapot", fn(Context $c) => $c->status(201)->text("no", 418));

        $this->assertSame(201, $app->request("/users", "POST")->getStatusCode());
        $this->assertSame(418, $app->request("/teapot")->getStatusCode());
    }

    public function testRequestTextUrlAndCookies()
    {
        $app = new Dumbo();
        $app->post("/echo", fn(Context $c) => $c->json([
            "text" => $c->req->text(),
            "url" => $c->req->url(),
            "theme" => $c->req->cookie("theme"),
            "cookies" => $c->req->cookie(),
        ]));

        $response = $app->request("/echo?x=1", "POST", ["Cookie" => "theme=dark; lang=en%20GB"], "raw body");

        $this->assertSame([
            "text" => "raw body",
            "url" => "/echo?x=1",
            "theme" => "dark",
            "cookies" => ["theme" => "dark", "lang" => "en GB"],
        ], json_decode((string) $response->getBody(), true));
    }

    public function testMiddlewareReturningNothingAfterNextRunsTheHandlerOnce()
    {
        $calls = 0;
        $app = new Dumbo();
        $app->use(function (Context $c, callable $next) {
            $next($c);
        });
        $app->get("/", function (Context $c) use (&$calls) {
            $calls++;
            return $c->text("ok");
        });

        $response = $app->request("/");

        $this->assertSame(1, $calls);
        $this->assertSame("ok", (string) $response->getBody());
    }

    public function testMiddlewareReturningNothingWithoutNextContinues()
    {
        $app = new Dumbo();
        $app->use(function (Context $c, callable $next) {
            $c->set("seen", "yes");
        });
        $app->get("/", fn(Context $c) => $c->text($c->get("seen")));

        $this->assertSame("yes", (string) $app->request("/")->getBody());
    }

    public function testNotFoundAndErrorHandlersCanReturnArrays()
    {
        $app = new Dumbo();
        $app->notFound(fn(Context $c) => ["error" => "missing"]);
        $app->onError(fn(\Throwable $e, Context $c) => ["error" => $e->getMessage()]);
        $app->get("/boom", fn() => throw new \RuntimeException("boom"));

        $missing = $app->request("/nope");
        $boom = $app->request("/boom");

        $this->assertSame(404, $missing->getStatusCode());
        $this->assertSame('{"error":"missing"}', (string) $missing->getBody());
        $this->assertSame(500, $boom->getStatusCode());
        $this->assertSame('{"error":"boom"}', (string) $boom->getBody());
    }

    public function testNestedScopedMiddlewareCoversRoutesWithParams()
    {
        $api = new Dumbo();
        $api->use("/admin", fn(Context $c, callable $next) => $c->text("denied", 403));
        $api->get("/:page", fn(Context $c) => $c->text("page"));

        $app = new Dumbo();
        $app->route("/api", $api);

        $this->assertSame(403, $app->request("/api/admin")->getStatusCode());
        $this->assertSame(200, $app->request("/api/about")->getStatusCode());
    }

    public function testScopedMiddlewareSurvivesMountingTwice()
    {
        $admin = new Dumbo();
        $admin->use("/secret", fn(Context $c, callable $next) => $c->text("denied", 403));
        $admin->get("/secret/:id", fn(Context $c) => $c->text("secret"));

        $v1 = (new Dumbo())->route("/v1", $admin);
        $app = (new Dumbo())->route("/api", $v1);

        $this->assertSame(403, $app->request("/api/v1/secret/1")->getStatusCode());
    }

    public function testJsonAndFormOnlyReadMatchingContentTypes()
    {
        $app = new Dumbo();
        $app->post("/", fn(Context $c) => ["json" => $c->req->json(), "form" => $c->req->form()]);

        $json = $app->request("/", "POST", [], ["a" => 1]);
        $form = $app->request("/", "POST", ["Content-Type" => "application/x-www-form-urlencoded"], "b=2");

        $this->assertSame('{"json":{"a":1},"form":[]}', (string) $json->getBody());
        $this->assertSame('{"json":[],"form":{"b":"2"}}', (string) $form->getBody());
    }

    public function testMountingUnderAParamPrefixKeepsNestedMiddleware()
    {
        $nested = new Dumbo();
        $nested->use(fn(Context $c, callable $next) => $c->text("global", 401));
        $nested->get("/posts", fn(Context $c) => $c->text("posts"));

        $scoped = new Dumbo();
        $scoped->use("/admin", fn(Context $c, callable $next) => $c->text("scoped", 403));
        $scoped->get("/admin/:id", fn(Context $c) => $c->text("admin"));

        $app = (new Dumbo())->route("/orgs/:org", $nested)->route("/teams/:team", $scoped);

        $this->assertSame(401, $app->request("/orgs/acme/posts")->getStatusCode());
        $this->assertSame(403, $app->request("/teams/a/admin/1")->getStatusCode());
    }

    public function testNestedMiddlewareStaysOffParentAndSiblingRoutes()
    {
        $admin = new Dumbo();
        $admin->use(fn(Context $c, callable $next) => $c->text("denied", 401));
        $admin->get("/admin", fn(Context $c) => $c->text("admin"));

        $posts = (new Dumbo())->get("/posts", fn(Context $c) => $c->text("posts"));

        $app = (new Dumbo())
            ->get("/public", fn(Context $c) => $c->text("public"))
            ->route("/", $admin)
            ->route("/", $posts);

        $this->assertSame(401, $app->request("/admin")->getStatusCode());
        $this->assertSame(200, $app->request("/public")->getStatusCode());
        $this->assertSame(200, $app->request("/posts")->getStatusCode());
    }

    public function testParentScopedMiddlewareRunsBeforeNestedMiddleware()
    {
        $calls = [];
        $nested = new Dumbo();
        $nested->use(function (Context $c, callable $next) use (&$calls) {
            $calls[] = "nested";
            return $next($c);
        });
        $nested->get("/users", fn(Context $c) => $c->text("users"));

        $app = (new Dumbo())->route("/api", $nested);
        $app->use("/api", function (Context $c, callable $next) use (&$calls) {
            $calls[] = "parent";
            return $next($c);
        });

        $app->request("/api/users");

        $this->assertSame(["parent", "nested"], $calls);
    }

    public function testScopedMiddlewareCanUseParamSegments()
    {
        $app = new Dumbo();
        $app->use("/orgs/:org/admin", fn(Context $c, callable $next) => $c->text("denied", 403));
        $app->get("/orgs/:org/admin", fn(Context $c) => $c->text("admin"));
        $app->get("/orgs/:org", fn(Context $c) => $c->text("org"));

        $this->assertSame(403, $app->request("/orgs/acme/admin")->getStatusCode());
        $this->assertSame(200, $app->request("/orgs/acme")->getStatusCode());
    }
}
