<?php

namespace Dumbo;

class Response
{
    private $body;
    private $headers;

    public function __construct($body, $headers = [])
    {
        $this->body = $body;
        $this->headers = $headers;
    }

    public function send()
    {
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }
        echo $this->body;
    }
}
