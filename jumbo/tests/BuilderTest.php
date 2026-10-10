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

    private function site(string $fixture = "basic"): Site
    {
        $dir = __DIR__ . "/fixtures/$fixture";

        return new Site($dir, ["name" => "Fixture"], new Dumbo(Dumbo::ENV_PRODUCTION));
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
}
