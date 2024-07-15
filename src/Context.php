<?php

namespace Dumbo;

class Context
{
    private $params;

    public function __construct($params = [])
    {
        $this->params = $params;
    }

    public function param($name)
    {
        return $this->params[$name] ?? null;
    }

    public function json($data, $status = 200, $headers = [])
    {
        $headers = array_merge(
            [
                "Content-Type" => "application/json",
            ],
            $headers
        );
        return new Response(json_encode($data), $headers, $status);
    }

    public function text($data, $status = 200, $headers = [])
    {
        $headers = array_merge(
            [
                "Content-Type" => "text/plain",
            ],
            $headers
        );
        return new Response($data, $headers, $status);
    }

    public function html($data, $status = 200, $headers = [])
    {
        $headers = array_merge(
            [
                "Content-Type" => "text/html",
            ],
            $headers
        );
        return new Response($data, $headers, $status);
    }

    public function body($data, $status = 200, $headers = [])
    {
        return new Response($data, $headers, $status);
    }
}
