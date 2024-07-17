<?php

namespace Dumbo;

use Psr\Http\Message\ServerRequestInterface;

class Router
{
    /** @var array<array{method: string, path: string, handler: callable(Context): (ResponseInterface|null)}> */
    private $routes = [];

    /** @var string */
    private $prefix = "";

    public function addRoute(
        string $method,
        string $path,
        callable $handler
    ): void {
        $this->routes[] = [
            "method" => $method,
            "path" => $this->prefix . $path,
            "handler" => $handler,
        ];
    }

    public function findRoute(ServerRequestInterface $request): ?array
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        foreach ($this->routes as $route) {
            if (
                $route["method"] === $method &&
                $this->matchPath($route["path"], $path)
            ) {
                $params = $this->extractParams($route["path"], $path);
                return [
                    "handler" => $route["handler"],
                    "params" => $params,
                    "routePath" => $route["path"],
                ];
            }
        }

        return null;
    }

    /**
     * Check if a route path matches the request path
     *
     * This method compares the route path pattern with the actual request path,
     * accounting for path parameters (e.g., ":id" in "/users/:id").
     *
     * @param string $routePath The route path pattern
     * @param string $requestPath The actual request path
     * @return bool True if the paths match, false otherwise
     */
    private function matchPath(string $routePath, string $requestPath): bool
    {
        $routePath = trim($routePath, "/");
        $requestPath = trim($requestPath, "/");

        if ($routePath === "" && $requestPath === "") {
            return true;
        }

        $routeParts = $routePath ? explode("/", $routePath) : [];
        $requestParts = $requestPath ? explode("/", $requestPath) : [];

        if (count($routeParts) !== count($requestParts)) {
            return false;
        }

        foreach ($routeParts as $index => $routePart) {
            if ($routePart[0] === ":") {
                continue;
            }

            if ($routePart !== $requestParts[$index]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract parameters from the request path based on the route path
     *
     * This method extracts values for path parameters defined in the route path
     * (e.g., it will extract "123" as the value for ":id" from "/users/123" if the
     * route path is "/users/:id").
     *
     * @param string $routePath The route path pattern
     * @param string $requestPath The actual request path
     * @return array<string, string|null> An associative array of parameter names and their values
     */
    private function extractParams(
        string $routePath,
        string $requestPath
    ): array {
        $params = [];

        $routePath = trim($routePath, "/");
        $requestPath = trim($requestPath, "/");

        if ($routePath === "" && $requestPath === "") {
            return $params;
        }

        $routeParts = $routePath ? explode("/", $routePath) : [];
        $requestParts = $requestPath ? explode("/", $requestPath) : [];

        foreach ($routeParts as $index => $routePart) {
            if ($routePart[0] === ":") {
                $params[substr($routePart, 1)] = $requestParts[$index] ?? null;
            }
        }

        return $params;
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
