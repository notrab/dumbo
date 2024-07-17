<?php

namespace Dumbo\Tests;

use Dumbo\Router;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testAddRoute(): void
    {
        $this->router->addRoute("GET", "/test", function () {});
        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals("GET", $routes[0]["method"]);
        $this->assertEquals("/test", $routes[0]["path"]);
        $this->assertIsCallable($routes[0]["handler"]);
    }

    public function testFindRoute(): void
    {
        $this->router->addRoute("GET", "/test/:id", function () {});

        $request = $this->createMockRequest("GET", "/test/123");

        $route = $this->router->findRoute($request);

        $this->assertNotNull($route);
        $this->assertIsCallable($route["handler"]);
        $this->assertEquals(["id" => "123"], $route["params"]);
        $this->assertEquals("/test/:id", $route["routePath"]);
    }

    public function testFindRouteNoMatch(): void
    {
        $this->router->addRoute("GET", "/test", function () {});

        $request = $this->createMockRequest("GET", "/non-existent");

        $route = $this->router->findRoute($request);

        $this->assertNull($route);
    }

    public function testSetPrefix(): void
    {
        $this->router->setPrefix("/api");
        $this->router->addRoute("GET", "/test", function () {});

        $routes = $this->router->getRoutes();

        $this->assertEquals("/api/test", $routes[0]["path"]);
    }

    /**
     * @return ServerRequestInterface
     */
    private function createMockRequest(
        string $method,
        string $path
    ): ServerRequestInterface {
        $request = $this->createMock(ServerRequestInterface::class);
        $uri = $this->createMock(UriInterface::class);

        $request->method("getMethod")->willReturn($method);
        $request->method("getUri")->willReturn($uri);
        $uri->method("getPath")->willReturn($path);

        return $request;
    }
}
