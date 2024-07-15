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
        $uri = $_SERVER["REQUEST_URI"];
        $path = parse_url($uri, PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if (
                $route["method"] === $method &&
                $this->matchPath($route["path"], $path)
            ) {
                $params = $this->extractParams($route["path"], $path);
                $context = new Context(
                    $params,
                    $this->parseQueryString($uri),
                    $this->parseRequestBody(),
                    $this->getRequestHeaders()
                );

                $response = $this->runMiddleware($context, $route["handler"]);

                if ($response instanceof Response) {
                    $response->send();
                } elseif ($response !== null) {
                    $context->getResponse()->text($response)->send();
                } else {
                    $context->getResponse()->send();
                }

                return;
            }
        }

        (new Response())->status(404)->text("404 Not Found")->send();
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

    private function parseRequestBody()
    {
        $contentType = $_SERVER["CONTENT_TYPE"] ?? "";
        $contentLength = $_SERVER["CONTENT_LENGTH"] ?? 0;

        if (
            strpos($contentType, "application/x-www-form-urlencoded") !== false
        ) {
            return $_POST;
        }

        if (strpos($contentType, "application/json") !== false) {
            $input = file_get_contents("php://input");
            return json_decode($input, true);
        }

        if (strpos($contentType, "multipart/form-data") !== false) {
            return [
                "post" => $_POST,
                "files" => $_FILES,
            ];
        }

        if ($contentLength > 1024 * 1024) {
            return fopen("php://input", "rb");
        }

        return file_get_contents("php://input");
    }

    private function getRequestHeaders()
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, "HTTP_") === 0) {
                $name = str_replace(
                    " ",
                    "-",
                    ucwords(strtolower(str_replace("_", " ", substr($key, 5))))
                );
                $headers[$name] = $value;
            } elseif (
                in_array(
                    $key,
                    ["CONTENT_TYPE", "CONTENT_LENGTH", "CONTENT_MD5"],
                    true
                )
            ) {
                $name = str_replace(
                    " ",
                    "-",
                    ucwords(strtolower(str_replace("_", " ", $key)))
                );
                $headers[$name] = $value;
            }
        }

        return $headers;
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
}
