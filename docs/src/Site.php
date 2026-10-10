<?php

namespace Docs;

use Docs\Markdown\ComponentExtension;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Everything the docs know about themselves: config, pages, Markdown, SEO and
 * asset URLs. The routes in app.php and the templates in theme/ use it.
 */
class Site
{
    public const DEFAULTS = [
        "name" => "Docs",
        "logo" => "📘",
        "badge" => null,
        "url" => null,
        "locale" => "en",
        "seo" => [
            "title" => null,
            "titleTemplate" => "%s",
            "description" => "",
            "image" => null,
            "twitter" => null,
        ],
        "theme" => [
            "primary" => "#8a2be2",
            "accent" => "#fcd96a",
        ],
        "links" => [],
        "social" => [],
        "sidebar" => [],
        "editUrl" => null,
        "footer" => [
            "title" => null,
            "text" => null,
        ],
        "confetti" => "✨",
        "assets" => null,
    ];

    /** Where packages loaded with asset() come from in CDN mode */
    public const CDN = "https://cdn.hopjs.net/npm";

    public array $config;

    private MarkdownConverter $markdown;

    /** @var array<string, array> */
    private array $pages = [];

    public function __construct(public string $root, array $config, public bool $production = false)
    {
        $this->config = array_replace_recursive(self::DEFAULTS, $config);

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

        $this->markdown = new MarkdownConverter($environment);
    }

    /**
     * Render a template from theme/ with the given variables
     */
    public function view(string $name, array $data = []): string
    {
        return $this->template("$this->root/theme/$name.php", $data);
    }

    /**
     * Render a template inside the layout
     */
    public function page(string $name, array $data = []): string
    {
        return $this->view("layout", [...$data, "body" => $this->view($name, $data)]);
    }

    /**
     * Render a Markdown component from components/{name}.php
     */
    public function component(string $name, array $attributes = [], string $slot = ""): string
    {
        $file = "$this->root/components/$name.php";

        if (!preg_match('/^[a-z][\w-]*$/i', $name) || !is_file($file)) {
            trigger_error("Unknown component \"$name\"", E_USER_WARNING);

            return "<div class=\"component-missing\">Unknown component <code>" . e($name) . "</code></div>";
        }

        return $this->template($file, ["attributes" => $attributes, "slot" => $slot]);
    }

    /**
     * A page from content/{slug}.md with its front matter, HTML, title and
     * table of contents, or null if there's no such page
     */
    public function find(string $slug): ?array
    {
        if (isset($this->pages[$slug])) {
            return $this->pages[$slug];
        }

        $file = "$this->root/content/$slug.md";

        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) || !is_file($file)) {
            return null;
        }

        $result = $this->markdown->convert(file_get_contents($file));
        $meta = $result instanceof RenderedContentWithFrontMatter ? (array) $result->getFrontMatter() : [];
        $html = (string) $result;

        // Build "On this page" from the h2 and h3 headings
        preg_match_all('/<h([23]) id="([^"]+)">(.*?)<\/h\1>/s', $html, $matches, PREG_SET_ORDER);
        $toc = array_map(fn($m) => [
            "level" => (int) $m[1],
            "id" => $m[2],
            "text" => self::text($m[3]),
        ], $matches);

        preg_match('/<h1>(.*?)<\/h1>/s', $html, $h1);

        return $this->pages[$slug] = [
            "slug" => $slug,
            "meta" => $meta,
            "title" => $meta["title"] ?? (isset($h1[1]) ? self::text($h1[1]) : $slug),
            "html" => $html,
            "toc" => $toc,
        ];
    }

    /**
     * Every page in content/, by slug
     *
     * @return array<string>
     */
    public function slugs(): array
    {
        return array_map(fn($file) => basename($file, ".md"), glob("$this->root/content/*.md"));
    }

    /**
     * The sidebar groups from config, as [group => [slug => title]]
     */
    public function sidebar(): array
    {
        return array_map(
            fn($slugs) => array_combine($slugs, array_map(fn($slug) => $this->find($slug)["title"] ?? $slug, $slugs)),
            $this->config["sidebar"]
        );
    }

    /**
     * The pages before and after a page in the sidebar
     */
    public function siblings(string $slug): array
    {
        $pages = array_merge(...array_values($this->sidebar()));
        $slugs = array_keys($pages);
        $index = array_search($slug, $slugs, true);
        $link = fn($i) => $index !== false && isset($slugs[$i]) ? ["href" => "/docs/$slugs[$i]", "title" => $pages[$slugs[$i]]] : null;

        return ["prev" => $link($index - 1), "next" => $link($index + 1)];
    }

    /**
     * The meta tags for a page: config defaults, overridden by front matter
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
     * The URL of a file from an npm package: from the hop.js CDN while
     * developing, or served locally in production. Versions come from
     * package-lock.json, so both load exactly the same file.
     */
    public function asset(string $package, string $file): string
    {
        if ($this->assetMode() === "cdn") {
            return self::CDN . "/$package@{$this->version($package)}/$file";
        }

        return "/vendor/$package/$file";
    }

    public function assetMode(): string
    {
        return $this->config["assets"] ?? ($this->production ? "local" : "cdn");
    }

    private function version(string $package): string
    {
        static $lock = null;
        $lock ??= json_decode(file_get_contents("$this->root/package-lock.json"), true);

        return $lock["packages"]["node_modules/$package"]["version"]
            ?? throw new \RuntimeException("$package isn't installed. Add it with npm install -D $package");
    }

    private function template(string $__file, array $__data): string
    {
        $site = $this;
        extract($__data);
        ob_start();
        require $__file;

        return ob_get_clean();
    }

    private static function text(string $html): string
    {
        $html = preg_replace('/<a[^>]*class="anchor".*?<\/a>/s', "", $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }
}
