<?php

namespace Dumbo;

class Response
{
    private $body;
    private $headers;
    private $status;

    public function __construct($body, $headers = [], $status = 200)
    {
        $this->body = $body;
        $this->headers = $headers;
        $this->status = $status;
    }

    public function send()
    {
        http_response_code($this->status);
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }
        echo $this->body;
    }
}
