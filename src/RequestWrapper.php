<?php

namespace Dumbo;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface for the RequestWrapper class
 */
#[\Attribute]
interface RequestWrapperInterface
{
    public function param(string $name): ?string;
    public function queries(string $name): array|string;
    public function query(?string $name = null): array|string|null;
    public function body(): array;
    public function method(): string;
    public function headers(?string $name = null): array;
    public function header(string $name): ?string;
    public function path(): string;
    public function routePath(): string;
}

/**
 * RequestWrapper class for handling HTTP request details in the Dumbo framework
 */
class RequestWrapper implements RequestWrapperInterface
{
    /** @var array The parsed request body */
    private array $parsedBody;

    /**
     * @param ServerRequestInterface $request The server request object
     * @param array $params The route parameters extracted by FastRoute
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
        return $this->normalizeUri($this->request->getUri()->getPath());
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

        if (str_contains($contentType, "application/json")) {
            return json_decode($body, true) ?? [];
        }

        if (str_contains($contentType, "application/x-www-form-urlencoded")) {
            $data = [];

            parse_str($body, $data);

            return $data;
        }

        return $this->request->getParsedBody() ?? [];
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
    public function param(string $name): ?string
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Get query parameters as an array
     *
     * This method handles multiple values for the same query parameter.
     * If a parameter appears multiple times, all values are returned as an array.
     * If a parameter appears once, it's returned as a string.
     * If the parameter doesn't exist, an empty array is returned.
     *
     * @param string $name The name of the query parameter
     * @return array|string The query parameter value(s)
     */
    public function queries(string $name): array|string
    {
        $query = $this->request->getQueryParams();
        $value = $query[$name] ?? [];

        if (is_array($value)) {
            return empty($value) ? [] : $value;
        }

        return $value !== "" ? $value : [];
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

    public function getUploadedFiles(): array
    {
        return $this->request->getUploadedFiles();
    }

    /**
     * Normalize the given URI by ensuring it starts with a forward slash
     *
     * @param string $uri The URI to normalize
     * @return string The normalized URI
     */
    private function normalizeUri(string $uri): string
    {
        return "/" . trim($uri, "/");
    }
}
