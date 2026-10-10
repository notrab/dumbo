<?php

namespace Dumbo\Jumbo\Tests;

use Dumbo\Dumbo;
use Dumbo\Jumbo\Html;
use Dumbo\Jumbo\Site;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use PHPUnit\Framework\TestCase;

class FeaturesTest extends TestCase
{
    private const FIXTURE = __DIR__ . "/fixtures/features";

    private function site(array $config = [], string $environment = Dumbo::ENV_TESTING): Site
    {
        return new Site(self::FIXTURE, ["name" => "Features", ...$config], new Dumbo($environment));
    }

    private function get(Site $site, string $path): string
    {
        return (string) $site->app->request($path)->getBody();
    }

    public function testPropsFromPaths(): void
    {
        $site = $this->site();

        $this->assertSame("<p>php: 3</p>", $this->get($site, "/tags/php"));
        $this->assertSame("<p>plain: none</p>", $this->get($site, "/tags/plain"));
        $this->assertSame("<p>other: none</p>", $this->get($site, "/tags/other"));
        $tags = array_values(array_filter($site->routes(), fn($route) => $route->path === "/tags/{tag}"))[0];
        $this->assertSame(["/tags/php" => ["count" => 3], "/tags/plain" => []], $site->paths($tags));
    }

    public function testPagination(): void
    {
        $site = $this->site();

        $this->assertSame(
            ["items" => ["c", "d"], "current" => 2, "last" => 3, "previous" => "/blog/1", "next" => "/blog/3", "start" => 3, "end" => 4],
            json_decode($this->get($site, "/blog/2"), true)
        );
        $this->assertSame(
            ["items" => ["e"], "current" => 3, "last" => 3, "previous" => "/blog/2", "next" => null, "start" => 5, "end" => 5],
            json_decode($this->get($site, "/blog/3"), true)
        );
    }

    public function testPaginateNeedsAPageSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->site()->paginate([1, 2], 0);
    }

    public function testRedirects(): void
    {
        $site = $this->site(["redirects" => [
            "/old" => "/about",
            "/posts/[slug]" => ["to" => "/blog/[slug]", "status" => 302],
        ]]);

        $response = $site->app->request("/old");
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame("/about", $response->getHeaderLine("Location"));

        $response = $site->app->request("/posts/hello");
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame("/blog/hello", $response->getHeaderLine("Location"));
    }

    public function testI18nRoutes(): void
    {
        $site = $this->site(["i18n" => ["locales" => ["en", "fr"]]]);

        $this->assertStringContainsString('<html lang="en">', $this->get($site, "/about"));
        $this->assertStringContainsString('<html lang="fr">', $this->get($site, "/fr/about"));
        $this->assertStringContainsString('<html lang="fr">', $this->get($site, "/fr"));
        $this->assertSame("<p>php: 3</p>", $this->get($site, "/fr/tags/php"));
        $this->assertSame(["en" => "", "fr" => "/fr"], $site->locales());
        $this->assertSame("/fr/about", $site->localize("/about", "fr"));
        $this->assertSame("/about", $site->localize("/about", "en"));
        $this->assertSame("/fr", $site->localize("/", "fr"));
        $this->assertSame("Bonjour", $site->find("hello", "fr")["title"]);
        $this->assertSame("Hello", $site->find("hello", "de")["title"]);

        $site->app->get("/fr/check", function ($context) use ($site) {
            $context->set("locale", "fr");

            return $context->json($site->alternates($context));
        });

        $this->assertSame(["en" => "/check", "fr" => "/fr/check"], json_decode($this->get($site, "/fr/check"), true));
    }

    public function testI18nPrefixingTheDefaultLocale(): void
    {
        $site = $this->site(["i18n" => ["locales" => ["en", "fr"], "default" => "fr", "prefixDefault" => true]]);

        $response = $site->app->request("/");
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame("/fr", $response->getHeaderLine("Location"));
        $this->assertStringContainsString('<html lang="en">', $this->get($site, "/en/about"));
        $this->assertSame(404, $site->app->request("/about")->getStatusCode());
    }

    public function testErrorPageInProduction(): void
    {
        $response = $this->site([], Dumbo::ENV_PRODUCTION)->app->request("/broken");

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString("<p>Sorry: The page broke</p>", (string) $response->getBody());
    }

    public function testErrorsWhileDevelopingShowTheCode(): void
    {
        $response = $this->site([], Dumbo::ENV_DEVELOPMENT)->app->request("/broken");
        $body = (string) $response->getBody();

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString("<h1>The page broke</h1>", $body);
        $this->assertStringContainsString("pages/broken.php:7", $body);
        $this->assertStringContainsString("throw new RuntimeException(&quot;The page broke&quot;)", $body);
    }

    public function testComponentStylesAreHoistedOnce(): void
    {
        $body = $this->get($this->site(), "/");

        $this->assertSame(1, substr_count($body, "<style>.badge { color: red; }</style>"));
        $this->assertStringContainsString("<style>.badge { color: red; }</style>\n</head>", $body);
        $this->assertStringNotContainsString("hoist", $body);
    }

    public function testHoistLeavesOtherTagsAlone(): void
    {
        $html = '<head></head><script type="module" hoist>a()</script><script>b()</script><script type="module" hoist="">a()</script><style data-hoisted>c</style>';

        $this->assertSame(
            '<head><script type="module">a()</script>' . "\n" . '</head><script>b()</script><style data-hoisted>c</style>',
            Html::hoist($html)
        );
    }

    public function testPrefetchAndViewTransitions(): void
    {
        $body = $this->get($this->site(["prefetch" => "eager", "viewTransitions" => true]), "/");

        $this->assertStringContainsString("<style>@view-transition { navigation: auto; }</style>", $body);
        $this->assertStringContainsString('<script type="speculationrules">{"prefetch":[{"where":{"href_matches":"/*"},"eagerness":"eager"}]}</script>', $body);
        $this->assertStringNotContainsString("speculationrules", $this->get($this->site(), "/"));
    }

    public function testVersionedPublicFiles(): void
    {
        $site = $this->site();
        $url = $site->file("/app.css");

        $this->assertSame("/app.css?v=" . substr(md5_file(self::FIXTURE . "/public/app.css"), 0, 8), $url);
        $this->assertSame("/app.css?x=1&" . substr($url, 9), $site->file("/app.css?x=1"));
        $this->assertSame("public, max-age=31536000, immutable", $site->app->request($url)->getHeaderLine("Cache-Control"));
        $this->assertSame("public, max-age=3600", $site->app->request("/app.css")->getHeaderLine("Cache-Control"));
        $this->assertStringNotContainsString("immutable", $site->app->request("/about?v=1")->getHeaderLine("Cache-Control"));
    }

    public function testCodeIsHighlightedWithClasses(): void
    {
        $html = $this->site()->markdown("```php\necho \"hi\";\n```\n\n```\n<b>plain</b>\n```")["html"];

        $this->assertStringContainsString('<pre><code class="language-php"><span class="hl-keyword">echo</span>', $html);
        $this->assertStringContainsString("&quot;hi&quot;", $html);
        $this->assertStringContainsString("<pre><code>&lt;b&gt;plain&lt;/b&gt;\n</code></pre>", $html);
    }

    public function testCodeIsHighlightedWithAnInlineTheme(): void
    {
        $html = $this->site(["markdown" => ["highlight" => "github-dark"]])->markdown("```php\necho 1;\n```")["html"];

        $this->assertMatchesRegularExpression('#<pre [^>]*style="[^"]*background-color: \#24292e;[^"]*"><code class="language-php"><span style="color: \#f97583;">echo</span>#', $html);
    }

    public function testHighlightingCanBeTurnedOff(): void
    {
        $html = $this->site(["markdown" => ["highlight" => false]])->markdown("```php\necho 1;\n```")["html"];

        $this->assertSame("<pre><code class=\"language-php\">echo 1;\n</code></pre>\n", $html);
    }

    public function testHighlightStyles(): void
    {
        $css = $this->site()->highlightStyles("github-dark");

        $this->assertStringContainsString(".hl-keyword {\n    color: #f97583;\n}", $css);
        $this->assertStringNotContainsString("pre", $css);

        $this->expectException(\InvalidArgumentException::class);
        $this->site()->highlightStyles("../nope");
    }

    public function testMarkdownExtensionsAndConfig(): void
    {
        $site = $this->site(["markdown" => [
            "extensions" => [new FootnoteExtension()],
            "config" => ["heading_permalink" => ["symbol" => "¶"]],
        ]]);
        $html = $site->markdown("## Title\n\nText[^1]\n\n[^1]: A note")["html"];

        $this->assertStringContainsString('class="footnotes"', $html);
        $this->assertStringContainsString("¶</a>", $html);
    }

    public function testListConfigReplacesTheDefaults(): void
    {
        $this->assertSame([500], $this->site(["images" => ["widths" => [500]]])->config["images"]["widths"]);
    }

    public function testRss(): void
    {
        $xml = $this->site(["url" => "https://example.com/sub/"])->rss([
            ["title" => "A & B", "link" => "/blog/a", "date" => 1767225600, "content" => "<p>Hi]]></p>"],
        ]);

        $this->assertStringContainsString("<title>Features</title>", $xml);
        $this->assertStringContainsString("<link>https://example.com/sub/</link>", $xml);
        $this->assertStringContainsString("<title>A &amp; B</title>", $xml);
        $this->assertStringContainsString("<link>https://example.com/sub/blog/a</link>", $xml);
        $this->assertStringContainsString("<guid>https://example.com/sub/blog/a</guid>", $xml);
        $this->assertStringContainsString("<pubDate>Thu, 01 Jan 2026 00:00:00 +0000</pubDate>", $xml);
        $this->assertStringContainsString("<![CDATA[<p>Hi]]]]><![CDATA[></p>]]>", $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function testLiveReload(): void
    {
        $site = $this->site()->watch();
        $version = $this->get($site, "/_jumbo/changes");

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $version);
        $this->assertSame($version, $this->get($site, "/_jumbo/changes"));
        $this->assertStringContainsString('fetch("/_jumbo/changes"', $this->get($site, "/"));
        $this->assertStringContainsString('fetch("/_jumbo/changes"', $this->get($site, "/broken"));
        $this->assertStringNotContainsString("_jumbo", $this->get($site, "/blog/1"));
    }
}
