<?php

namespace Dumbo\Jumbo;

/**
 * Renders a site's pages to static files, ready to upload to any static host
 * or CDN. Each page is a request through the app, so a built page matches
 * the one the server sends. Fonts and scripts from asset() are bundled from
 * node_modules, so the built site makes no requests to other hosts.
 */
final class Builder
{
    /** @var array<string> Files from node_modules the pages use */
    private array $vendor = [];

    /** @var array<string> */
    private array $written = [];

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
     * Every path to prerender, from the pages and their paths()
     *
     * @return array<string>
     */
    public function paths(): array
    {
        $paths = [];

        foreach ($this->site->routes() as $route) {
            if ($route->page && !$route->page->prerenders($this->site)) {
                continue;
            }

            if ($route->params === []) {
                $paths[] = $route->path;

                continue;
            }

            if (!$route->page?->paths) {
                throw new \RuntimeException(
                    "$route->file has params, so it needs paths() to be built. Return a Page with paths, or with prerender: false to render it on each request."
                );
            }

            foreach (($route->page->paths)($this->site) as $params) {
                $paths[] = $route->url($params);
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
        $this->vendor = $this->written = [];
        $this->clean();

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
        $this->copy($this->site->path("public"), "");

        return $this->written;
    }

    private function page(string $path): void
    {
        $response = $this->site->app->request($path);
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        // Pages become path/index.html, so /docs/routing works on any static host
        $file = rawurldecode(ltrim(pathinfo($path, PATHINFO_EXTENSION) ? $path : "$path/index.html", "/"));

        if ($status >= 300 && $status < 400) {
            // Static hosts can't redirect, so send the browser on with a meta refresh
            $location = e($this->base . $response->getHeaderLine("Location"));
            $this->write($file, "<!DOCTYPE html><meta charset=\"utf-8\"><meta http-equiv=\"refresh\" content=\"0; url=$location\"><link rel=\"canonical\" href=\"$location\"><a href=\"$location\">Redirecting...</a>\n");
        } elseif ($status === 200) {
            preg_match_all('#"/vendor/([^"]+)"#', $body, $matches);
            $this->vendor = [...$this->vendor, ...$matches[1]];
            $this->write($file, str_ends_with($file, ".html") ? $this->rebase($body) : $body);
        } else {
            throw new \RuntimeException("GET $path responded with $status");
        }
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
     * Prefix root-relative links and assets with the base path
     */
    private function rebase(string $html): string
    {
        return $this->base === ""
            ? $html
            : preg_replace('#((?:href|src|action)=["\']?)/(?!/)#', "$1$this->base/", $html);
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
