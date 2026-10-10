<?php

namespace Dumbo\Jumbo;

use Closure;

/**
 * What a page file in pages/ returns when it needs more than a template:
 *
 *   return new Page(
 *       paths: fn(Site $site) => [["slug" => "hello"], ["slug" => "world"]],
 *       render: fn(Context $context, Site $site) => $site->view("post", [...]),
 *   );
 *
 * render() returns HTML, a response or null for the context's response.
 * paths() lists the params to build for a page with [params] in its file
 * name. Set prerender to false, or a closure that decides, to render the page
 * on each request instead of at build time.
 */
final class Page
{
    /**
     * @param Closure(\Dumbo\Context, Site): mixed $render
     * @param (Closure(Site): iterable<array<string, string>>)|null $paths
     * @param bool|Closure(Site): bool $prerender
     * @param array<callable> $middleware Run before render, like route middleware
     */
    public function __construct(
        public readonly Closure $render,
        public readonly ?Closure $paths = null,
        public readonly bool|Closure $prerender = true,
        public readonly array $middleware = [],
    ) {
    }

    public function prerenders(Site $site): bool
    {
        return $this->prerender instanceof Closure ? (bool) ($this->prerender)($site) : $this->prerender;
    }
}
