<?php

namespace Dumbo\Jumbo\Tests;

use Dumbo\Dumbo;
use Dumbo\Jumbo\Builder;
use Dumbo\Jumbo\Site;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension("gd")]
class ImagesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . "/jumbo-images-test-" . uniqid();
        mkdir("$this->dir/public/photos", 0777, true);
        mkdir("$this->dir/pages", 0777, true);

        $image = imagecreatetruecolor(1000, 500);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 50, 50));
        imagepng($image, "$this->dir/public/photos/red box.png");
        file_put_contents("$this->dir/public/logo.svg", '<svg xmlns="http://www.w3.org/2000/svg"/>');

        file_put_contents("$this->dir/pages/index.php", '<html><head></head><body><?= $site->img("/photos/red%20box.png", "A red box", width: 400) ?></body></html>');
        file_put_contents("$this->dir/pages/post.md", "![A **red** box](/photos/red%20box.png \"Red\")\n\n![Logo](/logo.svg)\n");
    }

    protected function tearDown(): void
    {
        exec("rm -rf " . escapeshellarg($this->dir));
    }

    private function site(array $config = []): Site
    {
        return new Site($this->dir, ["images" => ["widths" => [320, 640, 960], "cache" => "$this->dir/cache"], ...$config], new Dumbo(Dumbo::ENV_PRODUCTION));
    }

    public function testUrlsRoundUpToAnAllowedWidth(): void
    {
        $site = $this->site();
        $version = substr(md5(filemtime("$this->dir/public/photos/red box.png") . ":" . filesize("$this->dir/public/photos/red box.png")), 0, 8);

        $this->assertSame("/_image/640/photos/red%20box.png.webp?v=$version", $site->image("/photos/red%20box.png", 500));
        $this->assertSame("/_image/1000/photos/red%20box.png.webp?v=$version", $site->image("/photos/red%20box.png"));
        $this->assertSame("/_image/1000/photos/red%20box.png.webp?v=$version", $site->image("/photos/red%20box.png", 5000));
        $this->assertSame("/_image/320/photos/red%20box.png?v=$version", $site->image("/photos/red%20box.png", 100, "png"));
        $this->assertSame("/logo.svg", $site->image("/logo.svg", 100));
        $this->assertSame("/missing.png", $site->image("/missing.png", 100));
    }

    public function testImgTag(): void
    {
        $html = $this->site()->img("/photos/red%20box.png", "A \"red\" box", width: 400, attributes: ["loading" => "eager", "class" => "hero"]);

        $this->assertMatchesRegularExpression('#^<img src="/_image/640/photos/red%20box.png.webp\?v=\w+"#', $html);
        $this->assertMatchesRegularExpression('#srcset="/_image/320/[^ ]+ 320w, /_image/640/[^ ]+ 640w"#', $html);
        $this->assertStringContainsString('sizes="(min-width: 400px) 400px, 100vw" width="400" height="200" alt="A &quot;red&quot; box" loading="eager" decoding="async" class="hero">', $html);
        $this->assertSame('<img src="/logo.svg" alt="Logo">', $this->site()->img("/logo.svg", "Logo", width: 100));
    }

    public function testServesResizedImages(): void
    {
        $site = $this->site();
        $response = $site->app->request($site->image("/photos/red%20box.png", 320));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame("image/webp", $response->getHeaderLine("Content-Type"));
        $this->assertSame("public, max-age=31536000, immutable", $response->getHeaderLine("Cache-Control"));
        $this->assertSame([320, 160], array_slice(getimagesizefromstring((string) $response->getBody()), 0, 2));

        $png = $site->app->request("/_image/640/photos/red%20box.png");
        $this->assertSame("image/png", $png->getHeaderLine("Content-Type"));
        $this->assertSame([640, 320], array_slice(getimagesizefromstring((string) $png->getBody()), 0, 2));
    }

    public function testOnlyAllowedSizesAreMade(): void
    {
        $site = $this->site();

        $this->assertSame(404, $site->app->request("/_image/500/photos/red%20box.png.webp")->getStatusCode());
        $this->assertSame(404, $site->app->request("/_image/2000/photos/red%20box.png.webp")->getStatusCode());
        $this->assertSame(404, $site->app->request("/_image/320/photos/red%20box.png.gif")->getStatusCode());
        $this->assertSame(404, $site->app->request("/_image/320/logo.svg")->getStatusCode());
        $this->assertSame(404, $site->app->request("/_image/320/../pages/index.php")->getStatusCode());
        $this->assertSame(200, $site->app->request("/_image/1000/photos/red%20box.png.jpg")->getStatusCode());
    }

    public function testMarkdownImagesAreResponsive(): void
    {
        $html = (string) $this->site()->app->request("/post")->getBody();

        $this->assertMatchesRegularExpression('#<img src="/_image/1000/photos/red%20box.png.webp\?v=\w+" srcset="[^"]+ 320w, [^"]+ 640w, [^"]+ 960w, [^"]+ 1000w" sizes="\(min-width: 1000px\) 1000px, 100vw" width="1000" height="500" alt="A red box" loading="lazy" decoding="async" title="Red">#', $html);
        $this->assertStringContainsString('<img src="/logo.svg" alt="Logo" />', $html);
        $this->assertStringNotContainsString("_image", (string) $this->site(["markdown" => ["images" => false]])->app->request("/post")->getBody());
    }

    public function testBuildsWriteTheImagesPagesUse(): void
    {
        $out = "$this->dir/dist";
        $files = (new Builder($this->site(["url" => "https://example.com/sub"]), $out))->build();

        $this->assertContains("_image/640/photos/red box.png.webp", $files);
        $this->assertContains("_image/320/photos/red box.png.webp", $files);
        $this->assertContains("_image/1000/photos/red box.png.webp", $files);
        $this->assertSame([640, 320], array_slice(getimagesize("$out/_image/640/photos/red box.png.webp"), 0, 2));

        // Every URL in a srcset gets the base path
        $html = file_get_contents("$out/index.html");
        $this->assertMatchesRegularExpression('#src="/sub/_image/640/[^"]+" srcset="/sub/_image/320/[^ ]+ 320w, /sub/_image/640/#', $html);
    }
}
