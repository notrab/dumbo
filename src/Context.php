<?php
namespace Dumbo;

class Context
{
    public $req;
    public $res;
    private $variables;

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

    public function set(string $key, $value): void
    {
        $this->variables[$key] = $value;
    }

    public function get(string $key)
    {
        return $this->variables[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->variables[$key]);
    }

    public function redirect($url, $status = 302)
    {
        return $this->res->redirect($url, $status);
    }
}
