<?php

namespace Dumbo;

class Dumbo
{
    private $routes = [];
    private $middleware = [];

    public function get($path, $handler)
    {
        $this->addRoute("GET", $path, $handler);
    }

    public function post($path, $handler)
    {
        $this->addRoute("POST", $path, $handler);
    }

    public function put($path, $handler)
    {
        $this->addRoute("PUT", $path, $handler);
    }

    public function delete($path, $handler)
    {
        $this->addRoute("DELETE", $path, $handler);
    }

    private function addRoute($method, $path, $handler)
    {
        $this->routes[] = [
            "method" => $method,
            "path" => $path,
            "handler" => $handler,
        ];
    }

    public function use($middleware)
    {
        $this->middleware[] = $middleware;
    }

    public function run()
    {
        $method = $_SERVER["REQUEST_METHOD"];
        $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if ($route["method"] === $method) {
                $params = $this->matchRoute($route["path"], $path);
                if ($params !== false) {
                    $context = new Context($params);

                    foreach ($this->middleware as $mw) {
                        $mw($context);
                    }

                    $response = $route["handler"]($context);

                    if ($response instanceof Response) {
                        $response->send();
                    } else {
                        echo $response;
                    }
                    return;
                }
            }
        }

        http_response_code(404);
        echo "404 Not Found";
    }

    private function matchRoute($routePath, $requestPath)
    {
        $routeParts = explode("/", trim($routePath, "/"));
        $requestParts = explode("/", trim($requestPath, "/"));

        if (count($routeParts) !== count($requestParts)) {
            return false;
        }

        $params = [];
        for ($i = 0; $i < count($routeParts); $i++) {
            if (strpos($routeParts[$i], ":") === 0) {
                $paramName = substr($routeParts[$i], 1);
                $params[$paramName] = $requestParts[$i];
            } elseif ($routeParts[$i] !== $requestParts[$i]) {
                return false;
            }
        }

        return $params;
    }
}
