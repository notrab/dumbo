<?php

namespace Dumbo\Jumbo;

/**
 * A file in pages/ and the route it serves:
 *
 *   index.php               /
 *   about.md                /about
 *   blog/[slug].php         /blog/{slug}
 *   docs/[...path].php      /docs/{path:.+}, matching any depth
 *   sitemap.xml.php         /sitemap.xml
 */
final class Route
{
    /**
     * @param string $path The Dumbo route path
     * @param array<string, bool> $params Param names, true for [...rest] params
     * @param Page|null $page What the file returned, or null for templates and Markdown
     */
    public function __construct(
        public readonly string $path,
        public readonly string $file,
        public readonly array $params = [],
        public readonly ?Page $page = null,
    ) {
    }

    /**
     * Turn a file's path under pages/ into a route path and its params
     *
     * @return array{string, array<string, bool>}
     */
    public static function fromFile(string $relative): array
    {
        $name = preg_replace('/\.(php|md)$/', "", $relative);
        $segments = explode("/", $name);

        if (end($segments) === "index") {
            array_pop($segments);
        }

        $params = [];
        $path = preg_replace_callback(
            '/\[(\.\.\.)?(\w+)]/',
            function (array $m) use (&$params, $relative): string {
                if (isset($params[$m[2]])) {
                    throw new \InvalidArgumentException("pages/$relative uses [$m[2]] twice");
                }

                $params[$m[2]] = $m[1] !== "";

                return $m[1] !== "" ? "{{$m[2]}:.+}" : "{{$m[2]}}";
            },
            "/" . implode("/", $segments)
        );

        return [$path, $params];
    }

    /**
     * The URL for a set of params, e.g. /blog/hello for ["slug" => "hello"]
     */
    public function url(array $params): string
    {
        return preg_replace_callback('/\{(\w+)(:\.\+)?}/', function (array $m) use ($params): string {
            $value = (string) ($params[$m[1]] ?? throw new \InvalidArgumentException("paths() in $this->file is missing \"$m[1]\""));
            $parts = isset($m[2]) ? explode("/", trim($value, "/")) : [$value];

            foreach ($parts as $part) {
                if (in_array($part, ["", ".", ".."], true) || (!isset($m[2]) && str_contains($part, "/"))) {
                    throw new \InvalidArgumentException("\"$value\" isn't a valid value for [$m[1]]");
                }
            }

            return implode("/", array_map(rawurlencode(...), $parts));
        }, $this->path);
    }

    /**
     * The content type for the route's extension, like sitemap.xml.php
     */
    public function contentType(): string
    {
        return match (strtolower(pathinfo($this->path, PATHINFO_EXTENSION))) {
            "xml" => "application/xml",
            "txt" => "text/plain",
            "json" => "application/json",
            "rss" => "application/rss+xml",
            "css" => "text/css",
            "js" => "text/javascript",
            "svg" => "image/svg+xml",
            default => "text/html",
        };
    }

    /**
     * Sort key so specific routes match first: fewer rest params, then fewer
     * params, then more segments
     */
    public function rank(): array
    {
        return [
            count(array_filter($this->params)),
            count($this->params),
            -substr_count($this->path, "/"),
            $this->path,
        ];
    }
}
