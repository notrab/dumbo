<?php

use PHPUnit\Framework\TestCase;
use Dumbo\Context;
use Dumbo\Dumbo;
use GuzzleHttp\Psr7\ServerRequest;

class DumboTest extends TestCase
{
    public function testEnvironmentDetection()
    {
        $testCases = [
            // Test case: No environment set
            [
                "serverVars" => [],
                "getenvFunc" => function () {
                    return false;
                },
                "expectedEnv" => Dumbo::ENV_DEVELOPMENT,
            ],
            // Test case: Environment set in $_SERVER
            [
                "serverVars" => ["DUMBO_ENV" => "production"],
                "getenvFunc" => function () {
                    return false;
                },
                "expectedEnv" => Dumbo::ENV_PRODUCTION,
            ],
            // Test case: Environment set via getenv
            [
                "serverVars" => [],
                "getenvFunc" => function () {
                    return "testing";
                },
                "expectedEnv" => Dumbo::ENV_TESTING,
            ],
            // Test case: Invalid environment defaults to development
            [
                "serverVars" => ["DUMBO_ENV" => "invalid"],
                "getenvFunc" => function () {
                    return false;
                },
                "expectedEnv" => Dumbo::ENV_DEVELOPMENT,
            ],
            // Test case: $_SERVER takes precedence over getenv
            [
                "serverVars" => ["DUMBO_ENV" => "production"],
                "getenvFunc" => function () {
                    return "development";
                },
                "expectedEnv" => Dumbo::ENV_PRODUCTION,
            ],
        ];

        foreach ($testCases as $index => $testCase) {
            $app = new Dumbo();
            $app->detectEnvironment(
                $testCase["serverVars"],
                $testCase["getenvFunc"]
            );

            $this->assertEquals(
                $testCase["expectedEnv"],
                $app->getEnvironment(),
                "Failed assertion for test case $index"
            );
        }
    }

    public function testErrorReportingConfiguration()
    {
        $app = new Dumbo();
        $app->detectEnvironment(["DUMBO_ENV" => "production"]);
        $this->assertEquals(0, error_reporting());
        $this->assertEquals("0", ini_get("display_errors"));

        $app = new Dumbo();
        $app->detectEnvironment(["DUMBO_ENV" => "development"]);
        $this->assertEquals(E_ALL, error_reporting());
        $this->assertEquals("1", ini_get("display_errors"));
    }

    public function testMethodNotAllowedReturns405WithAllowHeader()
    {
        $app = new Dumbo();
        $app->get("/users", fn(Context $context) => $context->text("users"));
        $app->post("/users", fn(Context $context) => $context->text("created"));

        $response = $app->handle(new ServerRequest("DELETE", "/users"));

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals("GET, POST", $response->getHeaderLine("Allow"));
        $this->assertEquals(
            "405 Method Not Allowed",
            (string) $response->getBody()
        );
    }

    public function testUnknownPathStillReturns404()
    {
        $app = new Dumbo();
        $app->get("/users", fn(Context $context) => $context->text("users"));

        $response = $app->handle(new ServerRequest("GET", "/nope"));

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertFalse($response->hasHeader("Allow"));
        $this->assertEquals("404 Not Found", (string) $response->getBody());
    }

    public function testHeadRequestFallsBackToGetRoute()
    {
        $app = new Dumbo();
        $app->get("/users", fn(Context $context) => $context->text("users"));

        $response = $app->handle(new ServerRequest("HEAD", "/users"));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testMiddlewareRunsForMethodNotAllowed()
    {
        $app = new Dumbo();
        $app->use(
            fn(Context $context, callable $next) => $next($context)->withHeader(
                "X-Middleware",
                "ran"
            )
        );
        $app->get("/users", fn(Context $context) => $context->text("users"));

        $response = $app->handle(new ServerRequest("DELETE", "/users"));

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals("ran", $response->getHeaderLine("X-Middleware"));
    }
}
