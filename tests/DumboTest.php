<?php

namespace Dumbo\Tests;

use PHPUnit\Framework\TestCase;
use Dumbo\Dumbo;
use Dumbo\Context;
use Dumbo\Response;

class DumboTest extends TestCase
{
    public function testRouteRegistration()
    {
        $app = new Dumbo();

        $app->get("/", function ($c) {
            return $c->text("Hello World");
        });

        $this->assertCount(1, $this->getPrivateProperty($app, "routes"));
        $route = $this->getPrivateProperty($app, "routes")[0];
        $this->assertEquals("GET", $route["method"]);
        $this->assertEquals("/", $route["path"]);
        $this->assertIsCallable($route["handler"]);
    }

    public function testResponseCreation()
    {
        $context = new Context();

        $textResponse = $context->text("Hello World");
        $this->assertInstanceOf(Response::class, $textResponse);
        $this->assertEquals(
            "Hello World",
            $this->getPrivateProperty($textResponse, "body")
        );

        $jsonResponse = $context->json(["message" => "Hello World"]);
        $this->assertInstanceOf(Response::class, $jsonResponse);
        $this->assertEquals(
            '{"message":"Hello World"}',
            $this->getPrivateProperty($jsonResponse, "body")
        );
    }

    private function getPrivateProperty($object, $propertyName)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}
