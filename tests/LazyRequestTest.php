<?php

namespace Dumbo\Tests;

use Dumbo\Dumbo;
use Dumbo\Globals;
use Dumbo\LazyRequest;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LazyRequestTest extends TestCase
{
    private array $globals;

    protected function setUp(): void
    {
        $this->globals = [$_SERVER, $_GET, $_POST, $_COOKIE, $_FILES];
        $_GET = $_POST = $_COOKIE = $_FILES = [];
    }

    protected function tearDown(): void
    {
        [$_SERVER, $_GET, $_POST, $_COOKIE, $_FILES] = $this->globals;
    }

    public static function servers(): array
    {
        return [
            "plain" => [["REQUEST_METHOD" => "get", "REQUEST_URI" => "/users/42?tab=posts", "HTTP_HOST" => "example.com"]],
            "headers" => [[
                "REQUEST_URI" => "/",
                "HTTP_HOST" => "example.com:8080",
                "HTTP_ACCEPT" => "application/json",
                "HTTP_X_PADDED" => " spaced \t",
                "CONTENT_TYPE" => "text/plain",
                "SERVER_PROTOCOL" => "HTTP/2",
            ]],
            "no host" => [["REQUEST_URI" => "/a", "SERVER_NAME" => "server.local", "SERVER_PORT" => "8000"]],
            "invalid host" => [["REQUEST_URI" => "/a", "HTTP_HOST" => "evil.com/path"]],
            "no request uri" => [["QUERY_STRING" => "a=1"]],
            "escaped path" => [["REQUEST_URI" => "/files/my%20doc", "HTTP_HOST" => "example.com"]],
            "path the uri encodes" => [["REQUEST_URI" => "/caf\u{e9}/100%/a b", "HTTP_HOST" => "example.com"]],
            "basic auth" => [["REQUEST_URI" => "/", "PHP_AUTH_USER" => "user", "PHP_AUTH_PW" => "secret"]],
        ];
    }

    #[DataProvider("servers")]
    public function testMatchesTheFullRequest(array $server)
    {
        $_SERVER = $server;
        $_GET = ["tab" => "posts"];
        $_POST = ["name" => "Dumbo"];
        $_COOKIE = ["session" => "abc"];

        $lazy = LazyRequest::fromGlobals();
        $full = Globals::request();

        $this->assertSame($full->getMethod(), $lazy->getMethod());
        $this->assertSame($full->getUri()->getPath(), LazyRequest::pathOf($lazy));
        $this->assertSame($full->getProtocolVersion(), $lazy->getProtocolVersion());
        $this->assertSame($full->getQueryParams(), $lazy->getQueryParams());
        $this->assertSame($full->getParsedBody(), $lazy->getParsedBody());
        $this->assertSame($full->getCookieParams(), $lazy->getCookieParams());
        $this->assertSame($full->getServerParams(), $lazy->getServerParams());
        $this->assertSame($full->getAttributes(), $lazy->getAttributes());

        foreach (["Host", "host", "Accept", "X-Padded", "Content-Type", "Authorization", "Missing"] as $name) {
            $this->assertSame($full->getHeader($name), $lazy->getHeader($name), $name);
            $this->assertSame($full->getHeaderLine($name), $lazy->getHeaderLine($name), $name);
            $this->assertSame($full->hasHeader($name), $lazy->hasHeader($name), $name);
        }

        $this->assertSame($full->getHeaders(), $lazy->getHeaders());
        $this->assertSame((string) $full->getUri(), (string) $lazy->getUri());
    }

    public function testReadsTheGlobalsAsTheyWereWhenMade()
    {
        $_SERVER = ["REQUEST_METHOD" => "POST", "REQUEST_URI" => "/a"];
        $_GET = ["a" => "1"];

        $lazy = LazyRequest::fromGlobals();
        $_SERVER = ["REQUEST_METHOD" => "GET", "REQUEST_URI" => "/b"];
        $_GET = ["b" => "2"];

        $this->assertSame("POST", $lazy->getMethod());
        $this->assertSame("/a", LazyRequest::pathOf($lazy));
        $this->assertSame(["a" => "1"], $lazy->getQueryParams());
        $this->assertSame("/a", $lazy->getUri()->getPath());
        $this->assertSame(["a" => "1"], $lazy->toPsr7()->getQueryParams());
    }

    public function testBuildsTheFullRequestOnce()
    {
        $_SERVER = ["REQUEST_URI" => "/"];

        $lazy = LazyRequest::fromGlobals();

        $this->assertSame($lazy->toPsr7(), $lazy->toPsr7());
    }

    public function testWithMethodsReturnAFullRequest()
    {
        $_SERVER = ["REQUEST_URI" => "/a", "HTTP_HOST" => "example.com"];

        $lazy = LazyRequest::fromGlobals();
        $request = $lazy->withAttribute("user", "jamie")->withHeader("X-Test", "1");

        $this->assertNotInstanceOf(LazyRequest::class, $request);
        $this->assertSame("jamie", $request->getAttribute("user"));
        $this->assertSame("1", $request->getHeaderLine("X-Test"));
        $this->assertSame("/a", $request->getUri()->getPath());
        $this->assertNull($lazy->getAttribute("user"));
        $this->assertFalse($lazy->hasHeader("X-Test"));
    }

    public function testPathOfOtherRequestsUsesTheirUri()
    {
        $request = Globals::create(["REQUEST_URI" => "/x"], [], [], [], [], [])->withUri(new Uri("http://example.com/y"));

        $this->assertSame("/y", LazyRequest::pathOf($request));
    }

    public function testRunServesTheRequestFromGlobals()
    {
        $_SERVER = [
            "REQUEST_METHOD" => "POST",
            "REQUEST_URI" => "/users/42?tab=posts",
            "HTTP_HOST" => "example.com",
            "HTTP_X_TOKEN" => "abc",
            "HTTP_COOKIE" => "session=xyz",
        ];
        $_GET = ["tab" => "posts"];
        $_POST = ["name" => "Dumbo"];

        $app = new Dumbo();
        $app->post("/users/:id", fn($c) => $c->json([
            "id" => $c->req->param("id"),
            "path" => $c->req->path(),
            "url" => $c->req->url(),
            "tab" => $c->req->query("tab"),
            "token" => $c->req->header("X-Token"),
            "name" => $c->req->raw()->getParsedBody()["name"] ?? null,
            "session" => $c->req->cookie("session"),
        ]));

        ob_start();
        $app->run();

        $this->assertSame([
            "id" => "42",
            "path" => "/users/42",
            "url" => "http://example.com/users/42?tab=posts",
            "tab" => "posts",
            "token" => "abc",
            "name" => "Dumbo",
            "session" => "xyz",
        ], json_decode(ob_get_clean(), true));
    }

    public function testRunPassesPsr15MiddlewareChangesOn()
    {
        $_SERVER = ["REQUEST_URI" => "/", "HTTP_HOST" => "example.com"];

        $app = new Dumbo();
        $app->use(new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request->withAttribute("user", "jamie"));
            }
        });
        $app->get("/", fn($c) => $c->text($c->req->raw()->getAttribute("user") . " " . $c->req->path()));

        ob_start();
        $app->run();

        $this->assertSame("jamie /", ob_get_clean());
    }
}
