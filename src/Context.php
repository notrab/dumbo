<?php

namespace Dumbo;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;

class Context
{
    public $req;
    private $response;
    private $variables = [];

    public function __construct(ServerRequestInterface $request, array $params)
    {
        $this->request = $request;
        $this->response = new Response();
        $this->req = new class ($request, $params) {
            private $request;
            private $params;
            private $parsedBody;

            public function __construct($request, $params)
            {
                $this->request = $request;
                $this->params = $params;
                $this->parsedBody = $this->parseBody();
            }

            private function parseBody()
            {
                $contentType = $this->request->getHeaderLine("Content-Type");
                $body = (string) $this->request->getBody();

                if (strpos($contentType, "application/json") !== false) {
                    return json_decode($body, true) ?? [];
                }

                if (
                    strpos(
                        $contentType,
                        "application/x-www-form-urlencoded"
                    ) !== false
                ) {
                    parse_str($body, $data);
                    return $data;
                }

                return $this->request->getParsedBody() ?? [];
            }

            public function param($name)
            {
                return $this->params[$name] ?? null;
            }

            public function query($name = null)
            {
                $query = $this->request->getQueryParams();
                return $name === null ? $query : $query[$name] ?? null;
            }

            public function body()
            {
                return $this->parsedBody;
            }

            public function method()
            {
                return $this->request->getMethod();
            }

            public function headers($name = null)
            {
                if ($name === null) {
                    return $this->request->getHeaders();
                }
                return $this->request->getHeader($name);
            }
        };
    }

    public function set($key, $value)
    {
        $this->variables[$key] = $value;
    }

    public function get($key)
    {
        return $this->variables[$key] ?? null;
    }

    public function json($data, $status = 200, $headers = []): ResponseInterface
    {
        $this->response = $this->response
            ->withStatus($status)
            ->withHeader("Content-Type", "application/json");
        foreach ($headers as $name => $value) {
            $this->response = $this->response->withHeader($name, $value);
        }
        $this->response->getBody()->write(json_encode($data));
        return $this->response;
    }

    public function text($data, $status = 200, $headers = []): ResponseInterface
    {
        $this->response = $this->response
            ->withStatus($status)
            ->withHeader("Content-Type", "text/plain");
        foreach ($headers as $name => $value) {
            $this->response = $this->response->withHeader($name, $value);
        }
        $this->response->getBody()->write($data);
        return $this->response;
    }

    public function html($data, $status = 200, $headers = []): ResponseInterface
    {
        $this->response = $this->response
            ->withStatus($status)
            ->withHeader("Content-Type", "text/html");
        foreach ($headers as $name => $value) {
            $this->response = $this->response->withHeader($name, $value);
        }
        $this->response->getBody()->write($data);
        return $this->response;
    }

    public function redirect($url, $status = 302): ResponseInterface
    {
        return $this->response
            ->withStatus($status)
            ->withHeader("Location", $url);
    }

    public function header($name, $value): self
    {
        $this->response = $this->response->withHeader($name, $value);
        return $this;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
