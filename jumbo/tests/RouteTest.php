<?php

namespace Dumbo\Jumbo\Tests;

use Dumbo\Jumbo\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    public static function files(): array
    {
        return [
            ["index.php", "/", []],
            ["about.md", "/about", []],
            ["docs/index.php", "/docs", []],
            ["blog/[slug].php", "/blog/{slug}", ["slug" => false]],
            ["docs/[...path].php", "/docs/{path:.+}", ["path" => true]],
            ["[lang]/posts/[id].php", "/{lang}/posts/{id}", ["lang" => false, "id" => false]],
            ["sitemap.xml.php", "/sitemap.xml", []],
            ["post-[id].php", "/post-{id}", ["id" => false]],
        ];
    }

    #[DataProvider("files")]
    public function testFromFile(string $file, string $path, array $params): void
    {
        $this->assertSame([$path, $params], Route::fromFile($file));
    }

    public function testRejectsRepeatedParams(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Route::fromFile("[id]/[id].php");
    }

    public function testUrl(): void
    {
        $this->assertSame("/blog/hello%20world", (new Route("/blog/{slug}", "x.php", ["slug" => false]))->url(["slug" => "hello world"]));
        $this->assertSame("/docs/a/b%3F", (new Route("/docs/{path:.+}", "x.php", ["path" => true]))->url(["path" => "a/b?"]));
    }

    public static function invalidValues(): array
    {
        return [
            ["/blog/{slug}", []],
            ["/blog/{slug}", ["slug" => ""]],
            ["/blog/{slug}", ["slug" => ".."]],
            ["/blog/{slug}", ["slug" => "a/b"]],
            ["/docs/{path:.+}", ["path" => "a/../b"]],
        ];
    }

    #[DataProvider("invalidValues")]
    public function testUrlRejectsInvalidValues(string $path, array $params): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Route($path, "x.php"))->url($params);
    }

    public function testContentType(): void
    {
        $this->assertSame("text/html", (new Route("/about", "x.php"))->contentType());
        $this->assertSame("text/html", (new Route("/docs/{path:.+}", "x.php"))->contentType());
        $this->assertSame("application/xml", (new Route("/sitemap.xml", "x.php"))->contentType());
        $this->assertSame("application/json", (new Route("/feed.json", "x.php"))->contentType());
    }
}
