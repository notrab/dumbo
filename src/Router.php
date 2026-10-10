<?php

namespace Dumbo;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Http\Message\ServerRequestInterface;

class Router
{
    private const WILDCARD = "__wildcard";

    private ?Dispatcher $dispatcher = null;

    /** @var array<string, string> Compiled paths, so each is compiled once */
    private array $compiled = [];

    /** @var array<array{method: string, path: string, compiled: string, handler: callable, middleware: array<callable>}> */
    private array $routes = [];

    public function addRoute(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $this->routes[] = [
            "method" => $method,
            "path" => $path,
            "compiled" => $this->compiled[$path] ??= self::compile($path),
            "handler" => $handler,
            "middleware" => $middleware,
        ];
        $this->dispatcher = null;
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
        $this->dispatcher ??= \FastRoute\simpleDispatcher(function (RouteCollector $r) {
            foreach ($this->routes as $route) {
                $r->addRoute($route["method"], $route["compiled"], $route);
            }
        });

        $result = $this->dispatcher->dispatch(
            $request->getMethod(),
            self::normalize($request->getUri()->getPath())
        );

        return match ($result[0]) {
            Dispatcher::FOUND => [
                "route" => [
                    "handler" => $result[1]["handler"],
                    "params" => str_contains($result[1]["compiled"], self::WILDCARD)
                        ? array_filter(
                            $result[2],
                            fn($name) => !str_starts_with($name, self::WILDCARD),
                            ARRAY_FILTER_USE_KEY
                        )
                        : $result[2],
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
        // Split on slashes outside of {...} patterns
        $segments = preg_split('#/(?![^{]*})#', trim($path, "/"));
        $wildcards = 0;
        $required = [];
        $optional = [];

        foreach ($segments as $i => $segment) {
            $isOptional = str_ends_with($segment, "?") || ($segment === "*" && $i === count($segments) - 1);

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
