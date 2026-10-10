<?php

namespace Dumbo\Tests\Middleware;

use Dumbo\Context;
use Dumbo\Dumbo;
use Dumbo\Middleware\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private function app(string $target, callable $validate): Dumbo
    {
        return (new Dumbo())->on(
            ["GET", "POST"],
            "/users/:id",
            fn(Context $c) => $c->json(["valid" => $c->req->valid($target)]),
            [Validator::validator($target, $validate)]
        );
    }

    private function valid($response): mixed
    {
        return json_decode((string) $response->getBody(), true)["valid"];
    }

    public function testValidDataIsAvailableToTheHandler()
    {
        $app = $this->app("json", fn(array $value) => ["name" => strtoupper($value["name"])]);

        $response = $app->request("/users/1", "POST", [], ["name" => "jamie"]);

        $this->assertSame(["name" => "JAMIE"], $this->valid($response));
    }

    public function testReturningAResponseRejectsTheRequest()
    {
        $app = $this->app("json", fn(array $value, Context $c) => empty($value["name"])
            ? $c->json(["error" => "name is required"], 400)
            : $value);

        $response = $app->request("/users/1", "POST", [], ["email" => "a@b.c"]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('{"error":"name is required"}', (string) $response->getBody());
    }

    public function testJsonTargetIgnoresOtherContentTypes()
    {
        $seen = null;
        $app = $this->app("json", function (array $value) use (&$seen) {
            return $seen = $value;
        });

        $app->request("/users/1", "POST", ["Content-Type" => "application/x-www-form-urlencoded"], "name=jamie");

        $this->assertSame([], $seen);
    }

    public function testFormTarget()
    {
        $app = $this->app("form", fn(array $value) => $value);

        $response = $app->request(
            "/users/1",
            "POST",
            ["Content-Type" => "application/x-www-form-urlencoded"],
            "name=jamie"
        );

        $this->assertSame(["name" => "jamie"], $this->valid($response));
    }

    public function testQueryParamHeaderAndCookieTargets()
    {
        $headers = ["X-Api-Key" => "secret", "Cookie" => "theme=dark"];

        $this->assertSame(["page" => "2"], $this->valid(
            $this->app("query", fn($v) => $v)->request("/users/1?page=2")
        ));
        $this->assertSame(["id" => "1"], $this->valid(
            $this->app("param", fn($v) => $v)->request("/users/1")
        ));
        $this->assertSame("secret", $this->valid(
            $this->app("header", fn($v) => $v["x-api-key"])->request("/users/1", "GET", $headers)
        ));
        $this->assertSame(["theme" => "dark"], $this->valid(
            $this->app("cookie", fn($v) => $v)->request("/users/1", "GET", $headers)
        ));
    }

    public function testUnknownTargetThrows()
    {
        $this->expectException(\InvalidArgumentException::class);

        Validator::validator("body", fn($v) => $v);
    }
}
