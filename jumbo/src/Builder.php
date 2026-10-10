<?php

namespace Dumbo\Jumbo;

/**
 * Renders a site's pages to static files, ready to upload to any static host
 * or CDN. Each page is a request through the app, so a built page matches
 * the one the server sends. Fonts and scripts from asset() and images from
 * img() are bundled, so the built site makes no requests to other hosts.
 */
final class Builder
{
    /** @var array<string> Files from node_modules the pages use */
    private array $vendor = [];

    /** @var array<string> Resized images the pages use */
    private array $images = [];

    /** @var array<string, array<string>> Root-relative links on each built page */
    private array $links = [];

    /** @var array<string> Built pages to list in the sitemap */
    private array $indexable = [];

    /** @var array<string> */
    private array $written = [];

    /** @var array<array{page: string, link: string}> */
    private array $broken = [];

    /**
     * @param string $base A sub-path the site is hosted under, like /docs.
     *                     Defaults to the path of the url config.
     */
    public function __construct(private Site $site, private string $out, private ?string $base = null)
    {
        $this->base ??= parse_url($site->config["url"] ?? "", PHP_URL_PATH) ?? "";
        $this->base = rtrim($this->base, "/");
    }

    /**
     * Every path to prerender, from the pages and their paths(), in each
     * locale, and the redirects
     *
     * @return array<string>
     */
    public function paths(): array
    {
        $paths = [];
        $locales = array_keys($this->site->locales()) ?: [null];

        foreach ($this->site->routes() as $route) {
            if ($route->page && !$route->page->prerenders($this->site)) {
                continue;
            }

            if ($route->params !== [] && !$route->page?->paths) {
                throw new \RuntimeException(
                    "$route->file has params, so it needs paths() to be built. Return a Page with paths, or with prerender: false to render it on each request."
                );
            }

            foreach ($route->params === [] ? [$route->path] : array_keys($this->site->paths($route)) as $path) {
                foreach ($locales as $locale) {
                    $paths[] = $this->site->localize($path, $locale);
                }
            }
        }

        // Redirects with params only work when served
        foreach ($this->site->redirects() as $path => $redirect) {
            if ($redirect["params"] === []) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Build the site into the output directory, replacing what's there
     *
     * @param callable(string $path): void|null $progress Called for each page
     * @return array<string> The files written, relative to the output directory
     */
    public function build(?callable $progress = null): array
    {
        $this->vendor = $this->images = $this->links = $this->indexable = $this->written = $this->broken = [];
        $this->clean();
        $this->site->emit("build:start", $this->out);

        foreach ($this->paths() as $path) {
            $this->page($path);

            if ($progress) {
                $progress($path);
            }
        }

        // Most static hosts serve 404.html for missing pages
        $response = $this->site->app->request("/404");

        if ($response->getStatusCode() === 404 && str_starts_with($response->getHeaderLine("Content-Type"), "text/html")) {
            $this->write("404.html", $this->rebase((string) $response->getBody()));
        }

        $this->bundle();
        $this->resize();
        $this->copy($this->site->path("public"), "");
        $this->sitemap();
        $this->check();
        $this->site->emit("build:done", $this->out, $this->written);

        return $this->written;
    }

    /**
     * Links to pages and files that weren't built and that the app doesn't
     * serve either, from the last build
     *
     * @return array<array{page: string, link: string}>
     */
    public function broken(): array
    {
        return $this->broken;
    }

    private function page(string $path): void
    {
        $response = $this->site->app->request($path);
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $file = $this->file($path);

        if ($status >= 300 && $status < 400) {
            // Static hosts can't redirect, so send the browser on with a meta refresh
            $location = $response->getHeaderLine("Location");
            $location = e(str_starts_with($location, "/") && !str_starts_with($location, "//") ? $this->base . $location : $location);
            $this->write($file, "<!DOCTYPE html><meta charset=\"utf-8\"><meta http-equiv=\"refresh\" content=\"0; url=$location\"><link rel=\"canonical\" href=\"$location\"><a href=\"$location\">Redirecting...</a>\n");
        } elseif ($status === 200) {
            preg_match_all('#"/vendor/([^"]+)"#', $body, $vendor);
            preg_match_all('#/_image/[^"\'\s,?]+#', $body, $images);
            $this->vendor = [...$this->vendor, ...$vendor[1]];
            $this->images = [...$this->images, ...$images[0]];

            if (str_starts_with($response->getHeaderLine("Content-Type"), "text/html")) {
                $this->links[$path] = self::links($body);

                if (!preg_match('#<meta\s[^>]*name=["\']robots["\'][^>]*noindex#i', $body)) {
                    $this->indexable[] = $path;
                }
            }

            $this->write($file, str_ends_with($file, ".html") ? $this->rebase($body) : $body);
        } else {
            $error = $this->site->lastError();

            throw new \RuntimeException("GET $path responded with $status" . ($status >= 500 && $error ? ": " . $error->getMessage() : ""), 0, $error);
        }
    }

    /**
     * The file a page is written to: /docs/routing becomes
     * docs/routing/index.html so it works on any static host, or
     * docs/routing.html with the "file" build format
     */
    private function file(string $path): string
    {
        $file = match (true) {
            pathinfo($path, PATHINFO_EXTENSION) !== "" => $path,
            $path === "/" => "/index.html",
            $this->site->config["build"]["format"] === "file" => "$path.html",
            default => "$path/index.html",
        };

        return rawurldecode(ltrim($file, "/"));
    }

    /**
     * Copy the npm files the pages use, and the fonts and images their CSS uses
     */
    private function bundle(): void
    {
        $queue = array_unique($this->vendor);
        $modules = $this->site->root . "/node_modules";

        while (($asset = array_shift($queue)) !== null) {
            $source = "$modules/$asset";

            if (str_contains($asset, "..") || !is_file($source) || in_array("vendor/$asset", $this->written, true)) {
                continue;
            }

            $this->write("vendor/$asset", file_get_contents($source));

            if (str_ends_with($asset, ".css")) {
                preg_match_all('#url\(\s*[\'"]?(?!data:|https?:|/)([^\'")?\#]+)#', file_get_contents($source), $matches);

                foreach ($matches[1] as $relative) {
                    $queue[] = self::normalize(dirname($asset) . "/$relative");
                }
            }
        }
    }

    /**
     * Write the resized images the pages use
     */
    private function resize(): void
    {
        foreach (array_unique($this->images) as $url) {
            $response = $this->site->app->request($url);

            if ($response->getStatusCode() === 200) {
                $this->write(rawurldecode(ltrim($url, "/")), (string) $response->getBody());
            }
        }
    }

    /**
     * List every built page that isn't noindex, when the site has a URL and
     * no page of its own at /sitemap.xml
     */
    private function sitemap(): void
    {
        $filter = $this->site->config["sitemap"];

        if (!$filter || !$this->site->config["url"] || in_array("sitemap.xml", $this->written, true)) {
            return;
        }

        $entries = "";

        foreach ($this->indexable as $path) {
            $url = $this->site->url($path);

            if (!is_callable($filter) || $filter($url)) {
                $entries .= "  <url><loc>" . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES) . "</loc></url>\n";
            }
        }

        $this->write("sitemap.xml", "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n$entries</urlset>\n");
    }

    /**
     * Find links to pages and files the build doesn't have. Paths the app
     * serves on demand, like API routes, aren't broken.
     */
    private function check(): void
    {
        $written = array_flip($this->written);
        $served = [];

        foreach ($this->links as $page => $links) {
            foreach (array_unique($links) as $link) {
                $file = trim(rawurldecode($link), "/");
                $built = isset($written[$file]) || isset($written[ltrim("$file/index.html", "/")]) || isset($written["$file.html"]);

                if (!$built && !($served[$link] ??= $this->site->app->request($link)->getStatusCode() < 400)) {
                    $this->broken[] = ["page" => $page, "link" => $link];
                }
            }
        }
    }

    /**
     * Prefix root-relative links and assets with the base path
     */
    private function rebase(string $html): string
    {
        if ($this->base === "") {
            return $html;
        }

        $html = preg_replace('#((?:href|src|action)=["\']?)/(?!/)#', "$1$this->base/", $html);

        return preg_replace_callback(
            '#(\ssrcset=(["\']))(.*?)\2#s',
            fn(array $m) => $m[1] . preg_replace('#(^|,\s*)/(?!/)#', "$1$this->base/", $m[3]) . $m[2],
            $html
        );
    }

    private function copy(string $from, string $to): void
    {
        if (!is_dir($from)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $this->write($to . substr($file->getPathname(), strlen($from) + 1), file_get_contents($file->getPathname()));
        }
    }

    private function write(string $file, string $contents): void
    {
        $path = "$this->out/$file";

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
        $this->written[] = $file;
    }

    /**
     * Empty the output directory, refusing one that holds the site itself
     */
    private function clean(): void
    {
        $out = realpath($this->out);

        if ($out === false) {
            return;
        }

        if (str_starts_with(realpath($this->site->root) . "/", rtrim($out, "/") . "/")) {
            throw new \RuntimeException("Refusing to empty $out, as the site is inside it");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($out, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
    }

    /**
     * The root-relative paths a page links to or loads, without query
     * strings or fragments
     *
     * @return array<string>
     */
    private static function links(string $html): array
    {
        preg_match_all('#\s(?:href|src)=["\'](/(?!/)[^"\'\#?]*)#', $html, $attributes);
        preg_match_all('#\ssrcset=["\']([^"\']*)#', $html, $sets);

        $links = $attributes[1];

        foreach ($sets[1] as $set) {
            preg_match_all('#(?:^|,)\s*(/(?!/)[^\s,?\#]*)#', $set, $sources);
            $links = [...$links, ...$sources[1]];
        }

        return $links;
    }

    /**
     * Resolve ./ and ../ in a path under node_modules
     */
    private static function normalize(string $path): string
    {
        $parts = [];

        foreach (explode("/", $path) as $part) {
            match ($part) {
                "", "." => null,
                ".." => array_pop($parts),
                default => $parts[] = $part,
            };
        }

        return implode("/", $parts);
    }
}
