<?php

namespace Dumbo\Jumbo;

/**
 * One page of items, from Site::paginate(). Pages get it as the "page" prop:
 *
 *   return new Page(
 *       paths: fn(Site $site) => $site->paginate($posts, 10),
 *       render: fn($context, $site, $props) => $site->view("blog", ["page" => $props["page"]]),
 *   );
 */
final class Pagination
{
    private ?Route $route = null;

    /**
     * @param array $items The items on this page
     * @param int $current This page's number, from 1
     * @param array<string, string> $params The page's other route params
     */
    public function __construct(
        public readonly array $items,
        public readonly int $current,
        public readonly int $last,
        public readonly int $size,
        public readonly int $total,
        public readonly string $param = "page",
        public readonly array $params = [],
    ) {
    }

    /**
     * The URL of a page by number, or null if there's no such page
     */
    public function url(int $page): ?string
    {
        if ($page < 1 || $page > $this->last || $this->route === null) {
            return null;
        }

        return $this->route->url([...$this->params, $this->param => (string) $page]);
    }

    public function previous(): ?string
    {
        return $this->url($this->current - 1);
    }

    public function next(): ?string
    {
        return $this->url($this->current + 1);
    }

    /**
     * The position of the first item on this page, from 1
     */
    public function start(): int
    {
        return $this->total === 0 ? 0 : ($this->current - 1) * $this->size + 1;
    }

    public function end(): int
    {
        return min($this->current * $this->size, $this->total);
    }

    /**
     * @internal The route the pages are built for, set when its paths are listed
     */
    public function bind(Route $route): void
    {
        $this->route = $route;
    }
}
