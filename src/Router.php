<?php

namespace Dumbo;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Http\Message\ServerRequestInterface;

class Router
{
    private ?Dispatcher $dispatcher = null;

    /** @var array<array{method: string, path: string, handler: callable(Context): (ResponseInterface|null), middleware: array}> */
    private $routes = [];

    /** @var string */
    private array $groups = [];

    public function __construct()
    {
        $this->rebuildDispatcher();
    }

    /**
     * Add a route to the router
     */
    public function addRoute(
        string $method,
        string $path,
        callable $handler,
        array $middleware = []
    ): void {
        $this->routes[] = [
            "method" => $method,
            "path" => $path,
            "handler" => $handler,
            "middleware" => $middleware,
        ];
        $this->rebuildDispatcher();
    }

    public function addGroup(string $prefix, array $groupRoutes): void
    {
        $this->groups[$prefix] = $groupRoutes;
        $this->rebuildDispatcher();
    }

    public function findRoute(ServerRequestInterface $request): ?array
    {
        if (!$this->dispatcher) {
            error_log("Dispatcher is null");
            return null;
        }

        $httpMethod = $request->getMethod();
        $uri = "/" . trim($request->getUri()->getPath(), "/");

        error_log("Attempting to match route: $httpMethod $uri");

        $routeInfo = $this->dispatcher->dispatch($httpMethod, $uri);

        if ($routeInfo[0] === Dispatcher::FOUND) {
            $handler = $routeInfo[1];
            $vars = $routeInfo[2];

            error_log("Route found: " . json_encode($handler));

            return [
                "handler" => $handler["handler"],
                "params" => $vars,
                "routePath" => $handler["path"],
                "middleware" => $handler["middleware"] ?? [],
            ];
        }

        error_log("No matching route found");
        return null;
    }

    public function getRoutes(): array
    {
        $allRoutes = $this->routes;

        foreach ($this->groups as $groupRoutes) {
            $allRoutes = array_merge($allRoutes, $groupRoutes);
        }

        return $allRoutes;
    }

    private function preparePath(string $path): string
    {
        $path = preg_replace("/:(\w+)/", '{$1}', $path);
        return "/" . trim($path, "/");
    }

    private function rebuildDispatcher(): void
    {
        $this->dispatcher = \FastRoute\simpleDispatcher(function (
            RouteCollector $r
        ) {
            foreach ($this->routes as $route) {
                $path = $this->preparePath($route["path"]);
                $r->addRoute($route["method"], $path, $route);
            }
        });
    }
}
