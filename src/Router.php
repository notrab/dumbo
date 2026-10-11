<?php

namespace Dumbo;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Matches requests to routes. Paths are compiled to FastRoute's syntax and
 * matched the way FastRoute matches them: paths without params first, then
 * the rest in the order they were added.
 */
class Router
{
    private const WILDCARD = "__wildcard";

    /** Key for routes whose first segment isn't fixed, e.g. /:lang or /* */
    private const ANY = "*";

    /** A {name} or {name:regex} placeholder */
    private const PLACEHOLDER = '\{\s*([a-zA-Z_][a-zA-Z0-9_-]*)\s*(?::\s*([^{}]*(?:\{(?-1)\}[^{}]*)*))?\}';

    /** Routes combined into one regex, which keeps it under PCRE's size limit */
    private const CHUNK_SIZE = 50;

    /** @var array<array{method: string, path: string, compiled: string, handler: callable, middleware: array<callable>}> */
    private array $routes = [];

    /** @var array<string, array<string, int>> Indexes into $routes for paths without params, by method and path */
    private array $static = [];

    /**
     * @var array<string, array<int, array{int, string}>> Index into $routes and
     * compiled path for paths with params, by the first path segment they
     * match, keyed in the order they were added
     */
    private array $variable = [self::ANY => []];

    /** @var array<string, array<string, array>> Regexes by first segment and method, built on demand */
    private array $matchers = [];

    /** Orders $variable entries across segments */
    private int $sequence = 0;

    /**
     * @param string|array<string> $methods
     */
    public function addRoute(string|array $methods, string $path, callable $handler, array $middleware = []): void
    {
        $compiled = self::compile($path);
        $variants = self::variants($compiled, $path);

        foreach ((array) $methods as $method) {
            $index = count($this->routes);
            $this->routes[] = [
                "method" => $method,
                "path" => $path,
                "compiled" => $compiled,
                "handler" => $handler,
                "middleware" => $middleware,
            ];

            foreach ($variants as $variant) {
                if (!str_contains($variant, "{")) {
                    $this->static[$method][$variant] ??= $index;
                } else {
                    $this->variable[self::firstSegment($variant)][$this->sequence++] = [$index, $variant];
                }
            }
        }

        $this->matchers = [];
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
        $method = $request->getMethod();
        $path = self::normalize($request->getUri()->getPath());
        $segment = substr($path, 1, strcspn($path, "/", 1));

        if (!isset($this->variable[$segment])) {
            $segment = self::ANY;
        }

        $matchers = $this->matchers[$segment] ??= $this->buildMatchers($segment);

        // HEAD falls back to GET, and any method to routes added for "*"
        $found = $this->find($method, $path, $matchers)
            ?? ($method === "HEAD" ? $this->find("GET", $path, $matchers) : null)
            ?? $this->find("*", $path, $matchers);

        if ($found !== null) {
            [$index, $params] = $found;

            return [
                "route" => [
                    "handler" => $this->routes[$index]["handler"],
                    "params" => array_filter(
                        $params,
                        fn($name) => !str_starts_with($name, self::WILDCARD),
                        ARRAY_FILTER_USE_KEY
                    ),
                    "routePath" => $this->routes[$index]["path"],
                    "middleware" => $this->routes[$index]["middleware"],
                ],
                "allowedMethods" => [],
            ];
        }

        $allowed = [];

        foreach ($this->static as $other => $paths) {
            if ($other !== $method && isset($paths[$path])) {
                $allowed[] = $other;
            }
        }

        foreach (array_keys($matchers) as $other) {
            if ($other !== $method && $this->find($other, $path, $matchers) !== null) {
                $allowed[] = $other;
            }
        }

        return ["route" => null, "allowedMethods" => array_values(array_unique($allowed))];
    }

    /**
     * @return array{int, array<string, string>}|null The route index and params
     */
    private function find(string $method, string $path, array $matchers): ?array
    {
        if (isset($this->static[$method][$path])) {
            return [$this->static[$method][$path], []];
        }

        foreach ($matchers[$method] ?? [] as [$regex, $routes]) {
            if (preg_match($regex, $path, $matches)) {
                [$index, $names] = $routes[$matches["MARK"]];
                $params = [];

                foreach ($names as $i => $name) {
                    $params[$name] = $matches[$i + 1];
                }

                return [$index, $params];
            }
        }

        return null;
    }

    /**
     * Combine the regexes of routes with params that can match paths
     * starting with this segment, by method, so a request only compiles the
     * routes it might match. Each alternative is tagged with a MARK naming
     * the route, and the order they were added in is kept.
     */
    private function buildMatchers(string $segment): array
    {
        $entries = $segment === self::ANY
            ? $this->variable[self::ANY]
            : $this->variable[$segment] + $this->variable[self::ANY];

        ksort($entries);

        $byMethod = [];

        foreach ($entries as [$index, $variant]) {
            $byMethod[$this->routes[$index]["method"]][] = [$index, $variant];
        }

        $matchers = [];

        foreach ($byMethod as $method => $variants) {
            foreach (array_chunk($variants, self::CHUNK_SIZE) as $chunk) {
                $regexes = [];
                $routes = [];

                foreach ($chunk as $mark => [$index, $variant]) {
                    [$regex, $names] = $this->toRegex($variant, $index);
                    $regexes[] = "$regex(*MARK:$mark)";
                    $routes[$mark] = [$index, $names];
                }

                $matchers[$method][] = ["~^(?|" . implode("|", $regexes) . ")$~", $routes];
            }
        }

        return $matchers;
    }

    /**
     * Turn a compiled path without optional parts, like /users/{id:[0-9]+},
     * into a regex and its param names
     *
     * @return array{string, array<string>}
     * @throws \InvalidArgumentException If a param's regex has a capturing group
     */
    private function toRegex(string $variant, int $index): array
    {
        preg_match_all("~" . self::PLACEHOLDER . "~", $variant, $placeholders, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $regex = "";
        $names = [];
        $offset = 0;

        foreach ($placeholders as $placeholder) {
            $pattern = isset($placeholder[2]) ? trim($placeholder[2][0]) : "[^/]+";

            if (self::hasCapturingGroup($pattern)) {
                throw new \InvalidArgumentException(
                    "The regex for :{$placeholder[1][0]} in {$this->routes[$index]["path"]} can't have a capturing group, use (?:...) instead"
                );
            }

            $regex .= preg_quote(substr($variant, $offset, $placeholder[0][1] - $offset), "~") . "($pattern)";
            $names[] = $placeholder[1][0];
            $offset = $placeholder[0][1] + strlen($placeholder[0][0]);
        }

        return [$regex . preg_quote(substr($variant, $offset), "~"), $names];
    }

    /**
     * The paths a compiled route matches: /users[/{id}] is /users and
     * /users/{id}
     *
     * @return array<string>
     * @throws \InvalidArgumentException If the optional parts are malformed
     */
    private static function variants(string $compiled, string $path): array
    {
        if (!str_contains($compiled, "[")) {
            return [$compiled];
        }

        $required = rtrim($compiled, "]");
        $parts = preg_split("~" . self::PLACEHOLDER . "(*SKIP)(*F)|\\[~", $required);

        if (count($parts) - 1 !== strlen($compiled) - strlen($required) || in_array("", array_slice($parts, 1), true)) {
            throw new \InvalidArgumentException("Optional route parts must come last and not be empty in $path");
        }

        $variants = [];
        $current = "";

        foreach ($parts as $part) {
            $variants[] = $current .= $part;
        }

        return $variants;
    }

    /**
     * The fixed first segment of a compiled path, e.g. "users" for
     * /users/{id}, or ANY when it varies, as in /{lang}
     */
    private static function firstSegment(string $variant): string
    {
        $end = strcspn($variant, "/{", 1) + 1;

        return ($variant[$end] ?? "/") === "/" ? substr($variant, 1, $end - 1) : self::ANY;
    }

    /**
     * Whether a regex has a capturing group, which would shift the params.
     * Escapes, character classes and (?...) groups other than named ones
     * don't count.
     */
    private static function hasCapturingGroup(string $regex): bool
    {
        return str_contains($regex, "(") && preg_match(
            '~(?:\(\?\(|\[[^\]\\\\]*(?:\\\\.[^\]\\\\]*)*\]|\\\\.)(*SKIP)(*FAIL)|\((?!\?(?!<(?![!=])|P<|\')|\*)~',
            $regex
        ) === 1;
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

        // One replace is enough when every param is a whole segment, like /users/:id
        if (strpbrk($path, "*{?") === false) {
            $compiled = preg_replace('~(?<=/):(\w+)(?=/|$)~', '{$1}', "/" . trim($path, "/"), -1, $params);

            if (!str_contains($compiled, ":")) {
                return $params > 1 ? self::assertUniqueNames($compiled, $path) : $compiled;
            }
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

        return substr_count($compiled, "{") > 1 ? self::assertUniqueNames($compiled, $path) : $compiled;
    }

    /**
     * @throws \InvalidArgumentException If a param name is used twice
     */
    private static function assertUniqueNames(string $compiled, string $path): string
    {
        preg_match_all('/\{([a-zA-Z_][\w-]*)/', $compiled, $names);

        if (count($names[1]) !== count(array_unique($names[1]))) {
            throw new \InvalidArgumentException("Route params must have unique names in $path");
        }

        return $compiled;
    }

    public static function normalize(string $path): string
    {
        return "/" . trim($path, "/");
    }
}
