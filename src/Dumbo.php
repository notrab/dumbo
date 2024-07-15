<?php

namespace Dumbo;

class Dumbo
{
    private $routes = [];
    private $middleware = [];
    private $prefix = "";

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
        $method = $_SERVER["REQUEST_METHOD"];
        $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if (
                $route["method"] === $method &&
                $this->matchPath($route["path"], $path)
            ) {
                $context = new Context(
                    $this->extractParams($route["path"], $path)
                );

                $response = $this->runMiddleware($context, $route["handler"]);

                if ($response instanceof Response) {
                    $response->send();
                } else {
                    echo $response;
                }

                return;
            }
        }

        http_response_code(404);
        echo "404 Not Found";
    }

    private function runMiddleware($context, $handler)
    {
        $next = $handler;

        foreach (array_reverse($this->middleware) as $mw) {
            $next = function ($ctx) use ($mw, $next) {
                return $mw($ctx, $next);
            };
        }

        return $next($context);
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

    private function isParam($part)
    {
        return strpos($part, ":") === 0;
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
}
