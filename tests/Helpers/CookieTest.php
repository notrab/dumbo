<?php

namespace Dumbo\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use Dumbo\Helpers\Cookie;
use Dumbo\Context;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;

class CookieTest extends TestCase
{
    private $context;
    private $request;
    private $response;

    protected function setUp(): void
    {
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->response = new Response();
        $this->context = new TestContext($this->request, $this->response);
    }

    private function setRequestCookie(string $cookieString): void
    {
        $this->request
            ->method("getHeader")
            ->with("Cookie")
            ->willReturn([$cookieString]);
    }

    public function testHasCookie()
    {
        $this->setRequestCookie("test_cookie=value; other_cookie=other_value");

        $this->assertTrue(Cookie::has($this->context, "test_cookie"));
        $this->assertFalse(Cookie::has($this->context, "non_existent_cookie"));
    }

    public function testGet()
    {
        $this->setRequestCookie("test_cookie=value; other_cookie=other_value");

        $this->assertEquals(
            "value",
            Cookie::get($this->context, "test_cookie")
        );
        $this->assertNull(Cookie::get($this->context, "non_existent_cookie"));
    }

    public function testSetCookie()
    {
        Cookie::set($this->context, "test_cookie", "new_value");

        $setHeaders = $this->context->getResponse()->getHeader("Set-Cookie");
        $this->assertCount(1, $setHeaders);
        $this->assertStringContainsString(
            "test_cookie=new_value",
            $setHeaders[0]
        );
    }

    public function testDeleteCookie()
    {
        $this->setRequestCookie("test_cookie=value; other_cookie=other_value");

        $deletedValue = Cookie::delete($this->context, "test_cookie");

        $this->assertEquals("value", $deletedValue);
        $setHeaders = $this->context->getResponse()->getHeader("Set-Cookie");
        $this->assertCount(1, $setHeaders);
        $this->assertStringContainsString("test_cookie=", $setHeaders[0]);
        $this->assertStringContainsString("Expires=", $setHeaders[0]);
    }

    public function testGetSignedCookie()
    {
        $secret = "test_secret";
        $value = "test_value";
        $signature = hash_hmac("sha256", $value, $secret);
        $signedValue = $value . "." . $signature;

        $this->setRequestCookie("signed_cookie=$signedValue");

        $this->assertEquals(
            $value,
            Cookie::getSigned($this->context, $secret, "signed_cookie")
        );
    }

    public function testSetSignedCookie()
    {
        $secret = "test_secret";
        $name = "signed_cookie";
        $value = "test_value";

        Cookie::setSigned($this->context, $name, $value, $secret);

        $setHeaders = $this->context->getResponse()->getHeader("Set-Cookie");
        $this->assertCount(1, $setHeaders);
        $this->assertStringContainsString("$name=", $setHeaders[0]);
    }

    public function testRefreshCookie()
    {
        $this->setRequestCookie("test_cookie=old_value");

        $refreshed = Cookie::refresh($this->context, "test_cookie");

        $this->assertTrue($refreshed);
        $setHeaders = $this->context->getResponse()->getHeader("Set-Cookie");
        $this->assertCount(1, $setHeaders);
        $this->assertStringContainsString(
            "test_cookie=old_value",
            $setHeaders[0]
        );
    }

    public function testClearAllCookies()
    {
        $this->setRequestCookie("cookie1=value1; cookie2=value2");

        Cookie::clearAll($this->context);

        $setHeaders = $this->context->getResponse()->getHeader("Set-Cookie");
        $this->assertCount(2, $setHeaders);
        $this->assertStringContainsString("cookie1=; Expires=", $setHeaders[0]);
        $this->assertStringContainsString("cookie2=; Expires=", $setHeaders[1]);
    }
}

class TestContext extends Context
{
    private $response;

    public function __construct(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        parent::__construct($request, [], "");
        $this->response = $response;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function header(string $name, string $value): self
    {
        $this->response = $this->response->withAddedHeader($name, $value);
        return $this;
    }
}
