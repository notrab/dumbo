<?php

namespace Dumbo\Jumbo\Tests;

use Dumbo\Dumbo;
use Dumbo\Jumbo\Builder;
use Dumbo\Jumbo\Site;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    private string $out;

    protected function setUp(): void
    {
        $this->out = sys_get_temp_dir() . "/jumbo-build-" . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->out)) {
            exec("rm -rf " . escapeshellarg($this->out));
        }
    }

    private function site(string $fixture = "basic", array $config = []): Site
    {
        $dir = __DIR__ . "/fixtures/$fixture";

        return new Site($dir, ["name" => "Fixture", ...$config], new Dumbo(Dumbo::ENV_PRODUCTION));
    }

    public function testPathsSkipOnDemandPagesAndExpandParams(): void
    {
        $this->assertSame(
            ["/", "/about", "/feed.json", "/old", "/plain", "/blog/hello", "/blog/second", "/files/a/b", "/files/c%20d"],
            (new Builder($this->site(), $this->out))->paths()
        );
    }

    public function testBuild(): void
    {
        $files = (new Builder($this->site(), $this->out))->build();

        $this->assertContains("index.html", $files);
        $this->assertContains("about/index.html", $files);
        $this->assertContains("blog/hello/index.html", $files);
        $this->assertContains("files/c d/index.html", $files);
        $this->assertContains("feed.json", $files);
        $this->assertContains("css/site.css", $files);
        $this->assertNotContains("live/index.html", $files);

        $this->assertStringContainsString("<h1>Welcome to Fixture</h1>", file_get_contents("$this->out/index.html"));
        $this->assertStringContainsString("Nothing at /404", file_get_contents("$this->out/404.html"));
        $this->assertStringContainsString('http-equiv="refresh" content="0; url=/about"', file_get_contents("$this->out/old/index.html"));
        $this->assertSame('{"slugs":["hello","second"]}', file_get_contents("$this->out/feed.json"));
    }

    public function testBuildUnderABasePath(): void
    {
        (new Builder($this->site(), $this->out, "/sub/"))->build();

        $this->assertStringContainsString('url=/sub/about"', file_get_contents("$this->out/old/index.html"));
    }

    public function testBuildEmptiesTheOutputFirst(): void
    {
        mkdir("$this->out/stale", 0777, true);
        file_put_contents("$this->out/stale/file.txt", "old");

        (new Builder($this->site(), $this->out))->build();

        $this->assertFileDoesNotExist("$this->out/stale/file.txt");
    }

    public function testRefusesToEmptyADirectoryHoldingTheSite(): void
    {
        $this->expectExceptionMessage("Refusing to empty");

        (new Builder($this->site(), __DIR__ . "/fixtures"))->build();
    }

    public function testTemplatesWithParamsNeedPaths(): void
    {
        $this->expectExceptionMessage("needs paths()");

        (new Builder($this->site("params-without-paths"), $this->out))->paths();
    }

    public function testFileFormat(): void
    {
        $files = (new Builder($this->site("basic", ["build" => ["format" => "file"]]), $this->out))->build();

        $this->assertContains("index.html", $files);
        $this->assertContains("about.html", $files);
        $this->assertContains("blog/hello.html", $files);
        $this->assertContains("feed.json", $files);
        $this->assertContains("404.html", $files);
    }

    public function testSitemapListsIndexablePages(): void
    {
        $site = $this->site("features", ["url" => "https://example.com/sub", "sitemap" => fn(string $url) => !str_contains($url, "/tags/")]);
        $site->app->get("/api/ping", fn($context) => $context->text("pong"));
        (new Builder($site, $this->out))->build();

        $sitemap = file_get_contents("$this->out/sitemap.xml");

        $this->assertStringContainsString("<loc>https://example.com/sub/</loc>", $sitemap);
        $this->assertStringContainsString("<loc>https://example.com/sub/about</loc>", $sitemap);
        $this->assertStringNotContainsString("hidden", $sitemap);
        $this->assertStringNotContainsString("/tags/", $sitemap);
        $this->assertStringNotContainsString("/blog/1", $sitemap);
        $this->assertNotFalse(simplexml_load_string($sitemap));
    }

    public function testNoSitemapWithoutAUrlOrWhenTurnedOff(): void
    {
        $this->assertNotContains("sitemap.xml", (new Builder($this->site("features"), $this->out))->build());
        $this->assertNotContains("sitemap.xml", (new Builder($this->site("features", ["url" => "https://example.com", "sitemap" => false]), $this->out))->build());
    }

    public function testFindsBrokenLinks(): void
    {
        $site = $this->site("features");
        $site->app->get("/api/ping", fn($context) => $context->text("pong"));
        $builder = new Builder($site, $this->out);
        $builder->build();

        $this->assertSame([["page" => "/links", "link" => "/missing"]], $builder->broken());
    }

    public function testBuildHooks(): void
    {
        $site = $this->site();
        $events = [];
        $site->on("build:start", function (string $out) use (&$events) {
            $events[] = ["start", $out];
        });
        $site->on("build:done", function (string $out, array $files) use (&$events) {
            $events[] = ["done", $out, in_array("index.html", $files, true)];
        });

        (new Builder($site, $this->out))->build();

        $this->assertSame([["start", $this->out], ["done", $this->out, true]], $events);
    }

    public function testRedirectsAndLocalesAreBuilt(): void
    {
        $site = $this->site("features", [
            "i18n" => ["locales" => ["en", "fr"]],
            "redirects" => ["/old" => "https://example.com/new", "/posts/[slug]" => "/blog/[slug]"],
        ]);
        $paths = (new Builder($site, $this->out, "/sub"))->paths();

        $this->assertContains("/fr", $paths);
        $this->assertContains("/fr/about", $paths);
        $this->assertContains("/fr/blog/3", $paths);
        $this->assertContains("/old", $paths);
        $this->assertNotContains("/posts/{slug}", $paths);

        (new Builder($site, $this->out, "/sub"))->build();

        $this->assertStringContainsString('<html lang="fr">', file_get_contents("$this->out/fr/about/index.html"));
        $this->assertStringContainsString('url=https://example.com/new"', file_get_contents("$this->out/old/index.html"));
    }

    public function testBuildErrorsSayWhatFailed(): void
    {
        $dir = "$this->out-site";
        mkdir("$dir/pages", 0777, true);
        file_put_contents("$dir/pages/index.php", "<?php throw new LogicException('Bad template');");

        try {
            (new Builder(new Site($dir, [], new Dumbo(Dumbo::ENV_PRODUCTION)), $this->out))->build();
            $this->fail("The build should fail");
        } catch (\RuntimeException $error) {
            $this->assertSame("GET / responded with 500: Bad template", $error->getMessage());
            $this->assertInstanceOf(\LogicException::class, $error->getPrevious());
        } finally {
            exec("rm -rf " . escapeshellarg($dir));
        }
    }
}
