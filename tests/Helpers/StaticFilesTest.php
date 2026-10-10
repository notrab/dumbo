<?php

namespace Dumbo\Tests\Helpers;

use Dumbo\Context;
use Dumbo\Helpers\StaticFiles;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class StaticFilesTest extends TestCase
{
    private string $root;
    private string $public;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . "/dumbo-static-" . uniqid();
        $this->public = $this->root . "/public";

        mkdir($this->public, 0777, true);
        mkdir($this->root . "/public-private", 0777, true);

        file_put_contents($this->public . "/index.html", "<h1>Hello</h1>");
        file_put_contents($this->public . "/app.css", "body { color: red; }");
        file_put_contents($this->public . "/app.js", "console.log(1);");
        file_put_contents($this->root . "/public-private/secret.txt", "secret");
        file_put_contents($this->public . "/my file.txt", "spaced");
    }

    protected function tearDown(): void
    {
        foreach (
            ["/public/index.html", "/public/app.css", "/public/app.js", "/public/my file.txt", "/public-private/secret.txt"]
            as $file
        ) {
            @unlink($this->root . $file);
        }

        @rmdir($this->public);
        @rmdir($this->root . "/public-private");
        @rmdir($this->root);
    }

    private function serve(string $path, array $headers = [])
    {
        $handler = StaticFiles::serve($this->public);
        $context = new Context(
            new ServerRequest("GET", "/" . $path, $headers),
            ["path" => $path],
            "/*"
        );

        return $handler($context);
    }

    public function testServesAFileFromTheDirectory()
    {
        $response = $this->serve("index.html");

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame("<h1>Hello</h1>", (string) $response->getBody());
        $this->assertStringStartsWith('"', $response->getHeaderLine("ETag"));
    }

    public function testSendsTheContentTypeForStylesheetsAndScripts()
    {
        $this->assertSame("text/css", $this->serve("app.css")->getHeaderLine("Content-Type"));
        $this->assertSame("text/javascript", $this->serve("app.js")->getHeaderLine("Content-Type"));
        $this->assertSame("text/html", $this->serve("index.html")->getHeaderLine("Content-Type"));
    }

    public function testDoesNotServeASiblingDirectorySharingThePrefix()
    {
        $response = $this->serve("../public-private/secret.txt");

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testReturnsNotModifiedForAMatchingEtag()
    {
        $etag = $this->serve("index.html")->getHeaderLine("ETag");

        $response = $this->serve("index.html", ["If-None-Match" => $etag]);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame("", (string) $response->getBody());
    }

    public function testPassesThroughAsMiddlewareWhenNoFileMatches()
    {
        $app = new \Dumbo\Dumbo();
        $app->use("/", StaticFiles::serve($this->public));
        $app->get("/api", fn(Context $context) => $context->text("api"));

        $file = $app->handle(new ServerRequest("GET", "/index.html"));
        $route = $app->handle(new ServerRequest("GET", "/api"));

        $this->assertSame("<h1>Hello</h1>", (string) $file->getBody());
        $this->assertSame("api", (string) $route->getBody());
    }

    public function testServesWithAPathParamThroughTheRouter()
    {
        $app = new \Dumbo\Dumbo();
        $app->get("/assets/:path{.+}", StaticFiles::serve($this->public));

        $response = $app->request("/assets/index.html");

        $this->assertSame("<h1>Hello</h1>", (string) $response->getBody());
    }

    public function testWildcardRouteServesTheFullRequestPath()
    {
        $app = new \Dumbo\Dumbo();
        $app->get("/public/*", StaticFiles::serve($this->root));

        $response = $app->request("/public/index.html");

        $this->assertSame("<h1>Hello</h1>", (string) $response->getBody());
    }

    public function testMiddlewareIgnoresTheMatchedRoutesPathParam()
    {
        $app = new \Dumbo\Dumbo();
        $app->use(StaticFiles::serve($this->public));
        $app->get("/docs/{path:.+}", fn(Context $context) => $context->text("page " . $context->req->param("path")));

        $this->assertSame("page app.css", (string) $app->request("/docs/app.css")->getBody());
        $this->assertSame("body { color: red; }", (string) $app->request("/app.css")->getBody());
    }

    public function testMiddlewareOnlyServesGetAndHeadRequests()
    {
        $app = new \Dumbo\Dumbo();
        $app->use(StaticFiles::serve($this->public));
        $app->post("/app.css", fn(Context $context) => $context->text("posted"));

        $this->assertSame("posted", (string) $app->request("/app.css", "POST")->getBody());
        $this->assertSame(200, $app->request("/app.css", "HEAD")->getStatusCode());
        $this->assertSame("text/css", $app->request("/app.css", "HEAD")->getHeaderLine("Content-Type"));
    }

    public function testDecodesEncodedPaths()
    {
        $app = new \Dumbo\Dumbo();
        $app->use(StaticFiles::serve($this->public));
        $app->get("/files/:path{.+}", StaticFiles::serve($this->public));

        $this->assertSame("spaced", (string) $app->request("/my%20file.txt")->getBody());
        $this->assertSame("spaced", (string) $app->request("/files/my%20file.txt")->getBody());
        $this->assertSame(404, $app->request("/files/..%2Fpublic-private%2Fsecret.txt")->getStatusCode());
    }

    public function testRefusesNullBytes()
    {
        $app = new \Dumbo\Dumbo();
        $app->use(StaticFiles::serve($this->public));

        $this->assertSame(404, $app->request("/app.css%00.txt")->getStatusCode());
        $this->assertSame(404, $this->serve("app.css\0")->getStatusCode());
    }
}
