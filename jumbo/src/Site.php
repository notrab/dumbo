<?php

namespace Dumbo\Jumbo;

use Dumbo\Context;
use Dumbo\Dumbo;
use Dumbo\Helpers\StaticFiles;
use Dumbo\HTTPException;
use Dumbo\Jumbo\Markdown\CodeRenderer;
use Dumbo\Jumbo\Markdown\ComponentExtension;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Tempest\Highlight\Highlighter;
use Tempest\Highlight\Themes\InlineTheme;

/**
 * A site built from files: pages/ becomes routes, views/ holds layouts and
 * templates, components/ holds Markdown components and content/ holds
 * Markdown. The same app serves pages on demand and renders them to static
 * files with `jumbo build`.
 *
 * Extend it to add helpers for your templates, which get the site as $site.
 */
class Site
{
    public const DEFAULTS = [
        "name" => "Site",
        // Where the site is hosted, for canonical URLs and the build's base path
        "url" => null,
        "locale" => "en",
        "seo" => [
            "title" => null,
            "titleTemplate" => "%s",
            "description" => "",
            "image" => null,
            "twitter" => null,
        ],
        // "cdn" or "local" for asset(). Defaults to the CDN in development
        // and local in production, and static builds are always local.
        "assets" => null,
        "cdn" => "https://cdn.hopjs.net/npm",
        "markdown" => [
            // CommonMark extensions and config, added to the defaults
            "extensions" => [],
            "config" => [],
            // Highlight fenced code on the server: true for hl-* classes to
            // style with highlightStyles(), a theme name like "github-dark"
            // for inline colours, or false to leave code plain
            "highlight" => true,
            // Make local images responsive, as img() does
            "images" => true,
        ],
        "images" => [
            // The widths resized images can be made at
            "widths" => [320, 640, 960, 1280, 1920, 2560],
            // "webp", "avif", "png", "jpg", or null to keep each image's own
            "format" => "webp",
            "quality" => 80,
            // Where resized images are kept. Defaults to the temp directory.
            "cache" => null,
        ],
        // "/old" => "/new", or "/blog/[slug]" => ["to" => "/posts/[slug]", "status" => 302]
        "redirects" => [],
        "i18n" => [
            // Locales other than the default are served under /{locale}
            "locales" => [],
            // Defaults to the first locale
            "default" => null,
            // Serve the default locale under /{locale} too
            "prefixDefault" => false,
        ],
        "build" => [
            // "directory" builds /about as about/index.html, "file" as about.html
            "format" => "directory",
        ],
        // Write sitemap.xml with every built page when url is set, or a
        // callable that's given each URL and returns whether to list it
        "sitemap" => true,
        // Let browsers fetch pages before they're clicked: true, or an
        // eagerness of "conservative", "moderate" or "eager"
        "prefetch" => false,
        // Animate between pages with cross-document view transitions
        "viewTransitions" => false,
        "actions" => [
            // Refuse forms posted from other sites, as browsers report them
            "checkOrigin" => true,
            // Other origins allowed to post, like "https://admin.example.com"
            "origins" => [],
        ],
        "dirs" => [
            "pages" => "pages",
            "views" => "views",
            "components" => "components",
            "content" => "content",
            "public" => "public",
        ],
    ];

    /** Config values that are lists, so they replace the defaults rather than merge */
    private const LISTS = [["markdown", "extensions"], ["images", "widths"], ["i18n", "locales"], ["actions", "origins"]];

    private const LIVE_RELOAD = <<<'HTML'
<script>(() => { let last; const check = async () => { try { const version = await (await fetch("/_jumbo/changes", { cache: "no-store" })).text(); if (last && version !== last) return location.reload(); last = version; } catch {} setTimeout(check, 1000); }; check(); })();</script>
HTML;

    public array $config;

    public readonly Dumbo $app;

    /** @var array<Route> */
    private array $routes;

    private ?string $notFoundPage = null;

    private ?string $errorPage = null;

    private MarkdownConverter $markdown;

    private Images $images;

    /** @var array<string, array> */
    private array $documents = [];

    /** @var array<string, array<string, array>> Props by URL, for each page file */
    private array $paths = [];

    /** @var array<string, array{to: string, status: int, params: array<string, bool>}> */
    private array $redirects = [];

    /** @var array<string, array<callable>> */
    private array $listeners = [];

    private ?array $lock = null;

    private bool $watching = false;

    private ?\Throwable $lastError = null;

    public function __construct(public readonly string $root, array $config = [], ?Dumbo $app = null)
    {
        $this->config = array_replace_recursive(static::DEFAULTS, $config);

        foreach (self::LISTS as [$group, $key]) {
            if (isset($config[$group][$key])) {
                $this->config[$group][$key] = $config[$group][$key];
            }
        }

        $this->app = $app ?? new Dumbo();
        $this->images = new Images($this);
        $this->markdown = $this->createMarkdown();
        $this->routes = $this->discover();
        $this->redirects = $this->parseRedirects();
        $this->register();
    }

    /**
     * The site returned by app.php in a project
     */
    public static function load(string $root): static
    {
        $site = (static fn() => require "$root/app.php")();

        if (!$site instanceof self) {
            throw new \RuntimeException("$root/app.php should return a " . self::class . ", got " . get_debug_type($site));
        }

        return $site;
    }

    /**
     * Serve the request from PHP's globals
     */
    public function run(): void
    {
        $this->app->run();
    }

    public function production(): bool
    {
        return $this->app->getEnvironment() === Dumbo::ENV_PRODUCTION;
    }

    /**
     * @return array<Route> The pages, most specific first
     */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * @return array<string, array{to: string, status: int, params: array<string, bool>}> The redirects, by route path
     */
    public function redirects(): array
    {
        return $this->redirects;
    }

    /**
     * Listen for an event: "build:start" gets the output directory, and
     * "build:done" gets it and the files written. Run a CSS build first,
     * say, or upload the site once it's built.
     */
    public function on(string $event, callable $listener): static
    {
        $this->listeners[$event][] = $listener;

        return $this;
    }

    public function emit(string $event, mixed ...$args): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener(...$args);
        }
    }

    /**
     * The absolute path of a configured directory, such as "pages"
     */
    public function path(string $dir): string
    {
        return $this->root . "/" . ($this->config["dirs"][$dir] ?? throw new \InvalidArgumentException("No \"$dir\" in dirs config"));
    }

    /**
     * The absolute URL of a path on the site, or null without a url config
     */
    public function url(string $path = "/"): ?string
    {
        return $this->config["url"] ? rtrim($this->config["url"], "/") . "/" . ltrim($path, "/") : null;
    }

    /**
     * Render a template from views/ with the given variables
     */
    public function view(string $name, array $data = []): string
    {
        return (new Template($this, $this->viewFile($name)))->render($data);
    }

    public function viewFile(string $name): string
    {
        $file = $this->path("views") . "/$name.php";

        if (!preg_match('#^[\w-]+(/[\w-]+)*$#', $name) || !is_file($file)) {
            throw new \RuntimeException("No view \"$name\" in {$this->config["dirs"]["views"]}/");
        }

        return $file;
    }

    /**
     * Render a component from components/{name}.php. Markdown uses these for
     * ::: name blocks, and templates can call it too.
     */
    public function component(string $name, array $attributes = [], string $slot = ""): string
    {
        $file = $this->path("components") . "/$name.php";

        if (!preg_match('/^[a-z][\w-]*$/i', $name) || !is_file($file)) {
            trigger_error("Unknown component \"$name\"", E_USER_WARNING);

            return "<div class=\"component-missing\">Unknown component <code>" . e($name) . "</code></div>";
        }

        return (new Template($this, $file))->render(["attributes" => $attributes, "slot" => $slot]);
    }

    /**
     * Render Markdown to its front matter as meta, HTML, title and a table of
     * contents from the h2 and h3 headings
     *
     * @return array{meta: array, title: ?string, html: string, toc: array}
     */
    public function markdown(string $source): array
    {
        $result = $this->markdown->convert($source);
        $meta = $result instanceof RenderedContentWithFrontMatter ? (array) $result->getFrontMatter() : [];
        $html = (string) $result;

        if ($this->config["markdown"]["images"]) {
            $html = $this->responsiveImages($html);
        }

        preg_match_all('/<h([23]) id="([^"]+)">(.*?)<\/h\1>/s', $html, $matches, PREG_SET_ORDER);
        preg_match('/<h1>(.*?)<\/h1>/s', $html, $h1);

        return [
            "meta" => $meta,
            "title" => $meta["title"] ?? (isset($h1[1]) ? self::text($h1[1]) : null),
            "html" => $html,
            "toc" => array_map(fn($m) => [
                "level" => (int) $m[1],
                "id" => $m[2],
                "text" => self::text($m[3]),
            ], $matches),
        ];
    }

    /**
     * A Markdown file from content/, with its slug, or null if there's none.
     * With a locale, content/{locale}/{slug}.md is used when there is one.
     */
    public function find(string $slug, ?string $locale = null): ?array
    {
        if ($locale !== null && ($translated = $this->find("$locale/$slug")) !== null) {
            return $translated;
        }

        if (array_key_exists($slug, $this->documents)) {
            return $this->documents[$slug];
        }

        $file = $this->path("content") . "/$slug.md";

        if (!preg_match('#^[a-z0-9][a-z0-9-]*(/[a-z0-9][a-z0-9-]*)*$#', $slug) || !is_file($file)) {
            return null;
        }

        $document = $this->markdown(file_get_contents($file));

        return $this->documents[$slug] = [
            "slug" => $slug,
            ...$document,
            "title" => $document["title"] ?? basename($slug),
        ];
    }

    /**
     * The slug of every Markdown file in content/, including subdirectories
     *
     * @return array<string>
     */
    public function slugs(): array
    {
        return array_map(
            fn(string $file) => substr($file, 0, -3),
            self::files($this->path("content"), "md")
        );
    }

    /**
     * The meta tags for a page: the seo config, overridden by front matter
     */
    public function seo(array $meta, string $path, ?string $title = null): array
    {
        $seo = $this->config["seo"];
        $url = $this->config["url"] ? rtrim($this->config["url"], "/") : null;
        $image = $meta["image"] ?? $seo["image"];

        return [
            "title" => match (true) {
                isset($meta["seo_title"]) => $meta["seo_title"],
                $title !== null => sprintf($seo["titleTemplate"], $title),
                default => $seo["title"] ?? $this->config["name"],
            },
            "description" => $meta["description"] ?? $seo["description"],
            "canonical" => $meta["canonical"] ?? ($url ? $url . ($path === "/" ? "/" : $path) : null),
            // Social cards need absolute image URLs
            "image" => $image && str_starts_with($image, "/") ? ($url ? $url . $image : null) : $image,
            "noindex" => (bool) ($meta["noindex"] ?? false),
            "type" => $path === "/" ? "website" : "article",
            "twitter" => $seo["twitter"],
        ];
    }

    /**
     * The URL of a file from an npm package: from the CDN while developing,
     * or served from node_modules in production. Versions come from
     * package-lock.json, so both load exactly the same file.
     */
    public function asset(string $package, string $file): string
    {
        if ($this->assetMode() === "cdn") {
            return rtrim($this->config["cdn"], "/") . "/$package@{$this->version($package)}/$file";
        }

        return "/vendor/$package/$file";
    }

    public function assetMode(): string
    {
        return $this->config["assets"] ?? ($this->production() ? "local" : "cdn");
    }

    /**
     * The URL of a file in public/ with a hash of its contents, like
     * /app.css?v=1a2b3c4d. It changes whenever the file does, so browsers
     * and CDNs can cache it for good.
     */
    public function file(string $path): string
    {
        $file = $this->path("public") . "/" . ltrim(explode("?", $path)[0], "/");

        if (!is_file($file)) {
            trigger_error("No $path in {$this->config["dirs"]["public"]}/", E_USER_WARNING);

            return $path;
        }

        return $path . (str_contains($path, "?") ? "&" : "?") . "v=" . substr(md5_file($file), 0, 8);
    }

    /**
     * What a page's action did with the form just posted, or null if none
     * ran. Pass a name to only get that action's result, for pages with
     * more than one form.
     */
    public function action(Context $context, ?string $name = null): ?Action
    {
        $action = $context->get("action");

        return $action instanceof Action && ($name === null || $action->name === $name) ? $action : null;
    }

    /**
     * The URL of an image in public/, resized to a width and converted to
     * the images format config
     */
    public function image(string $src, ?int $width = null, ?string $format = null): string
    {
        return $this->images->url($src, $width, $format);
    }

    /**
     * A responsive <img> for an image in public/: resized for every screen
     * up to twice the width, lazy loaded, and with its size set so the page
     * doesn't shift as it loads
     *
     * @param array<string, string|bool|null> $attributes Extra attributes, or overrides like "loading" => "eager"
     */
    public function img(string $src, string $alt = "", ?int $width = null, ?string $sizes = null, ?string $format = null, array $attributes = []): string
    {
        return $this->images->tag($src, $alt, $width, $sizes, $format, $attributes);
    }

    /**
     * The CSS for a highlight theme, like "github-dark", to style code
     * highlighted with classes
     */
    public function highlightStyles(string $theme): string
    {
        preg_match_all('/\.hl-[\w-]+(?:\s*,\s*\.hl-[\w-]+)*\s*\{[^}]*}/', file_get_contents(self::themeFile($theme)), $rules);

        return implode("\n", $rules[0]);
    }

    /**
     * An RSS feed, with the channel from the site's config. Links can be
     * paths, and become absolute when the url config is set.
     *
     * @param iterable<array{title: string, link: string, description?: string, content?: string, date?: mixed}> $items
     * @param array $channel Overrides for the title, link, description and language
     */
    public function rss(iterable $items, array $channel = []): string
    {
        $absolute = fn(string $link) => str_starts_with($link, "/") && !str_starts_with($link, "//") ? ($this->url($link) ?? $link) : $link;
        $items = (function () use ($items, $absolute) {
            foreach ($items as $item) {
                yield ["guid" => $absolute($item["link"]), ...$item, "link" => $absolute($item["link"])];
            }
        })();

        return Feed::rss([
            "title" => $this->config["name"],
            "link" => $this->url() ?? "/",
            "description" => $this->config["seo"]["description"],
            "language" => $this->config["locale"],
            ...$channel,
        ], $items);
    }

    /**
     * Split items into pages for a page's paths(), with a "page" param
     * counting from 1 and a Pagination as the "page" prop
     *
     * @param array<string, string> $params Other params for the route, like a tag
     * @return array<array{params: array, props: array{page: Pagination}}>
     */
    public function paginate(iterable $items, int $size, string $param = "page", array $params = []): array
    {
        if ($size < 1) {
            throw new \InvalidArgumentException("Pages need at least one item, got $size");
        }

        $items = is_array($items) ? array_values($items) : iterator_to_array($items, false);
        $last = max(1, (int) ceil(count($items) / $size));
        $pages = [];

        for ($current = 1; $current <= $last; $current++) {
            $pages[] = [
                "params" => [...$params, $param => (string) $current],
                "props" => [
                    "page" => new Pagination(array_slice($items, ($current - 1) * $size, $size), $current, $last, $size, count($items), $param, $params),
                ],
            ];
        }

        return $pages;
    }

    /**
     * The URLs a page with params is built at, from its paths(), each with
     * its props
     *
     * @return array<string, array> Props by URL
     */
    public function paths(Route $route): array
    {
        if (!$route->page?->paths) {
            return [];
        }

        if (isset($this->paths[$route->file])) {
            return $this->paths[$route->file];
        }

        $paths = [];

        foreach (($route->page->paths)($this) as $entry) {
            // Either the params, or ["params" => [...], "props" => [...]]
            $nested = is_array($entry["params"] ?? null) && array_diff(array_keys($entry), ["params", "props"]) === [];
            $props = $nested ? ($entry["props"] ?? []) : [];

            foreach ($props as $prop) {
                if ($prop instanceof Pagination) {
                    $prop->bind($route);
                }
            }

            $paths[$route->url($nested ? $entry["params"] : $entry)] = $props;
        }

        return $this->paths[$route->file] = $paths;
    }

    /**
     * @return array<string, string> The URL prefix for each locale, empty without i18n
     */
    public function locales(): array
    {
        $prefixes = [];

        foreach ($this->config["i18n"]["locales"] as $locale) {
            $prefixes[$locale] = $locale === $this->defaultLocale() && !$this->config["i18n"]["prefixDefault"] ? "" : "/$locale";
        }

        return $prefixes;
    }

    public function defaultLocale(): string
    {
        return $this->config["i18n"]["default"] ?? $this->config["i18n"]["locales"][0] ?? $this->config["locale"];
    }

    /**
     * The locale of the page being rendered
     */
    public function locale(?Context $context = null): string
    {
        return $context?->get("locale") ?: $this->defaultLocale();
    }

    /**
     * A path in a locale, like /fr/about for /about
     */
    public function localize(string $path, ?string $locale = null): string
    {
        $locales = $this->locales();
        $locale ??= $this->defaultLocale();

        if ($locales === []) {
            return $path;
        }

        $prefix = $locales[$locale] ?? throw new \InvalidArgumentException("No \"$locale\" in i18n locales");

        return $prefix === "" ? $path : $prefix . ($path === "/" ? "" : $path);
    }

    /**
     * The page being rendered in every locale, for language switchers and
     * hreflang links
     *
     * @return array<string, string> Paths by locale
     */
    public function alternates(Context $context): array
    {
        $path = $context->req->path();
        $prefix = $this->locales()[$this->locale($context)] ?? "";

        if ($prefix !== "" && ($path === $prefix || str_starts_with($path, "$prefix/"))) {
            $path = substr($path, strlen($prefix)) ?: "/";
        }

        $alternates = [];

        foreach (array_keys($this->locales()) as $locale) {
            $alternates[$locale] = $this->localize($path, $locale);
        }

        return $alternates;
    }

    /**
     * Reload open pages when a file in the project changes. `jumbo dev`
     * turns this on.
     */
    public function watch(): static
    {
        $this->watching = true;

        $this->app->get("/_jumbo/changes", fn(Context $context) => $context->send($this->signature(), "text/plain", 200, [
            "Cache-Control" => "no-store",
        ]));

        $this->app->use(function (Context $context, callable $next): ResponseInterface {
            return self::rewriteHtml($next($context), fn(string $html) => Html::inject($html, self::LIVE_RELOAD, "</body>"));
        });

        return $this;
    }

    /**
     * Finish a rendered page: hoist component styles and scripts into the
     * <head>, and add prefetching and view transitions when they're on
     */
    public function finish(string $html): string
    {
        $html = Html::hoist($html);
        $head = "";

        if ($this->config["viewTransitions"]) {
            $head .= "<style>@view-transition { navigation: auto; }</style>";
        }

        if ($prefetch = $this->config["prefetch"]) {
            $rules = ["prefetch" => [["where" => ["href_matches" => "/*"], "eagerness" => is_string($prefetch) ? $prefetch : "moderate"]]];
            $head .= "<script type=\"speculationrules\">" . json_encode($rules, JSON_UNESCAPED_SLASHES) . "</script>";
        }

        return $head === "" ? $html : Html::inject($html, $head);
    }

    /**
     * @internal The last uncaught error, so builds can say what went wrong
     */
    public function lastError(): ?\Throwable
    {
        return $this->lastError;
    }

    /**
     * Find every page in pages/. Files and folders starting with _ are
     * skipped, so helpers can live next to the pages that use them.
     *
     * @return array<Route>
     */
    private function discover(): array
    {
        $dir = $this->path("pages");
        $routes = [];

        foreach (self::files($dir, "php", "md") as $relative) {
            if (preg_match('#(^|/)_#', $relative)) {
                continue;
            }

            $file = "$dir/$relative";

            if (preg_match('/^(404|500)\.(php|md)$/', $relative, $special)) {
                $special[1] === "404" ? $this->notFoundPage = $file : $this->errorPage = $file;

                continue;
            }

            [$path, $params] = Route::fromFile($relative);
            $routes[] = new Route($path, $file, $params, self::definition($file));
        }

        usort($routes, fn(Route $a, Route $b) => $a->rank() <=> $b->rank());

        return $routes;
    }

    /**
     * @return array<string, array{to: string, status: int, params: array<string, bool>}>
     */
    private function parseRedirects(): array
    {
        $redirects = [];

        foreach ($this->config["redirects"] as $from => $to) {
            $to = is_array($to) ? $to : ["to" => $to];
            [$path, $params] = Route::fromFile(trim($from, "/"));

            $redirects[$path] = ["to" => $to["to"], "status" => (int) ($to["status"] ?? 301), "params" => $params];
        }

        // With every locale prefixed, the home page picks the default one
        if ($this->config["i18n"]["prefixDefault"] && $this->locales() !== []) {
            $redirects["/"] ??= ["to" => $this->localize("/"), "status" => 302, "params" => []];
        }

        return $redirects;
    }

    private function register(): void
    {
        $this->app->use(fn(Context $context, callable $next) => self::rewriteHtml($next($context), $this->finish(...)));
        $this->app->use($this->servePublic());

        // Packages loaded with asset() are served from node_modules when not using the CDN
        if ($this->assetMode() === "local") {
            $this->app->get("/vendor/{path:.+}", StaticFiles::serve("$this->root/node_modules"));
        }

        $this->app->get("/_image/{width:\d+}/{file:.+}", $this->images->serve(...));

        foreach ($this->redirects as $path => $redirect) {
            $this->app->get($path, fn(Context $context) => $context->redirect(
                preg_replace_callback('/\[(?:\.\.\.)?(\w+)]/', fn($m) => $context->req->param($m[1]) ?? "", $redirect["to"]),
                $redirect["status"]
            ));
        }

        foreach ($this->locales() ?: ["" => ""] as $locale => $prefix) {
            foreach ($this->routes as $route) {
                $path = $prefix === "" ? $route->path : $prefix . ($route->path === "/" ? "" : $route->path);

                $this->app->get($path, fn(Context $context) => $this->respond($route, $context, (string) $locale), $route->page->middleware ?? []);

                if ($route->page?->actions) {
                    $this->app->post($path, fn(Context $context) => $this->act($route, $context, (string) $locale), $route->page->middleware);
                }
            }
        }

        if ($this->notFoundPage) {
            $this->app->notFound(fn(Context $context) => $context->html(
                $this->renderFile($this->notFoundPage, $context, "/404")
            ));
        }

        $this->app->onError($this->error(...));
    }

    /**
     * Files in public/, cached for good when they're asked for with the
     * version from file()
     */
    private function servePublic(): callable
    {
        $serve = StaticFiles::serve($this->path("public"));

        return function (Context $context, callable $next) use ($serve): ResponseInterface {
            $missing = false;
            $response = $serve($context, function (Context $context) use ($next, &$missing) {
                $missing = true;

                return $next($context);
            });

            if ($missing || $response->getStatusCode() !== 200 || $context->req->query("v") === null) {
                return $response;
            }

            return $response->withHeader("Cache-Control", "public, max-age=31536000, immutable");
        };
    }

    private function respond(Route $route, Context $context, string $locale): mixed
    {
        if ($locale !== "") {
            $context->set("locale", $locale);
        }

        $result = $route->page
            ? ($route->page->render)($context, $this, $this->props($route, $context))
            : $this->renderFile($route->file, $context, $context->req->path());

        return is_string($result) ? $context->send($result, $route->contentType()) : $result;
    }

    /**
     * Run the action a form asked for, then render the page with what it did,
     * unless it returned a response of its own
     */
    private function act(Route $route, Context $context, string $locale): mixed
    {
        if ($this->config["actions"]["checkOrigin"] && !$this->sameOrigin($context)) {
            return $context->send("Forms can't be posted here from other sites", "text/plain", 403);
        }

        $input = $context->req->body();
        $name = $context->req->query("_action") ?? $input["_action"] ?? "default";
        $handler = is_string($name) ? $route->page->actions[$name] ?? null : null;
        unset($input["_action"]);

        if ($handler === null) {
            return $context->send("This page has no \"" . (is_string($name) ? $name : "") . "\" action", "text/plain", 400);
        }

        $result = $handler($input, $context, $this);

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        $action = $result instanceof Failure
            ? new Action($name, $input, $result->data, $result->errors, $result->status)
            : new Action($name, $input, $result);

        // Forms sent with fetch() get the result itself
        if (self::wantsJson($context)) {
            return $context->json(["ok" => $action->ok(), "data" => $action->data, "errors" => (object) $action->errors], $action->status);
        }

        $context->set("action", $action);
        $context->status($action->status);

        return $this->respond($route, $context, $locale);
    }

    /**
     * Whether a form was posted from this site. Browsers say where a request
     * came from with Sec-Fetch-Site, or failing that Origin.
     */
    private function sameOrigin(Context $context): bool
    {
        $origin = $context->req->header("Origin");

        if ($origin !== null && in_array(rtrim($origin, "/"), array_map(fn($o) => rtrim($o, "/"), $this->config["actions"]["origins"]), true)) {
            return true;
        }

        $site = $context->req->header("Sec-Fetch-Site");

        if ($site !== null) {
            return in_array(strtolower($site), ["same-origin", "none"], true);
        }

        if ($origin === null) {
            return true;
        }

        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);

        return $host !== null && strcasecmp($host . ($port ? ":$port" : ""), $context->req->raw()->getUri()->getAuthority()) === 0;
    }

    private static function wantsJson(Context $context): bool
    {
        $accept = $context->req->header("Accept") ?? "";

        return str_contains($accept, "application/json") && !str_contains($accept, "text/html");
    }

    /**
     * The props from paths() for the page being rendered. They're only
     * worked out for render functions that take them.
     */
    private function props(Route $route, Context $context): array
    {
        if (!$route->page->paths || (new \ReflectionFunction($route->page->render))->getNumberOfParameters() < 3) {
            return [];
        }

        try {
            $url = $route->url(array_map(rawurldecode(...), $context->req->param()));
        } catch (\InvalidArgumentException) {
            return [];
        }

        return $this->paths($route)[$url] ?? [];
    }

    /**
     * Render a template or Markdown page. Templates get $context and $params,
     * and Markdown is rendered in the view named by its layout front matter.
     */
    private function renderFile(string $file, Context $context, string $path, array $data = []): string
    {
        if (!str_ends_with($file, ".md")) {
            return (new Template($this, $file))->render([
                ...$data,
                "context" => $context,
                "params" => $context->req->param(),
            ]);
        }

        $page = $this->markdown(file_get_contents($file));

        if (empty($page["meta"]["layout"])) {
            return $page["html"];
        }

        return $this->view($page["meta"]["layout"], [
            ...$data,
            "context" => $context,
            "page" => $page,
            // The home page keeps the site's own title
            "seo" => $this->seo($page["meta"], $path, $path === "/" ? null : $page["title"]),
        ]);
    }

    /**
     * Uncaught errors show the error while developing, and pages/500.php in
     * production
     */
    private function error(\Throwable $error, Context $context): ResponseInterface
    {
        if ($error instanceof HTTPException) {
            return $error->getCustomResponse() ?? $context->json($error->toArray(), $error->getStatusCode());
        }

        $this->lastError = $error;

        if (!$this->production()) {
            $html = ErrorPage::render($error, $this->root);

            return $context->html($this->watching ? Html::inject($html, self::LIVE_RELOAD, "</body>") : $html, 500);
        }

        // Builds and tests say what failed themselves, so only servers log it
        if (PHP_SAPI !== "cli") {
            error_log((string) $error);
        }

        if ($this->errorPage) {
            try {
                return $context->html($this->finish($this->renderFile($this->errorPage, $context, "/500", ["error" => $error])), 500);
            } catch (\Throwable) {
                // An error page that fails falls back to plain text
            }
        }

        return $context->send("Internal Server Error", "text/plain", 500);
    }

    /**
     * Change the HTML of a response, leaving anything else alone
     *
     * @param callable(string): string $change
     */
    private static function rewriteHtml(ResponseInterface $response, callable $change): ResponseInterface
    {
        if (!str_starts_with($response->getHeaderLine("Content-Type"), "text/html") || !$response->getBody()->isSeekable()) {
            return $response;
        }

        return $response->withBody(Stream::create($change((string) $response->getBody())));
    }

    /**
     * Swap Markdown's local images for responsive ones
     */
    private function responsiveImages(string $html): string
    {
        return preg_replace_callback('#<img src="(/[^"]*)" alt="([^"]*)"(?: title="([^"]*)")? />#', function (array $m): string {
            $decode = fn(string $value) => html_entity_decode($value, ENT_QUOTES | ENT_HTML5, "UTF-8");
            $src = $decode($m[1]);

            if (!$this->images->resizable($src)) {
                return $m[0];
            }

            return $this->images->tag($src, $decode($m[2]), attributes: isset($m[3]) ? ["title" => $decode($m[3])] : []);
        }, $html);
    }

    /**
     * What a page file returns, if it starts with a return statement
     */
    private static function definition(string $file): ?Page
    {
        if (!str_ends_with($file, ".php") || !self::startsWithReturn(file_get_contents($file))) {
            return null;
        }

        $page = (static fn() => require $file)();

        return $page instanceof Page ? $page : throw new \RuntimeException("$file should return a " . Page::class . ", got " . get_debug_type($page));
    }

    /**
     * Whether the first statement in PHP code, after any namespace, use and
     * declare statements, is a return. That makes it a page definition
     * rather than a template.
     */
    private static function startsWithReturn(string $code): bool
    {
        $skip = [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
        $tokens = array_values(array_filter(
            \PhpToken::tokenize($code),
            fn(\PhpToken $token) => !$token->is($skip)
        ));

        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i]->is([T_USE, T_NAMESPACE, T_DECLARE])) {
                while ($i < count($tokens) && $tokens[$i]->text !== ";") {
                    $i++;
                }

                continue;
            }

            return $tokens[$i]->is(T_RETURN);
        }

        return false;
    }

    /**
     * @return array<string> Paths relative to $dir of files with the given extensions
     */
    private static function files(string $dir, string ...$extensions): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (in_array($file->getExtension(), $extensions, true)) {
                $files[] = str_replace("\\", "/", substr($file->getPathname(), strlen($dir) + 1));
            }
        }

        sort($files);

        return $files;
    }

    /**
     * The Markdown converter. Override it to change the defaults, or add
     * extensions with the markdown config.
     */
    protected function createMarkdown(): MarkdownConverter
    {
        $environment = new Environment(array_replace_recursive([
            "heading_permalink" => [
                "html_class" => "anchor",
                "id_prefix" => "",
                "fragment_prefix" => "",
                "apply_id_to_heading" => true,
                "insert" => "after",
                "symbol" => "#",
                "min_heading_level" => 2,
                "max_heading_level" => 3,
            ],
        ], $this->config["markdown"]["config"]));
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $environment->addExtension(new FrontMatterExtension());
        $environment->addExtension(new ComponentExtension($this->component(...)));

        foreach ($this->config["markdown"]["extensions"] as $extension) {
            $environment->addExtension($extension);
        }

        if ($highlighter = $this->highlighter()) {
            $environment->addRenderer(FencedCode::class, new CodeRenderer($highlighter), 10);
        }

        return new MarkdownConverter($environment);
    }

    private function highlighter(): ?Highlighter
    {
        $highlight = $this->config["markdown"]["highlight"];

        return match (true) {
            $highlight instanceof Highlighter => $highlight,
            $highlight === true => new Highlighter(),
            is_string($highlight) => new Highlighter(new InlineTheme(self::themeFile($highlight))),
            default => null,
        };
    }

    /**
     * A highlight theme's CSS file, from its name or path
     */
    private static function themeFile(string $theme): string
    {
        if (str_ends_with($theme, ".css") && is_file($theme)) {
            return $theme;
        }

        $file = dirname((new \ReflectionClass(Highlighter::class))->getFileName()) . "/Themes/Css/$theme.css";

        return preg_match('/^[\w-]+$/', $theme) && is_file($file) ? $file : throw new \InvalidArgumentException("No highlight theme \"$theme\"");
    }

    /**
     * A hash of every file's modification time, skipping dependencies and
     * builds, for live reloading
     */
    private function signature(): string
    {
        $skip = ["vendor", "node_modules", "dist"];
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            fn(\SplFileInfo $file) => !$file->isDir() || !(str_starts_with($file->getFilename(), ".") || in_array($file->getFilename(), $skip, true))
        ));
        $hash = hash_init("md5");

        foreach ($files as $file) {
            hash_update($hash, $file->getPathname() . ":" . $file->getMTime() . "\n");
        }

        return hash_final($hash);
    }

    private function version(string $package): string
    {
        $this->lock ??= json_decode(file_get_contents("$this->root/package-lock.json"), true);

        return $this->lock["packages"]["node_modules/$package"]["version"]
            ?? throw new \RuntimeException("$package isn't installed. Add it with npm install -D $package");
    }

    private static function text(string $html): string
    {
        $html = preg_replace('/<a[^>]*class="anchor".*?<\/a>/s', "", $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }
}
