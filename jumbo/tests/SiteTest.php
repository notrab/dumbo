<?php

namespace Dumbo\Jumbo\Tests;

use Dumbo\Jumbo\Route;
use Dumbo\Jumbo\Site;
use PHPUnit\Framework\TestCase;

class SiteTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        $this->site = Site::load(__DIR__ . "/fixtures/basic");
    }

    public function testDiscoversPagesMostSpecificFirst(): void
    {
        $this->assertSame(
            ["/", "/about", "/feed.json", "/live", "/old", "/plain", "/blog/{slug}", "/files/{path:.+}"],
            array_map(fn(Route $route) => $route->path, $this->site->routes())
        );
    }

    public function testSkipsUnderscoredFiles(): void
    {
        $this->assertSame(404, $this->site->app->request("/_drafts/secret")->getStatusCode());
    }

    public function testTemplatePageWithNestedLayoutsAndSlots(): void
    {
        $response = $this->site->app->request("/");

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            "<html><head><title>Home</title><meta name=\"page\" content=\"home\"></head><body><main><h1>Welcome to Fixture</h1>\n</main>\n</body></html>\n",
            (string) $response->getBody()
        );
    }

    public function testMarkdownPageWithLayoutAndComponents(): void
    {
        $body = (string) $this->site->app->request("/about")->getBody();

        $this->assertStringContainsString("<title>About us | Fixture</title>", $body);
        $this->assertStringContainsString("<aside class=\"note-info\"><p>Some <strong>notes</strong>.</p></aside>", $body);
        $this->assertStringContainsString("<a href=\"#team\">Team</a>", $body);
    }

    public function testMarkdownPageWithoutLayout(): void
    {
        $this->assertSame("<h1>Plain</h1>\n", (string) $this->site->app->request("/plain")->getBody());
    }

    public function testPageWithParams(): void
    {
        $body = (string) $this->site->app->request("/blog/hello")->getBody();

        $this->assertStringContainsString("<title>Hello world</title>", $body);
        $this->assertStringContainsString("<p>Hi</p>", $body);
        $this->assertSame("file a/b", (string) $this->site->app->request("/files/a/b")->getBody());
    }

    public function testMissingContentUsesTheNotFoundPage(): void
    {
        $response = $this->site->app->request("/blog/nope");

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString("<title>Not found</title>", (string) $response->getBody());
        $this->assertStringContainsString("Nothing at /blog/nope", (string) $response->getBody());
    }

    public function testContentTypeFromTheFileName(): void
    {
        $response = $this->site->app->request("/feed.json");

        $this->assertStringStartsWith("application/json", $response->getHeaderLine("Content-Type"));
        $this->assertSame(["slugs" => ["hello", "second"]], json_decode((string) $response->getBody(), true));
    }

    public function testPagesReturningResponses(): void
    {
        $this->assertSame("live", (string) $this->site->app->request("/live")->getBody());
        $this->assertSame("/about", $this->site->app->request("/old")->getHeaderLine("Location"));
    }

    public function testServesPublicFiles(): void
    {
        $this->assertSame("body{}\n", (string) $this->site->app->request("/css/site.css")->getBody());
    }

    public function testFindsContent(): void
    {
        $this->assertSame("Hello world", $this->site->find("hello")["title"]);
        $this->assertSame("Second post", $this->site->find("second")["title"]);
        $this->assertNull($this->site->find("../app"));
        $this->assertNull($this->site->find("missing"));
    }

    public function testRoutesCanBeAddedToTheApp(): void
    {
        $this->site->app->get("/api/ping", fn($context) => $context->text("pong"));

        $this->assertSame("pong", (string) $this->site->app->request("/api/ping")->getBody());
    }

    public function testTemplateErrorsLeaveNoOutputBuffers(): void
    {
        $dir = sys_get_temp_dir() . "/jumbo-" . uniqid();
        mkdir("$dir/pages", 0777, true);
        file_put_contents("$dir/pages/index.php", "<?php \$this->start('x') ?>oops<?php throw new RuntimeException('boom') ?>");

        $level = ob_get_level();
        $response = (new Site($dir, [], new \Dumbo\Dumbo("production")))->app->request("/");

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame($level, ob_get_level());

        unlink("$dir/pages/index.php");
        rmdir("$dir/pages");
        rmdir($dir);
    }
}
