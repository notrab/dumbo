<?php

namespace Dumbo;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Convenient access to the incoming request, available as $context->req
 */
class RequestWrapper
{
    private ?array $parsedBody = null;
    private ?array $cookies = null;

    /** @var array<string, mixed> Data stored by the validator middleware */
    private array $validated = [];

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
     * A copy wrapping a different request, keeping the route params and
     * validated data
     */
    public function withRequest(ServerRequestInterface $request): self
    {
        if ($request === $this->request) {
            return $this;
        }

        $clone = clone $this;
        $clone->request = $request;
        $clone->parsedBody = null;
        $clone->cookies = null;

        return $clone;
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
        return Router::normalize(LazyRequest::pathOf($this->request));
    }

    /**
     * The route path as registered, e.g. /posts/:id
     */
    public function routePath(): string
    {
        return $this->routePath;
    }

    /**
     * Get all route params, or a single one by name
     */
    public function param(?string $name = null): array|string|null
    {
        return $name === null ? $this->params : $this->params[$name] ?? null;
    }

    /**
     * Get the data the validator middleware stored for a target
     */
    public function valid(string $target): mixed
    {
        return $this->validated[$target] ?? null;
    }

    /**
     * Store validated data for a target, read back with valid()
     */
    public function setValid(string $target, mixed $data): void
    {
        $this->validated[$target] = $data;
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
     * The body parsed from JSON, or an empty array for other content types
     */
    public function json(): array
    {
        return $this->is("application/json") ? $this->body() : [];
    }

    /**
     * The form fields, or an empty array for other content types
     */
    public function form(): array
    {
        return $this->is("application/x-www-form-urlencoded") || $this->is("multipart/form-data")
            ? $this->body()
            : [];
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
        $this->cookies ??= self::parseCookies($this->header("Cookie") ?? "");

        return $name === null ? $this->cookies : $this->cookies[$name] ?? null;
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

    private static function parseCookies(string $header): array
    {
        $cookies = [];

        foreach (explode(";", $header) as $pair) {
            $parts = explode("=", trim($pair), 2);

            if (count($parts) === 2) {
                $cookies[urldecode(trim($parts[0]))] = urldecode($parts[1]);
            }
        }

        return $cookies;
    }

    private function is(string $mediaType): bool
    {
        return str_contains($this->request->getHeaderLine("Content-Type"), $mediaType);
    }

    private function parseBody(): array
    {
        if ($this->is("application/json")) {
            $decoded = json_decode($this->text(), true);

            return is_array($decoded) ? $decoded : [];
        }

        if ($this->is("application/x-www-form-urlencoded")) {
            parse_str($this->text(), $data);

            return $data;
        }

        return (array) ($this->request->getParsedBody() ?? []);
    }
}
