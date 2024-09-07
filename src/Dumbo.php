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
    private Router $router;
    private bool $removeTrailingSlash = true;

    /** @var array<callable> */
    private $middleware = [];

    /**
     * @var array<string, array<callable>> Middleware specific to route groups
     */
    private $groupMiddleware = [];

    private ?Dumbo $parent = null;

    private $errorHandler;

    public function __construct()
    {
        $this->router = new Router();
    }

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
            $this->router->addRoute(strtoupper($method), ...$arguments);
        } else {
            throw new \BadMethodCallException("METHOD $method does not exist.");
        }
    }

    /**
     * Add middleware to the application or a specific route group
     *
     * @param string|callable $pathOrMiddleware The path for group-specific middleware or the middleware function
     * @param callable|null $middleware The middleware function for group-specific middleware
     * @throws \InvalidArgumentException If the middleware configuration is invalid
     */
    public function use($pathOrMiddleware, $middleware = null): void
    {
        if (is_callable($pathOrMiddleware)) {
            $this->middleware[] = $pathOrMiddleware;
        } elseif (is_string($pathOrMiddleware) && is_callable($middleware)) {
            $this->groupMiddleware[$pathOrMiddleware][] = $middleware;
        } else {
            throw new \InvalidArgumentException(
                "Invalid middleware configuration"
            );
        }
    }

    /**
     * Get the middleware stack
     *
     * @return array The middleware stack
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Add a nested route to the application
     *
     * @param string $prefix The prefix for the nested routes
     * @param self $nestedApp The nested Dumbo application
     */
    public function route(string $prefix, self $nestedApp): void
    {
        $nestedApp->parent = $this;

        foreach ($nestedApp->router->getRoutes() as $route) {
            $fullPath = rtrim($prefix, "/") . "/" . ltrim($route["path"], "/");
            $this->router->addRoute(
                $route["method"],
                $fullPath,
                $route["handler"],
                array_merge(
                    $this->middleware,
                    $nestedApp->getMiddleware(),
                    $route["middleware"] ?? []
                )
            );
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
        if ($this->removeTrailingSlash) {
            $uri = $request->getUri();
            $path = $uri->getPath();

            if (strlen($path) > 1 && substr($path, -1) === "/") {
                $newPath = rtrim($path, "/");
                $newUri = $uri->withPath($newPath);

                return new Response(301, ["Location" => (string) $newUri]);
            }
        }

        try {
            $route = $this->router->findRoute($request);

            $context = new Context(
                $request,
                $route ? $route["params"] : [],
                $route ? $route["routePath"] : ""
            );

            $fullMiddlewareStack = array_merge(
                $this->getMiddlewareForPath($context->req->path()),
                $route ? $route["middleware"] : []
            );

            if ($route) {
                $fullMiddlewareStack = array_unique(
                    array_merge(
                        $fullMiddlewareStack,
                        $route["middleware"] ?? []
                    ),
                    SORT_REGULAR
                );

                $handler = $route["handler"];
            } else {
                $handler = function () {
                    return new Response(404, [], "404 Not Found");
                };
            }

            $response = $this->runMiddleware(
                $context,
                $handler,
                $fullMiddlewareStack
            );

            return $response instanceof ResponseInterface
                ? $response
                : $context->getResponse();
        } catch (HTTPException $e) {
            return $this->handleHTTPException($e, $request);
        } catch (\Exception $e) {
            return $this->handleGenericException($e, $request);
        }
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

    /**
     * Create a server request from the PHP globals
     *
     * @return ServerRequestInterface The server request
     */
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
     * @param array<callable> $middleware The middleware stack
     * @return ResponseInterface The response after running middleware and handler
     */
    private function runMiddleware(
        Context $context,
        callable $handler,
        array $middleware
    ): ResponseInterface {
        $next = function ($context) use ($handler) {
            $result = $handler($context);

            if ($result instanceof ResponseInterface) {
                return $result;
            } elseif ($result === null) {
                return $context->getResponse();
            } else {
                return $context->json($result);
            }
        };

        foreach (array_reverse($middleware) as $mw) {
            $next = function ($context) use ($mw, $next) {
                $result = $mw($context, $next);

                if ($result instanceof ResponseInterface) {
                    return $result;
                } elseif ($result === null) {
                    return $next($context);
                } else {
                    return $context->json($result);
                }
            };
        }

        return $next($context);
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

    /**
     * Set a custom error handler
     *
     * @param callable $handler The custom error handler function
     */
    public function onError(callable $handler): void
    {
        $this->errorHandler = $handler;
    }

    /**
     * Handle HTTPException
     *
     * @param HTTPException $e The caught HTTPException
     * @param ServerRequestInterface $request The original request
     * @return ResponseInterface The response
     */
    private function handleHTTPException(
        HTTPException $e,
        ServerRequestInterface $request
    ): ResponseInterface {
        if ($this->errorHandler) {
            $context = new Context($request, [], "");
            return call_user_func($this->errorHandler, $e, $context);
        }

        $customResponse = $e->getCustomResponse();
        if ($customResponse) {
            return $customResponse;
        }

        $context = new Context($request, [], "");
        return $context->json($e->toArray(), $e->getStatusCode());
    }

    /**
     * Handle generic exceptions
     *
     * @param \Exception $e The caught exception
     * @param ServerRequestInterface $request The original request
     * @return ResponseInterface The response
     */
    private function handleGenericException(
        \Exception $e,
        ServerRequestInterface $request
    ): ResponseInterface {
        if ($this->errorHandler) {
            $context = new Context($request, [], "");
            return call_user_func($this->errorHandler, $e, $context);
        }

        $context = new Context($request, [], "");
        return $context->json(["error" => "Internal Server Error"], 500);
    }

    private function getFullMiddlewareStack(): array
    {
        $stack = $this->middleware;
        $current = $this;

        while ($current->parent !== null) {
            $stack = array_merge($current->parent->middleware, $stack);
            $current = $current->parent;
        }

        return $stack;
    }

    /**
     * Get all applicable middleware for a given path
     *
     * @param string $path The request path
     * @return array<callable> Array of middleware applicable to the given path
     */
    private function getMiddlewareForPath(string $path): array
    {
        $applicableMiddleware = $this->middleware;

        foreach ($this->groupMiddleware as $groupPath => $groupMiddlewares) {
            if (strpos($path, $groupPath) === 0) {
                $applicableMiddleware = array_merge(
                    $applicableMiddleware,
                    $groupMiddlewares
                );
            }
        }

        return $applicableMiddleware;
    }
}
