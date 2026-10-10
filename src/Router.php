<?php

namespace Dumbo;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Http\Message\ServerRequestInterface;

class Router
{
    private ?Dispatcher $dispatcher = null;

    /** @var array<array{method: string, path: string, handler: callable, middleware: array<callable>}> */
    private array $routes = [];

    public function addRoute(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $this->routes[] = [
            "method" => $method,
            "path" => $path,
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
                // Convert :param to FastRoute's {param}
                $path = preg_replace("/:(\w+)/", '{$1}', $route["path"]);
                $r->addRoute($route["method"], self::normalize($path), $route);
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
                    "params" => $result[2],
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

    public static function normalize(string $path): string
    {
        return "/" . trim($path, "/");
    }
}
