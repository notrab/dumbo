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
        file_put_contents($this->root . "/public-private/secret.txt", "secret");
    }

    protected function tearDown(): void
    {
        foreach (
            ["/public/index.html", "/public-private/secret.txt"]
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
}
