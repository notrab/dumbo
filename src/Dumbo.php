<?php

namespace Dumbo;

use Dumbo\Adapters\PhpDevelopmentServer;

class Dumbo
{
    private $routes = [];
    private $middleware = [];
    private $prefix = "";
    private $server;

    public function __construct(ServerInterface $server = null)
    {
        $this->server = $server ?? $this->createDefaultServer();
    }

    public function __call($method, $arguments)
    {
        $supportedMethods = [
            "get",
            "post",
            "put",
            "delete",
            "patch",
            "options",
        ];

        if (in_array(strtolower($method), $supportedMethods)) {
            $this->addRoute(strtoupper($method), ...$arguments);
        } else {
            throw new \BadMethodCallException("METHOD $method does not exist.");
        }
    }

    public function on($method, $path, $handler)
    {
        $this->addRoute(strtoupper($method), $path, $handler);
    }

    private function addRoute($method, $path, $handler)
    {
        $this->routes[] = [
            "method" => $method,
            "path" => $this->prefix . $path,
            "handler" => $handler,
        ];
    }

    public function use($middleware)
    {
        $this->middleware[] = $middleware;
    }

    public function route($prefix, Dumbo $nestedApp)
    {
        foreach ($nestedApp->routes as $route) {
            $this->routes[] = [
                "method" => $route["method"],
                "path" => $prefix . $route["path"],
                "handler" => $route["handler"],
            ];
        }
    }

    public function run()
    {
        $method = $this->server->getMethod();
        $uri = $this->server->getUri();
        $path = parse_url($uri, PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if (
                $route["method"] === $method &&
                $this->matchPath($route["path"], $path)
            ) {
                $params = $this->extractParams($route["path"], $path);
                $context = new Context(
                    $method,
                    $params,
                    $this->parseQueryString($uri),
                    $this->server->getBody(),
                    $this->server->getHeaders()
                );

                $response = $this->runMiddleware($context, $route["handler"]);

                if ($response instanceof Response) {
                    $this->server->sendResponse(
                        $response->getStatusCode(),
                        $response->getHeaders(),
                        $response->getBody() ?? ""
                    );
                } elseif ($response !== null) {
                    $this->server->sendResponse(
                        200,
                        ["Content-Type" => "text/plain"],
                        (string) $response
                    );
                } else {
                    $this->server->sendResponse(204, [], "");
                }

                return;
            }
        }

        $this->server->sendResponse(
            404,
            ["Content-Type" => "text/plain"],
            "404 Not Found"
        );
    }

    private function matchPath($routePath, $requestPath)
    {
        $routeParts = explode("/", trim($routePath, "/"));
        $requestParts = explode("/", trim($requestPath, "/"));

        if (count($routeParts) !== count($requestParts)) {
            return false;
        }

        return array_reduce(
            array_map(null, $routeParts, $requestParts),
            function ($carry, $parts) {
                [$routePart, $requestPart] = $parts;
                return $carry &&
                    ($routePart === $requestPart || $this->isParam($routePart));
            },
            true
        );
    }

    private function extractParams($routePath, $requestPath)
    {
        $routeParts = explode("/", trim($routePath, "/"));
        $requestParts = explode("/", trim($requestPath, "/"));

        return array_reduce(
            array_map(null, $routeParts, $requestParts),
            function ($params, $parts) {
                [$routePart, $requestPart] = $parts;

                if ($this->isParam($routePart)) {
                    $params[substr($routePart, 1)] = $requestPart;
                }

                return $params;
            },
            []
        );
    }

    private function isParam($part)
    {
        return strpos($part, ":") === 0;
    }

    private function parseQueryString($uri)
    {
        $query = parse_url($uri, PHP_URL_QUERY);

        if ($query === null) {
            return [];
        }

        parse_str($query, $queryParams);

        return $queryParams;
    }

    private function runMiddleware($context, $handler)
    {
        $next = $handler;

        foreach (array_reverse($this->middleware) as $middleware) {
            $next = function ($ctx) use ($middleware, $next) {
                return $middleware($ctx, $next);
            };
        }

        return $next($context);
    }

    private function createDefaultServer(): ServerInterface
    {
        return new PhpDevelopmentServer();
    }
}
