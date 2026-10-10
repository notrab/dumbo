<?php

namespace Dumbo;

/**
 * Middleware from a mounted app's use($prefix, ...), kept on its routes and
 * run only for requests under the prefix. Mounting again prefixes it again.
 *
 * @internal
 */
final class ScopedMiddleware
{
    /**
     * @param string $prefix A normalized prefix, e.g. /api/admin
     */
    public function __construct(private string $prefix, private \Closure $middleware)
    {
    }

    public function under(string $prefix): self
    {
        return new self($prefix . $this->prefix, $this->middleware);
    }

    public function __invoke(Context $context, callable $next): mixed
    {
        return Dumbo::coversPath($this->prefix, $context->req->path())
            ? ($this->middleware)($context, $next)
            : $next($context);
    }
}
