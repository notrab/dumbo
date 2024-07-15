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

    public function json($data)
    {
        return new Response(json_encode($data), [
            "Content-Type" => "application/json",
        ]);
    }

    public function text($data)
    {
        return new Response($data, ["Content-Type" => "text/plain"]);
    }
}
