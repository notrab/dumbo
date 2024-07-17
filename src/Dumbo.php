<?php

namespace Dumbo;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;

/**
 * Dumbo - A simple PHP HTTP framework
 *
 * @package Dumbo
 * @author Jamie Barton
 * @version 1.0.0
 *
 * @method void get(string $path, callable(Context): (ResponseInterface|null) $handler) Add a GET route
 * @method void post(string $path, callable(Context): (ResponseInterface|null) $handler) Add a POST route
 * @method void put(string $path, callable(Context): (ResponseInterface|null) $handler) Add a PUT route
 * @method void delete(string $path, callable(Context): (ResponseInterface|null) $handler) Add a DELETE route
 * @method void patch(string $path, callable(Context): (ResponseInterface|null) $handler) Add a PATCH route
 * @method void options(string $path, callable(Context): (ResponseInterface|null) $handler) Add an OPTIONS route
 */
class Dumbo
{
    /** @var array<array{method: string, path: string, handler: callable(Context): (ResponseInterface|null)}> */
    private $routes = [];

    /** @var array<callable> */
    private $middleware = [];

    /** @var string */
    private $prefix = "";

    /**
     * Magic method to handle dynamic route addition
     *
     * @param string $method The HTTP method (get, post, etc.)
     * @param array $arguments The arguments passed to the method
     * @throws \BadMethodCallException If an unsupported method is called
     */
    public function __call(string $method, array $arguments): void
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

    /**
     * Add a route to the application
     *
     * @param string $method The HTTP method
     * @param string $path The route path
     * @param callable(Context): (ResponseInterface|null) $handler The route handler
     */
    private function addRoute(
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

    /**
     * Add middleware to the application
     *
     * @param callable $middleware The middleware function
     */
    public function use(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Add a nested route to the application
     *
     * @param string $prefix The prefix for the nested routes
     * @param self $nestedApp The nested Dumbo application
     */
    public function route(string $prefix, Dumbo $nestedApp): void
    {
        foreach ($nestedApp->routes as $route) {
            $this->routes[] = [
                "method" => $route["method"],
                "path" => $prefix . $route["path"],
                "handler" => $route["handler"],
            ];
        }
    }

    /**
     * Handle an incoming server request
     *
     * @param ServerRequestInterface $request The server request
     * @return ResponseInterface The response
     */
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

        return new Response(status: 404, body: "404 Not Found");
    }

    /**
     * Run the application
     */
    public function run(): void
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

    /**
     * Run the middleware stack and the final handler
     *
     * @param Context $context The request context
     * @param callable $handler The final handler to be executed after all middleware
     * @return ResponseInterface The response after running middleware and handler
     */
    private function runMiddleware(
        Context $context,
        callable $handler
    ): ResponseInterface {
        $next = function ($ctx) use ($handler) {
            $result = $handler($ctx);

            if ($result instanceof ResponseInterface) {
                return $result;
            } elseif ($result === null) {
                return $ctx->json(null); // not sure I need this tbh
            } else {
                return $ctx->json($result);
            }
        };

        foreach (array_reverse($this->middleware) as $middleware) {
            $next = function ($ctx) use ($middleware, $next) {
                $result = $middleware($ctx, $next);

                if ($result instanceof ResponseInterface) {
                    return $result;
                } elseif ($result === null) {
                    return $next($ctx);
                } else {
                    return $ctx->json($result);
                }
            };
        }

        return $next($context);
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

    /**
     * Send the response to the client
     *
     * @param ResponseInterface $response The response to send
     */
    private function send(ResponseInterface $response): void
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
