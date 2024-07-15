<?php
namespace Dumbo;

class Request
{
    private $params;
    private $query;
    private $body;
    private $headers;

    public function __construct($params, $query, $body, $headers)
    {
        $this->params = $params;
        $this->query = $query;
        $this->body = $body;
        $this->headers = $headers;
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
}
