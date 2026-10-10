<?php

namespace Dumbo\Jumbo;

use Dumbo\Context;
use Dumbo\Dumbo;
use Dumbo\Helpers\StaticFiles;
use Dumbo\Jumbo\Markdown\ComponentExtension;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

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
        "dirs" => [
            "pages" => "pages",
            "views" => "views",
            "components" => "components",
            "content" => "content",
            "public" => "public",
        ],
    ];

    public array $config;

    public readonly Dumbo $app;

    /** @var array<Route> */
    private array $routes;

    private ?string $notFoundPage = null;

    private MarkdownConverter $markdown;

    /** @var array<string, array> */
    private array $documents = [];

    private ?array $lock = null;

    public function __construct(public readonly string $root, array $config = [], ?Dumbo $app = null)
    {
        $this->config = array_replace_recursive(static::DEFAULTS, $config);
        $this->app = $app ?? new Dumbo();
        $this->markdown = $this->createMarkdown();
        $this->routes = $this->discover();
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
     * The absolute path of a configured directory, such as "pages"
     */
    public function path(string $dir): string
    {
        return $this->root . "/" . ($this->config["dirs"][$dir] ?? throw new \InvalidArgumentException("No \"$dir\" in dirs config"));
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
     * A Markdown file from content/, with its slug, or null if there's none
     */
    public function find(string $slug): ?array
    {
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

            if (preg_match('/^404\.(php|md)$/', $relative)) {
                $this->notFoundPage = $file;

                continue;
            }

            [$path, $params] = Route::fromFile($relative);
            $routes[] = new Route($path, $file, $params, self::definition($file));
        }

        usort($routes, fn(Route $a, Route $b) => $a->rank() <=> $b->rank());

        return $routes;
    }

    private function register(): void
    {
        $this->app->use(StaticFiles::serve($this->path("public")));

        // Packages loaded with asset() are served from node_modules when not using the CDN
        if ($this->assetMode() === "local") {
            $this->app->get("/vendor/{path:.+}", StaticFiles::serve("$this->root/node_modules"));
        }

        foreach ($this->routes as $route) {
            $this->app->get(
                $route->path,
                fn(Context $context) => $this->respond($route, $context),
                $route->page->middleware ?? []
            );
        }

        if ($this->notFoundPage) {
            $this->app->notFound(fn(Context $context) => $context->html(
                $this->renderFile($this->notFoundPage, $context, "/404")
            ));
        }
    }

    private function respond(Route $route, Context $context): mixed
    {
        $result = $route->page
            ? ($route->page->render)($context, $this)
            : $this->renderFile($route->file, $context, $context->req->path());

        return is_string($result) ? $context->send($result, $route->contentType()) : $result;
    }

    /**
     * Render a template or Markdown page. Templates get $context and $params,
     * and Markdown is rendered in the view named by its layout front matter.
     */
    private function renderFile(string $file, Context $context, string $path): string
    {
        if (!str_ends_with($file, ".md")) {
            return (new Template($this, $file))->render([
                "context" => $context,
                "params" => $context->req->param(),
            ]);
        }

        $page = $this->markdown(file_get_contents($file));

        if (empty($page["meta"]["layout"])) {
            return $page["html"];
        }

        return $this->view($page["meta"]["layout"], [
            "context" => $context,
            "page" => $page,
            // The home page keeps the site's own title
            "seo" => $this->seo($page["meta"], $path, $path === "/" ? null : $page["title"]),
        ]);
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

    private function createMarkdown(): MarkdownConverter
    {
        $environment = new Environment([
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
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $environment->addExtension(new FrontMatterExtension());
        $environment->addExtension(new ComponentExtension($this->component(...)));

        return new MarkdownConverter($environment);
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
