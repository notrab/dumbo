<?php

namespace Dumbo\Tests;

use Dumbo\Context;
use Dumbo\Dumbo;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Psr15Test extends TestCase
{
    private function attributeMiddleware(): MiddlewareInterface
    {
        return new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $response = $handler->handle($request->withAttribute("user", "jamie"));

                return $response->withHeader("X-Psr15", "ran");
            }
        };
    }

    private function blockingMiddleware(): MiddlewareInterface
    {
        return new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                return new Response(403, [], "blocked");
            }
        };
    }

    public function testAppIsAPsr15RequestHandler()
    {
        $app = new Dumbo();
        $app->get("/", fn(Context $c) => $c->text("hi"));

        $this->assertInstanceOf(RequestHandlerInterface::class, $app);
        $this->assertSame("hi", (string) $app->handle(new ServerRequest("GET", "/"))->getBody());
    }

    public function testGlobalPsr15MiddlewareCanChangeTheRequest()
    {
        $app = new Dumbo();
        $app->use($this->attributeMiddleware());
        $app->get("/users/:id", fn(Context $c) => $c->text(
            $c->req->raw()->getAttribute("user") . " " . $c->req->param("id")
        ));

        $response = $app->handle(new ServerRequest("GET", "/users/7"));

        $this->assertSame("jamie 7", (string) $response->getBody());
        $this->assertSame("ran", $response->getHeaderLine("X-Psr15"));
    }

    public function testPsr15MiddlewareCanShortCircuit()
    {
        $app = new Dumbo();
        $app->use("/admin", $this->blockingMiddleware());
        $app->get("/admin", fn(Context $c) => $c->text("admin"));
        $app->get("/public", fn(Context $c) => $c->text("public"));

        $this->assertSame(403, $app->handle(new ServerRequest("GET", "/admin"))->getStatusCode());
        $this->assertSame(200, $app->handle(new ServerRequest("GET", "/public"))->getStatusCode());
    }

    public function testRouteLevelPsr15Middleware()
    {
        $app = new Dumbo();
        $app->get("/admin", fn(Context $c) => $c->text("admin"), [$this->blockingMiddleware()]);

        $this->assertSame(403, $app->handle(new ServerRequest("GET", "/admin"))->getStatusCode());
    }

    public function testPsr15AndCallableMiddlewareShareTheContext()
    {
        $app = new Dumbo();
        $app->use(function (Context $c, callable $next) {
            $c->set("before", "set");
            return $next($c);
        });
        $app->use($this->attributeMiddleware());
        $app->get("/", fn(Context $c) => $c->text(
            $c->get("before") . " " . $c->req->raw()->getAttribute("user")
        ));

        $this->assertSame("set jamie", (string) $app->handle(new ServerRequest("GET", "/"))->getBody());
    }

    public function testValidatedDataSurvivesARequestChange()
    {
        $app = new Dumbo();
        $app->use(\Dumbo\Middleware\Validator::validator("query", fn(array $q) => $q));
        $app->use($this->attributeMiddleware());
        $app->get("/", fn(Context $c) => $c->json($c->req->valid("query")));

        $this->assertSame('{"a":"1"}', (string) $app->request("/?a=1")->getBody());
    }
}
