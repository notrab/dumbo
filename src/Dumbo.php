<?php

namespace Dumbo;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;

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

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        foreach ($this->routes as $route) {
            if (
                $route["method"] === $method &&
                $this->matchPath($route["path"], $path)
            ) {
                $params = $this->extractParams($route["path"], $path);
                $context = new Context($request, $params);

                $response = $this->runMiddleware($context, $route["handler"]);

                return $response instanceof ResponseInterface
                    ? $response
                    : $context->getResponse();
            }
        }

        return new Response(404, [], "404 Not Found");
    }

    public function run()
    {
        $request = $this->createServerRequestFromGlobals();
        $response = $this->handle($request);
        $this->send($response);
    }

    private function createServerRequestFromGlobals(): ServerRequestInterface
    {
        $request = ServerRequest::fromGlobals();
        $body = file_get_contents("php://input");

        return $request->withBody(\GuzzleHttp\Psr7\Utils::streamFor($body));
    }

    private function runMiddleware($context, $handler)
    {
        $next = function ($ctx) use ($handler) {
            $result = $handler($ctx);

            return $result instanceof ResponseInterface
                ? $result
                : $ctx->getResponse();
        };

        foreach (array_reverse($this->middleware) as $middleware) {
            $next = function ($ctx) use ($middleware, $next) {
                $result = $middleware($ctx, $next);

                return $result instanceof ResponseInterface
                    ? $result
                    : $ctx->getResponse();
            };
        }

        return $next($context);
    }

    private function matchPath($routePath, $requestPath)
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

    private function extractParams($routePath, $requestPath)
    {
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

    private function send(ResponseInterface $response)
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header("$name: $value", false);
            }
        }
        echo $response->getBody();
    }
}
