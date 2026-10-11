<?php

namespace Dumbo\Tests;

use Dumbo\Dumbo;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class WorkerTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testRunHandlesEveryRequestAFrankenPhpWorkerReceives()
    {
        $this->fakeFrankenPhp(["/users/1", "/users/2", "/missing"]);

        $app = new Dumbo();
        $served = 0;
        $app->get("/users/:id", function ($context) use (&$served) {
            $served++;

            return $context->text("{$context->req->param("id")}:$served");
        });

        ob_start();
        $app->run();

        $this->assertEquals("1:12:2404 Not Found", ob_get_clean());
    }

    #[RunInSeparateProcess]
    public function testRunInsideAWorkerRequestHandlesOnlyThatRequest()
    {
        $this->fakeFrankenPhp(["/a", "/b"]);

        $app = new Dumbo();
        $app->get("/:name", fn($context) => $context->text($context->req->param("name")));

        ob_start();
        while (frankenphp_handle_request(fn() => $app->run())) {
        }

        $this->assertEquals("ab", ob_get_clean());
    }

    /**
     * Stand in for FrankenPHP: boot as a worker, then hand each path to the
     * callback as a request, like frankenphp_handle_request() does
     */
    private function fakeFrankenPhp(array $paths): void
    {
        putenv("FRANKENPHP_WORKER=1");
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["REQUEST_URI"] = "";
        $GLOBALS["frankenphpRequests"] = $paths;

        if (!function_exists("frankenphp_handle_request")) {
            eval('
                function frankenphp_handle_request(callable $handler): bool
                {
                    $path = array_shift($GLOBALS["frankenphpRequests"]);

                    if ($path === null) {
                        return false;
                    }

                    $_SERVER["REQUEST_URI"] = $path;
                    $handler();

                    return true;
                }
            ');
        }
    }
}
