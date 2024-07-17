<?php

namespace Dumbo;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;

/**
 * Context class for handling request and response in the Dumbo framework
 *
 * @package Dumbo
 * @author Jamie Barton
 * @version 1.0.0
 */
class Context
{
    /** @var RequestWrapper An object containing request-related methods */
    public RequestWrapper $req;

    /** @var ResponseInterface The response object */
    private ResponseInterface $response;

    /** @var array<string, mixed> Variables stored in the context */
    private $variables = [];

    /**
     * Context constructor
     *
     * @param ServerRequestInterface $request The server request object
     * @param array $params The route parameters
     * @param string $routePath The registered route path
     */
    public function __construct(
        private ServerRequestInterface $request,
        private array $params,
        private string $routePath
    ) {
        $this->response = new Response();
        $this->req = new class ($request, $params, $routePath) implements
            RequestWrapper
        {
            /** @var array The parsed request body */
            private array $parsedBody;

            /**
             * @param ServerRequestInterface $request The server request object
             * @param array $params The route parameters
             * @param string $routePath The registered route path
             */
            public function __construct(
                private ServerRequestInterface $request,
                private array $params,
                private string $routePath
            ) {
                $this->parsedBody = $this->parseBody();
            }

            /**
             * Get the current request path
             *
             * This method returns the path component of the request URI.
             * The path will be normalized to remove trailing slashes, except for the root path.
             *
             * @return string The current request path
             */
            public function path(): string
            {
                return $this->request->getUri()->getPath();
            }

            /**
             * Get the registered route path for the current request
             *
             * This method returns the original route path as it was registered,
             * including any path parameters (e.g., '/posts/:id').
             *
             * @return string The registered route path
             */
            public function routePath(): string
            {
                return $this->routePath;
            }

            /**
             * Parse the request body based on content type
             *
             * @return array The parsed body
             */
            private function parseBody(): array
            {
                $contentType = $this->request->getHeaderLine("Content-Type");
                $body = (string) $this->request->getBody();

                return match (true) {
                    str_contains($contentType, "application/json")
                        => json_decode($body, true) ?? [],
                    str_contains(
                        $contentType,
                        "application/x-www-form-urlencoded"
                    )
                        => $this->parseFormUrlEncoded($body),
                    default => $this->request->getParsedBody() ?? [],
                };
            }

            /**
             * Parse form URL encoded data
             *
             * @param string $body The raw request body
             * @return array The parsed data
             */
            private function parseFormUrlEncoded(string $body): array
            {
                $data = [];
                parse_str($body, $data);
                return $data;
            }

            /**
             * Get a route parameter
             *
             * @param string $name The parameter name
             * @return string|null The parameter value or null if not found
             */
            public function param($name): ?string
            {
                return $this->params[$name] ?? null;
            }

            /**
             * Get query parameters as an array
             *
             * If the parameter is a comma-separated string, it will be split into an array.
             * If the parameter is already an array, it will be returned as is.
             *
             * @param string $name The name of the query parameter
             * @return array The query parameter value(s) as an array
             */
            public function queries(string $name): array
            {
                $query = $this->request->getQueryParams();
                $value = $query[$name] ?? "";
                return is_array($value) ? $value : explode(",", $value);
            }

            /**
             * Get query parameters
             *
             * @param string|null $name The parameter name (optional)
             * @return array|string|null The query parameters or a specific parameter value
             */
            public function query(?string $name = null): array|string|null
            {
                $query = $this->request->getQueryParams();
                return $name === null ? $query : $query[$name] ?? null;
            }

            /**
             * Get the parsed request body
             *
             * @return array The parsed body
             */

            public function body(): array
            {
                return $this->parsedBody;
            }

            /**
             * Get the request method
             *
             * @return string The HTTP method
             */
            public function method(): string
            {
                return $this->request->getMethod();
            }

            /**
             * Get request headers
             *
             * @param string|null $name The header name (optional)
             * @return array The headers or a specific header value
             */
            public function headers(?string $name = null): array
            {
                return $name === null
                    ? $this->request->getHeaders()
                    : $this->request->getHeader($name);
            }

            /**
             * Get a single header value
             *
             * Returns the first value of the specified header. If the header has multiple
             * values, only the first one is returned. If the header doesn't exist, null is returned.
             *
             * @param string $name The name of the header
             * @return string|null The header value, or null if the header doesn't exist
             */
            public function header(string $name): ?string
            {
                $headers = $this->request->getHeader($name);
                return !empty($headers) ? $headers[0] : null;
            }
        };
    }

    /**
     * Set a variable in the context
     *
     * @param string $key The variable name
     * @param mixed $value The variable value
     */
    public function set(string $key, mixed $value): void
    {
        $this->variables[$key] = $value;
    }

    /**
     * Get a variable from the context
     *
     * @param string $key The variable name
     * @return mixed The variable value or null if not found
     */
    public function get(string $key): mixed
    {
        return $this->variables[$key] ?? null;
    }

    /**
     * Send a JSON response
     *
     * @param mixed $data The data to be JSON encoded (optional)
     * @param int $status The HTTP status code
     * @param array $headers Additional headers
     * @return ResponseInterface The response object
     */
    public function json(
        mixed $data = null,
        int $status = 200,
        array $headers = []
    ): ResponseInterface {
        $this->response = $this->response
            ->withStatus($status)
            ->withHeader("Content-Type", "application/json");

        foreach ($headers as $name => $value) {
            $this->response = $this->response->withHeader($name, $value);
        }

        $jsonData = $data !== null ? json_encode($data) : "null";
        $this->response->getBody()->write($jsonData);

        return $this->response;
    }

    /**
     * Send a plain text response
     *
     * @param string $data The response text
     * @param int $status The HTTP status code
     * @param array $headers Additional headers
     * @return ResponseInterface The response object
     */
    public function text(
        string $data,
        int $status = 200,
        array $headers = []
    ): ResponseInterface {
        $this->response = $this->response
            ->withStatus($status)
            ->withHeader("Content-Type", "text/plain");

        foreach ($headers as $name => $value) {
            $this->response = $this->response->withHeader($name, $value);
        }

        $this->response->getBody()->write($data);
        return $this->response;
    }

    /**
     * Send an HTML response
     *
     * @param string $data The HTML content
     * @param int $status The HTTP status code
     * @param array $headers Additional headers
     * @return ResponseInterface The response object
     */
    public function html(
        string $data,
        int $status = 200,
        array $headers = []
    ): ResponseInterface {
        $this->response = $this->response
            ->withStatus($status)
            ->withHeader("Content-Type", "text/html");

        foreach ($headers as $name => $value) {
            $this->response = $this->response->withHeader($name, $value);
        }

        $this->response->getBody()->write($data);
        return $this->response;
    }

    /**
     * Send a redirect response
     *
     * @param string $url The URL to redirect to
     * @param int $status The HTTP status code
     * @return ResponseInterface The response object
     */
    public function redirect(string $url, int $status = 302): ResponseInterface
    {
        return $this->response
            ->withStatus($status)
            ->withHeader("Location", $url);
    }

    /**
     * Add a header to the response
     *
     * @param string $name The header name
     * @param string $value The header value
     * @return self
     */
    public function header(string $name, string $value): self
    {
        $this->response = $this->response->withHeader($name, $value);
        return $this;
    }

    /**
     * Get the response object
     *
     * @return ResponseInterface The response object
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
