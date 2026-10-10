<?php

namespace Dumbo;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Http\Message\ServerRequestInterface;

class Router
{
    private const WILDCARD = "__wildcard";

    /** Key for routes whose first segment isn't fixed, e.g. /:lang or /* */
    private const ANY = "*";

    /** @var array<string, Dispatcher> Built on demand, one per first path segment */
    private array $dispatchers = [];

    /** @var array<array{method: string, path: string, compiled: string, handler: callable, middleware: array<callable>}> */
    private array $routes = [];

    /** @var array<string, array<int>> Indexes into $routes, by the first path segment they match */
    private array $segments = [self::ANY => []];

    /**
     * @param string|array<string> $methods
     */
    public function addRoute(string|array $methods, string $path, callable $handler, array $middleware = []): void
    {
        $compiled = self::compile($path);
        $segment = self::firstSegment($compiled);

        foreach ((array) $methods as $method) {
            $this->segments[$segment][] = count($this->routes);
            $this->routes[] = [
                "method" => $method,
                "path" => $path,
                "compiled" => $compiled,
                "handler" => $handler,
                "middleware" => $middleware,
            ];
        }

        $this->dispatchers = [];
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Match the request against the registered routes. When the path exists
     * but not for this method, the route is null and allowedMethods lists the
     * methods it does accept.
     *
     * @return array{route: array|null, allowedMethods: array<string>}
     */
    public function dispatch(ServerRequestInterface $request): array
    {
        $path = self::normalize($request->getUri()->getPath());
        $segment = substr($path, 1, strcspn($path, "/", 1));

        if (!isset($this->segments[$segment])) {
            $segment = self::ANY;
        }

        $result = ($this->dispatchers[$segment] ??= $this->buildDispatcher($segment))
            ->dispatch($request->getMethod(), $path);

        return match ($result[0]) {
            Dispatcher::FOUND => [
                "route" => [
                    "handler" => $result[1]["handler"],
                    "params" => array_filter(
                        $result[2],
                        fn($name) => !str_starts_with($name, self::WILDCARD),
                        ARRAY_FILTER_USE_KEY
                    ),
                    "routePath" => $result[1]["path"],
                    "middleware" => $result[1]["middleware"],
                ],
                "allowedMethods" => [],
            ],
            Dispatcher::METHOD_NOT_ALLOWED => [
                "route" => null,
                "allowedMethods" => array_values(array_unique($result[1])),
            ],
            default => ["route" => null, "allowedMethods" => []],
        };
    }

    /**
     * Build a dispatcher with only the routes that can match paths starting
     * with this segment, so a request doesn't pay to compile every route.
     * Registration order is kept, as FastRoute matches in that order.
     */
    private function buildDispatcher(string $segment): Dispatcher
    {
        $indexes = $segment === self::ANY
            ? $this->segments[self::ANY]
            : array_merge($this->segments[$segment], $this->segments[self::ANY]);

        sort($indexes);

        return \FastRoute\simpleDispatcher(function (RouteCollector $r) use ($indexes) {
            foreach ($indexes as $i) {
                $r->addRoute($this->routes[$i]["method"], $this->routes[$i]["compiled"], $this->routes[$i]);
            }
        });
    }

    /**
     * The fixed first segment of a compiled route, e.g. "users" for
     * /users/{id}, or ANY when it varies, as in /{lang} or /[{id}]
     */
    private static function firstSegment(string $compiled): string
    {
        $end = strcspn($compiled, "/[{", 1) + 1;
        $next = substr($compiled, $end, 2);

        return $next === "" || $next[0] === "/" || $next === "[/"
            ? substr($compiled, 1, $end - 1)
            : self::ANY;
    }

    /**
     * Convert a route path to FastRoute's syntax:
     *  - :id becomes {id}
     *  - :id{[0-9]+} becomes {id:[0-9]+}
     *  - :id? at the end is optional
     *  - * matches anything, and a trailing /* also matches without it
     * FastRoute's own {id:pattern} syntax is left as is.
     *
     * @throws \InvalidArgumentException If an optional part isn't at the end
     */
    public static function compile(string $path): string
    {
        // Nothing to convert in a plain path like /about
        if (strpbrk($path, ":*{?") === false) {
            return "/" . trim($path, "/");
        }

        // Split on slashes outside of {...} patterns
        $segments = str_contains($path, "{")
            ? preg_split('#/(?![^{]*})#', trim($path, "/"))
            : explode("/", trim($path, "/"));
        $wildcards = 0;
        $required = [];
        $optional = [];

        foreach ($segments as $i => $segment) {
            $isOptional = str_ends_with($segment, "?") || ($segment === "*" && $i === count($segments) - 1);

            if (str_starts_with($segment, ":") && preg_match('/^:(\w+)\??$/', $segment, $m)) {
                // The usual :id or :id? needs no callback
                $segment = "{{$m[1]}}";
            } elseif (strpbrk($segment, ":*{") !== false) {
                $segment = preg_replace_callback(
                    '/\{(?:[^{}]|\{[^{}]*})*}|\*|:(\w+)(?:\{((?:[^{}]|\{[^{}]*})+)})?\??/',
                    function (array $m) use (&$wildcards, $isOptional): string {
                        return match (true) {
                            $m[0][0] === "{" => $m[0],
                            $m[0] === "*" => "{" . self::WILDCARD . $wildcards++ . ($isOptional ? ":.*}" : ":.+}"),
                            isset($m[2]) => "{{$m[1]}:{$m[2]}}",
                            default => "{{$m[1]}}",
                        };
                    },
                    $segment
                );
            }

            if ($isOptional) {
                $optional[] = $segment;
            } elseif ($optional !== []) {
                throw new \InvalidArgumentException("Optional route parts must come last in $path");
            } else {
                $required[] = $segment;
            }
        }

        $compiled = "/" . implode("/", $required);

        if ($optional !== []) {
            $compiled = ($required === [] ? "/[" : $compiled . "[/")
                . implode("[/", $optional)
                . str_repeat("]", count($optional));
        }

        if (
            substr_count($compiled, "{") > 1
            && preg_match_all('/\{([a-zA-Z_][\w-]*)/', $compiled, $names)
            && count($names[1]) !== count(array_unique($names[1]))
        ) {
            throw new \InvalidArgumentException("Route params must have unique names in $path");
        }

        return $compiled;
    }

    public static function normalize(string $path): string
    {
        return "/" . trim($path, "/");
    }
}
