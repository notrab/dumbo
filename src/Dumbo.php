<?php

namespace Dumbo;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Dumbo - A lightweight, friendly PHP framework for HTTP
 */
class Dumbo implements RequestHandlerInterface
{
    public const ENV_PRODUCTION = "production";
    public const ENV_DEVELOPMENT = "development";
    public const ENV_TESTING = "testing";

    private const METHODS = ["GET", "POST", "PUT", "PATCH", "DELETE", "HEAD", "OPTIONS"];

    private Router $router;
    private string $environment;

    /** @var array<callable> */
    private array $middleware = [];

    /** @var array<string, array<callable>> Middleware scoped to a path prefix */
    private array $groupMiddleware = [];

    /** @var callable|null */
    private $errorHandler = null;

    /** @var callable|null */
    private $notFoundHandler = null;

    /**
     * @param string|null $environment Defaults to the DUMBO_ENV variable, then development
     */
    public function __construct(?string $environment = null)
    {
        $this->router = new Router();
        $this->environment =
            $environment ??
            (($_SERVER["DUMBO_ENV"] ?? null) ?: getenv("DUMBO_ENV") ?: self::ENV_DEVELOPMENT);
    }

    public function get(string $path, callable $handler, array $middleware = []): static
    {
        return $this->on("GET", $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): static
    {
        return $this->on("POST", $path, $handler, $middleware);
    }

    public function put(string $path, callable $handler, array $middleware = []): static
    {
        return $this->on("PUT", $path, $handler, $middleware);
    }

    public function patch(string $path, callable $handler, array $middleware = []): static
    {
        return $this->on("PATCH", $path, $handler, $middleware);
    }

    public function delete(string $path, callable $handler, array $middleware = []): static
    {
        return $this->on("DELETE", $path, $handler, $middleware);
    }

    public function head(string $path, callable $handler, array $middleware = []): static
    {
        return $this->on("HEAD", $path, $handler, $middleware);
    }

    public function options(string $path, callable $handler, array $middleware = []): static
    {
        return $this->on("OPTIONS", $path, $handler, $middleware);
    }

    public function all(string $path, callable $handler, array $middleware = []): static
    {
        return $this->on(self::METHODS, $path, $handler, $middleware);
    }

    /**
     * Add a route for one or more methods
     *
     * @param string|array<string> $methods
     * @param array<callable|MiddlewareInterface> $middleware
     */
    public function on(string|array $methods, string $path, callable $handler, array $middleware = []): static
    {
        $middleware = array_map(self::toCallable(...), $middleware);

        foreach ((array) $methods as $method) {
            $this->router->addRoute(strtoupper($method), $path, $handler, $middleware);
        }

        return $this;
    }

    /**
     * Add middleware to every route, or only to routes under a path prefix.
     * Middleware is a callable($context, $next) or a PSR-15 middleware.
     *
     * @param string|callable|MiddlewareInterface $pathOrMiddleware A path prefix, or the middleware itself
     */
    public function use(
        string|callable|MiddlewareInterface $pathOrMiddleware,
        callable|MiddlewareInterface|null $middleware = null
    ): static {
        if (is_string($pathOrMiddleware) && $middleware !== null) {
            $this->groupMiddleware[self::normalizePrefix($pathOrMiddleware)][] = self::toCallable($middleware);
        } elseif (is_callable($pathOrMiddleware) || $pathOrMiddleware instanceof MiddlewareInterface) {
            $this->middleware[] = self::toCallable($pathOrMiddleware);
        } else {
            throw new \InvalidArgumentException("Invalid middleware configuration");
        }

        return $this;
    }

    /**
     * Mount another app's routes under a prefix. Its middleware runs for
     * every request under the prefix. Routes and middleware added to the
     * nested app after mounting are not included.
     */
    public function route(string $prefix, self $app): static
    {
        $prefix = self::normalizePrefix($prefix);

        foreach ($app->middleware as $middleware) {
            $this->groupMiddleware[$prefix][] = $middleware;
        }

        foreach ($app->groupMiddleware as $groupPrefix => $group) {
            foreach ($group as $middleware) {
                $this->groupMiddleware[$prefix . $groupPrefix][] = $middleware;
            }
        }

        foreach ($app->router->getRoutes() as $route) {
            $this->router->addRoute(
                $route["method"],
                $prefix . "/" . ltrim($route["path"], "/"),
                $route["handler"],
                $route["middleware"]
            );
        }

        return $this;
    }

    /**
     * Set a handler for uncaught exceptions, called with ($error, $context).
     * Responses it sends default to the HTTPException's status, or 500.
     */
    public function onError(callable $handler): static
    {
        $this->errorHandler = fn(\Throwable $error, Context $context) => $this->toResponse(
            $context->status($error instanceof HTTPException ? $error->getStatusCode() : 500),
            $handler($error, $context)
        );

        return $this;
    }

    /**
     * Set the handler for requests that match no route, called with ($context).
     * It runs after middleware, like a route handler, and its responses
     * default to a 404 status.
     */
    public function notFound(callable $handler): static
    {
        $this->notFoundHandler = fn(Context $context) => $this->toResponse(
            $context->status(404),
            $handler($context)
        );

        return $this;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function isDevelopment(): bool
    {
        return $this->environment === self::ENV_DEVELOPMENT;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $uri = $request->getUri();
        $path = $uri->getPath();

        if (strlen($path) > 1 && str_ends_with($path, "/")) {
            $location = (string) $uri->withPath(rtrim($path, "/"));

            // 308 keeps the method and body; clients turn a 301 POST into a GET
            $status = in_array($request->getMethod(), ["GET", "HEAD"], true) ? 301 : 308;

            return new Response($status, ["Location" => $location]);
        }

        try {
            ["route" => $route, "allowedMethods" => $allowed] = $this->router->dispatch($request);

            $context = $this->createContext($request, $route["params"] ?? [], $route["routePath"] ?? "");
            $context->set("environment", $this->environment);

            $handler = match (true) {
                $route !== null => $route["handler"],
                $allowed !== [] => fn() => new Response(
                    405,
                    ["Allow" => implode(", ", $allowed)],
                    "405 Method Not Allowed"
                ),
                default => fn(Context $context) => $context->notFound(),
            };

            return $this->runMiddleware(
                $context,
                $handler,
                array_merge(
                    $this->getMiddlewareForPath($context->req->path()),
                    $route["middleware"] ?? []
                )
            );
        } catch (\Throwable $error) {
            return $this->handleError($error, $request);
        }
    }

    /**
     * Send a request to the app without a server, e.g. in tests. Arrays and
     * objects are sent as a JSON body.
     */
    public function request(
        string $path,
        string $method = "GET",
        array $headers = [],
        mixed $body = null
    ): ResponseInterface {
        if (is_array($body) || is_object($body)) {
            $body = json_encode($body);
            $headers += ["Content-Type" => "application/json"];
        }

        $request = new ServerRequest($method, $path, $headers, $body);
        parse_str($request->getUri()->getQuery(), $query);

        return $this->handle($request->withQueryParams($query));
    }

    /**
     * Handle the request from PHP's globals and send the response
     */
    public function run(): void
    {
        try {
            $response = $this->handle(ServerRequest::fromGlobals());
        } catch (\Throwable $error) {
            $response = $this->errorResponse($error);
        }

        $this->send($response);
    }

    /**
     * Adapt a PSR-15 middleware to a Dumbo callable. A request it passes on,
     * e.g. with added attributes, replaces the context's request.
     */
    private static function toCallable(callable|MiddlewareInterface $middleware): callable
    {
        if (!$middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        return fn(Context $context, callable $next) => $middleware->process(
            $context->req->raw(),
            new class ($context, $next) implements RequestHandlerInterface {
                public function __construct(private Context $context, private \Closure $next)
                {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $this->context->req = $this->context->req->withRequest($request);

                    return ($this->next)($this->context);
                }
            }
        );
    }

    private function runMiddleware(Context $context, callable $handler, array $middleware): ResponseInterface
    {
        $next = fn(Context $context) => $this->toResponse($context, $handler($context));

        foreach (array_reverse($middleware) as $mw) {
            $next = function (Context $context) use ($mw, $next): ResponseInterface {
                $response = null;
                $result = $mw($context, function (Context $context) use ($next, &$response) {
                    return $response = $next($context);
                });

                // Returning nothing continues the chain, unless the middleware already did
                return $this->toResponse($context, $result ?? $response ?? $next($context));
            };
        }

        return $next($context);
    }

    /**
     * Turn whatever a handler returned into a response: null sends the
     * context's response and anything else is sent as JSON
     */
    private function toResponse(Context $context, mixed $result): ResponseInterface
    {
        return match (true) {
            $result instanceof ResponseInterface => $result,
            $result === null => $context->getResponse(),
            default => $context->json($result),
        };
    }

    private function send(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $i => $value) {
                header("$name: $value", $i === 0);
            }
        }

        $body = $response->getBody();
        $streaming = !$body->isSeekable();

        if ($streaming) {
            // Send output buffers on, stopping at one that can't be removed
            while (ob_get_level() > 0 && @ob_end_flush()) {
            }
        } else {
            $body->rewind();
        }

        while (!$body->eof()) {
            echo $body->read(8192);

            if ($streaming) {
                flush();
            }
        }
    }

    private function createContext(ServerRequestInterface $request, array $params, string $routePath): Context
    {
        return new Context($request, $params, $routePath, $this->notFoundHandler);
    }

    private function handleError(\Throwable $error, ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->createContext($request, [], "");

        if ($this->errorHandler) {
            return ($this->errorHandler)($error, $context);
        }

        if ($error instanceof HTTPException) {
            return $error->getCustomResponse() ??
                $context->json($error->toArray(), $error->getStatusCode());
        }

        return $this->errorResponse($error);
    }

    private function errorResponse(\Throwable $error): ResponseInterface
    {
        if ($this->isDevelopment()) {
            return new Response(500, ["Content-Type" => "text/html"], $this->renderErrorPage($error));
        }

        return new Response(
            500,
            ["Content-Type" => "application/json"],
            '{"error":"Internal Server Error"}'
        );
    }

    /**
     * @return array<callable> Global middleware plus any scoped to a prefix of $path
     */
    private function getMiddlewareForPath(string $path): array
    {
        $middleware = $this->middleware;

        foreach ($this->groupMiddleware as $prefix => $group) {
            if (self::coversPath($prefix, $path)) {
                $middleware = array_merge($middleware, $group);
            }
        }

        return $middleware;
    }

    /**
     * Whether a normalized middleware prefix covers a path: itself and
     * everything below it
     */
    private static function coversPath(string $prefix, string $path): bool
    {
        return $prefix === "" || $path === $prefix || str_starts_with($path, "$prefix/");
    }

    /**
     * "/admin", "admin/" and "/admin/*" all become "/admin"; "/" and "*" become ""
     */
    private static function normalizePrefix(string $prefix): string
    {
        $prefix = trim(preg_replace('#(^|/)\*$#', "", $prefix), "/");

        return $prefix === "" ? "" : "/$prefix";
    }

    private function renderErrorPage(\Throwable $error): string
    {
        $escape = fn(string $value) => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");

        $title = $escape(get_class($error));
        $message = $escape($error->getMessage());
        $file = $escape($error->getFile());
        $line = $error->getLine();
        $trace = $escape($error->getTraceAsString());

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error: {$title}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px; }
        h1 { color: #8A2BE2; }
        pre { background-color: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>{$title}</h1>
    <p><strong>Message:</strong> {$message}</p>
    <p><strong>File:</strong> {$file}</p>
    <p><strong>Line:</strong> {$line}</p>
    <h2>Stack Trace:</h2>
    <pre>{$trace}</pre>
</body>
</html>
HTML;
    }
}
