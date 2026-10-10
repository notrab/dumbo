<?php

namespace Dumbo;

use Closure;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * The request, response and shared variables for a single HTTP request
 */
class Context
{
    public RequestWrapper $req;

    private ResponseInterface $response;

    /** @var array<string, mixed> */
    private array $variables = [];

    private ?Closure $viewBuilder = null;

    /**
     * @param array $params The route parameters
     * @param string $routePath The registered route path, e.g. /posts/:id
     */
    public function __construct(ServerRequestInterface $request, array $params, string $routePath)
    {
        $this->response = new Response();
        $this->req = new RequestWrapper($request, $params, $routePath);
    }

    public function set(string $key, mixed $value): void
    {
        $this->variables[$key] = $value;
    }

    public function get(string $key): mixed
    {
        return $this->variables[$key] ?? null;
    }

    public function json(mixed $data = null, int $status = 200, array $headers = []): ResponseInterface
    {
        return $this->send($data, "application/json", $status, $headers);
    }

    public function text(string $text, int $status = 200, array $headers = []): ResponseInterface
    {
        return $this->send($text, "text/plain", $status, $headers);
    }

    public function html(string $html, int $status = 200, array $headers = []): ResponseInterface
    {
        return $this->send($html, "text/html", $status, $headers);
    }

    public function redirect(string $url, int $status = 302): ResponseInterface
    {
        return $this->response = $this->response
            ->withStatus($status)
            ->withHeader("Location", $url);
    }

    /**
     * Set a response header
     *
     * @param bool $append Add to any existing values instead of replacing
     *                     them, for headers sent more than once
     */
    public function header(string $name, string $value, bool $append = false): self
    {
        $this->response = $append
            ? $this->response->withAddedHeader($name, $value)
            : $this->response->withHeader($name, $value);

        return $this;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Set the closure that view() uses to render templates
     *
     * @throws \RuntimeException If a render closure has already been set
     */
    public function render(Closure $closure): void
    {
        if ($this->viewBuilder !== null) {
            throw new \RuntimeException("Render closure has already been set.");
        }

        $this->viewBuilder = $closure;
    }

    /**
     * Render a view with the closure given to render() and send it as HTML
     *
     * @throws \RuntimeException If no render closure has been set
     */
    public function view(mixed ...$params): ResponseInterface
    {
        if ($this->viewBuilder === null) {
            throw new \RuntimeException("No render closure has been set.");
        }

        return $this->html(($this->viewBuilder)(...$params));
    }

    /**
     * Send a response with any body and content type. Arrays and objects are
     * JSON encoded and streams are used as-is. The body replaces anything
     * sent earlier in the request.
     */
    public function send(
        mixed $body = "",
        string $contentType = "text/plain",
        int $status = 200,
        array $headers = []
    ): ResponseInterface {
        $this->response = $this->response
            ->withStatus($status)
            ->withHeader("Content-Type", $contentType);

        foreach ($headers as $name => $value) {
            $this->response = $this->response->withHeader($name, $value);
        }

        $body = match (true) {
            $body instanceof StreamInterface => $body,
            is_string($body) || is_numeric($body) => (string) $body,
            is_array($body) || is_object($body) => (string) json_encode($body),
            default => "",
        };

        return $this->response = $this->response->withBody(Utils::streamFor($body));
    }
}
