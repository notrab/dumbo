<?php

namespace Dumbo;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Convenient access to the incoming request, available as $context->req
 */
class RequestWrapper
{
    private ?array $parsedBody = null;

    /**
     * @param array $params The route parameters
     * @param string $routePath The registered route path, e.g. /posts/:id
     */
    public function __construct(
        private ServerRequestInterface $request,
        private array $params,
        private string $routePath
    ) {
    }

    /**
     * The underlying PSR-7 request
     */
    public function raw(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * A copy wrapping a different request, keeping the route params
     */
    public function withRequest(ServerRequestInterface $request): self
    {
        return $request === $this->request
            ? $this
            : new self($request, $this->params, $this->routePath);
    }

    /**
     * The full request URL
     */
    public function url(): string
    {
        return (string) $this->request->getUri();
    }

    /**
     * The request path, without a trailing slash
     */
    public function path(): string
    {
        return Router::normalize($this->request->getUri()->getPath());
    }

    /**
     * The route path as registered, e.g. /posts/:id
     */
    public function routePath(): string
    {
        return $this->routePath;
    }

    public function param(string $name): ?string
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Get every value of a query parameter: an array when it was given more
     * than once (e.g. ?tag[]=a&tag[]=b), a string when given once, or an
     * empty array when missing.
     */
    public function queries(string $name): array|string
    {
        $value = $this->request->getQueryParams()[$name] ?? [];

        return $value === "" ? [] : $value;
    }

    /**
     * Get all query parameters, or a single one by name
     */
    public function query(?string $name = null): array|string|null
    {
        $query = $this->request->getQueryParams();

        return $name === null ? $query : $query[$name] ?? null;
    }

    /**
     * The request body, parsed from JSON or form data
     */
    public function body(): array
    {
        return $this->parsedBody ??= $this->parseBody();
    }

    /**
     * The raw request body
     */
    public function text(): string
    {
        return (string) $this->request->getBody();
    }

    /**
     * Get all cookies, or a single one by name
     */
    public function cookie(?string $name = null): array|string|null
    {
        $cookies = [];

        foreach (explode(";", $this->header("Cookie") ?? "") as $pair) {
            $parts = explode("=", trim($pair), 2);

            if (count($parts) === 2) {
                $cookies[urldecode(trim($parts[0]))] = urldecode($parts[1]);
            }
        }

        return $name === null ? $cookies : $cookies[$name] ?? null;
    }

    public function method(): string
    {
        return $this->request->getMethod();
    }

    /**
     * Get all headers, or every value of a single header by name
     */
    public function headers(?string $name = null): array
    {
        return $name === null
            ? $this->request->getHeaders()
            : $this->request->getHeader($name);
    }

    /**
     * Get the first value of a header, or null when missing
     */
    public function header(string $name): ?string
    {
        return $this->request->getHeader($name)[0] ?? null;
    }

    public function getUploadedFiles(): array
    {
        return $this->request->getUploadedFiles();
    }

    private function parseBody(): array
    {
        $contentType = $this->request->getHeaderLine("Content-Type");
        $body = $this->text();

        if (str_contains($contentType, "application/json")) {
            $decoded = json_decode($body, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (str_contains($contentType, "application/x-www-form-urlencoded")) {
            parse_str($body, $data);

            return $data;
        }

        return (array) ($this->request->getParsedBody() ?? []);
    }
}
