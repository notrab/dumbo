<?php
namespace Dumbo;

class Context
{
    public $req;
    public $res;

    public function __construct($method, $params, $query, $body, $headers)
    {
        $this->req = new Request($method, $params, $query, $body, $headers);
        $this->res = new Response();
    }

    public function json($data, $status = 200)
    {
        return $this->res->status($status)->json($data);
    }

    public function text($data, $status = 200)
    {
        return $this->res->status($status)->text($data);
    }

    public function html($data, $status = 200)
    {
        return $this->res->status($status)->html($data);
    }

    public function status($code)
    {
        $this->res->status($code);
        return $this;
    }

    public function header($name, $value)
    {
        $this->res->header($name, $value);
        return $this;
    }

    public function getResponse()
    {
        return $this->res;
    }

    public function method()
    {
        return $this->req->method();
    }
}
