<?php

namespace Dumbo;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Router class for handling HTTP routes in the Dumbo framework
 */
class Router
{
    private ?Dispatcher $dispatcher = null;

    /** @var array<array{method: string, path: string, handler: callable(Context): (ResponseInterface|null), middleware: array}> */
    private $routes = [];

    /**
     * Array of route groups
     *
     * @var array<string, array>
     */
    private array $groups = [];

    /**
     * Constructor
     *
     * Initializes the router and invokes the initial dispatcher.
     */
    public function __construct()
    {
        $this->rebuildDispatcher();
    }

    /**
     * Add a route to the router
     *
     * @param string $method The HTTP method for the route
     * @param string $path The URL path for the route
     * @param callable $handler The handler function for the route
     * @param array<callable> $middleware Array of middleware functions for the route
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

    /**
     * Add a group of routes with a common prefix
     *
     * @param string $prefix The common prefix for the group of routes
     * @param array $groupRoutes Array of routes in the group
     */
    public function addGroup(string $prefix, array $groupRoutes): void
    {
        $this->groups[$prefix] = $groupRoutes;
        $this->rebuildDispatcher();
    }

    /**
     * Find a matching route for the given request
     *
     * @param ServerRequestInterface $request The incoming HTTP request
     * @return array|null The matched route information or null if no match found
     */
    public function findRoute(ServerRequestInterface $request): ?array
    {
        if (!$this->dispatcher) {
            return null;
        }

        $httpMethod = $request->getMethod();
        $uri = $this->normalizeUri($request->getUri()->getPath());

        $routeInfo = $this->dispatcher->dispatch($httpMethod, $uri);

        if ($routeInfo[0] === Dispatcher::FOUND) {
            $handler = $routeInfo[1];
            $vars = $routeInfo[2];

            return [
                "handler" => $handler["handler"],
                "params" => $vars,
                "routePath" => $handler["path"],
                "middleware" => $handler["middleware"] ?? [],
            ];
        }

        return null;
    }

    /**
     * Get all declared routes
     *
     * @return array All registered routes including group routes
     */
    public function getRoutes(): array
    {
        $allRoutes = $this->routes;

        foreach ($this->groups as $groupRoutes) {
            $allRoutes = array_merge($allRoutes, $groupRoutes);
        }

        return $allRoutes;
    }

    /**
     * Prepare the path by converting :parameter syntax to {parameter}
     *
     * @param string $path The route path to prepare
     * @return string The prepared path
     */
    private function preparePath(string $path): string
    {
        $path = preg_replace("/:(\w+)/", '{$1}', $path);
        return $this->normalizeUri($path);
    }

    /**
     * Rebuild the FastRoute dispatcher
     *
     * This method is called whenever routes are added or modified.
     */
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

    /**
     * Normalize the given URI by ensuring it starts with a forward slash
     *
     * @param string $uri The URI to normalize
     * @return string The normalized URI
     */
    private function normalizeUri(string $uri): string
    {
        return "/" . trim($uri, "/");
    }
}
