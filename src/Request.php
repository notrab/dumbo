<?php
namespace Dumbo;

class Request
{
    private $method;
    private $params;
    private $query;
    private $body;
    private $headers;

    public function __construct($method, $params, $query, $body, $headers)
    {
        $this->method = strtoupper($method);
        $this->params = $params;
        $this->query = $query;
        $this->body = $body;
        $this->headers = array_change_key_case($headers, CASE_UPPER);
    }

    public function method()
    {
        return $this->method;
    }

    public function param($key)
    {
        return $this->params[$key] ?? null;
    }

    public function query($key = null)
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? null;
    }

    public function body()
    {
        return $this->body;
    }

    public function header($name)
    {
        $name = str_replace("-", "_", strtoupper($name));
        return $this->headers[$name] ?? null;
    }

    public function headers()
    {
        return $this->headers;
    }
}
