<?php

namespace Dumbo;

use Closure;
use Dumbo\Helpers\View;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Context class for handling request and response in the Dumbo framework
 *
 * This class encapsulates the request, response, and route information for each HTTP request.
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
     * @var Closure|null
     */
    private Closure|null $viewBuilder = null;

    /**
     * Context constructor
     *
     * @param ServerRequestInterface $request The server request object
     * @param array $params The route parameters extracted by FastRoute
     * @param string $routePath The registered route path
     */
    public function __construct(
        private ServerRequestInterface $request,
        private array $params,
        private string $routePath
    ) {
        $this->response = new Response();
        $this->req = new RequestWrapper($request, $params, $routePath);
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

    /**
     * Set the render closure for constructing views.
     *
     * @param Closure $closure The closure responsible for rendering the view.
     * @return void
     * @throws \RuntimeException If the render closure has already been set.
     */
    public function render(Closure $closure): void
    {
        if ($this->viewBuilder !== null) {
            throw new \RuntimeException("Render closure has already been set.");
        }

        $this->viewBuilder = $closure;
    }

    /**
     * Generate and return the view using the passed parameters.
     *
     * @param mixed ...$params The parameters to pass to the view renderer.
     * @return mixed The rendered view content.
     * @throws \RuntimeException If no render closure has been set.
     */
    public function view(...$params)
    {
        if ($this->viewBuilder === null) {
            throw new \RuntimeException("No render closure has been set.");
        }

        return $this->html(call_user_func_array($this->viewBuilder, $params));
    }
}
