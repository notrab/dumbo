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

    /** @var array<callable> */
    private $middleware = [];

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
     * Add middleware to the application
     *
     * @param callable $middleware The middleware function
     */
    public function use(callable $middleware): void
    {
        $this->middleware[] = $middleware;
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
        foreach ($nestedApp->router->getRoutes() as $route) {
            $combinedMiddleware = array_merge(
                $this->middleware,
                $nestedApp->getMiddleware()
            );

            $this->router->addRoute(
                $route["method"],
                $prefix . $route["path"],
                $route["handler"],
                $combinedMiddleware
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
        try {
            $route = $this->router->findRoute($request);

            if ($route) {
                $context = new Context(
                    $request,
                    $route["params"],
                    $route["routePath"]
                );

                $combinedMiddleware = array_merge(
                    $this->middleware,
                    $route["middleware"] ?? []
                );

                $response = $this->runMiddleware(
                    $context,
                    $route["handler"],
                    $combinedMiddleware
                );

                return $response instanceof ResponseInterface
                    ? $response
                    : $context->getResponse();
            }

            return new Response(status: 404, body: "404 Not Found");
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
        $context = new Context($request, [], "");
        return $context->json(["error" => "Internal Server Error"], 500);

        if ($this->errorHandler) {
            $context = new Context($request, [], "");
            return call_user_func($this->errorHandler, $e, $context);
        }

        $context = new Context($request, [], "");
        return $context->json(["error" => "Internal Server Error"], 500);
    }
}
