<?php

namespace Dumbo;

class Response
{
    private $body;
    private $headers = [];
    private $statusCode = 200;

    public function status($code)
    {
        $this->statusCode = $code;

        return $this;
    }

    public function header($name, $value)
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function json($data)
    {
        $this->header("Content-Type", "application/json");
        $this->body = json_encode($data);

        return $this;
    }

    public function text($data)
    {
        $this->header("Content-Type", "text/plain");
        $this->body = $data;

        return $this;
    }

    public function html($data)
    {
        $this->header("Content-Type", "text/html");
        $this->body = $data;

        return $this;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function redirect($url, $status = 302)
    {
        $this->statusCode = $status;
        $this->header("Location", $url);

        return $this;
    }
}
