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

        $this->assertTrue(Cookie::hasCookie($this->context, "test_cookie"));
        $this->assertFalse(
            Cookie::hasCookie($this->context, "non_existent_cookie")
        );
    }

    public function testGetCookie()
    {
        $this->setRequestCookie("test_cookie=value; other_cookie=other_value");

        $this->assertEquals(
            "value",
            Cookie::getCookie($this->context, "test_cookie")
        );
        $this->assertNull(
            Cookie::getCookie($this->context, "non_existent_cookie")
        );
    }

    public function testSetCookie()
    {
        Cookie::setCookie($this->context, "test_cookie", "new_value");

        $setCookieHeaders = $this->context
            ->getResponse()
            ->getHeader("Set-Cookie");
        $this->assertCount(1, $setCookieHeaders);
        $this->assertStringContainsString(
            "test_cookie=new_value",
            $setCookieHeaders[0]
        );
    }

    public function testDeleteCookie()
    {
        $this->setRequestCookie("test_cookie=value; other_cookie=other_value");

        $deletedValue = Cookie::deleteCookie($this->context, "test_cookie");

        $this->assertEquals("value", $deletedValue);
        $setCookieHeaders = $this->context
            ->getResponse()
            ->getHeader("Set-Cookie");
        $this->assertCount(1, $setCookieHeaders);
        $this->assertStringContainsString("test_cookie=", $setCookieHeaders[0]);
        $this->assertStringContainsString("Expires=", $setCookieHeaders[0]);
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
            Cookie::getSignedCookie($this->context, $secret, "signed_cookie")
        );
    }

    public function testSetSignedCookie()
    {
        $secret = "test_secret";
        $name = "signed_cookie";
        $value = "test_value";

        Cookie::setSignedCookie($this->context, $name, $value, $secret);

        $setCookieHeaders = $this->context
            ->getResponse()
            ->getHeader("Set-Cookie");
        $this->assertCount(1, $setCookieHeaders);
        $this->assertStringContainsString("$name=", $setCookieHeaders[0]);
    }

    public function testRefreshCookie()
    {
        $this->setRequestCookie("test_cookie=old_value");

        $refreshed = Cookie::refreshCookie($this->context, "test_cookie");

        $this->assertTrue($refreshed);
        $setCookieHeaders = $this->context
            ->getResponse()
            ->getHeader("Set-Cookie");
        $this->assertCount(1, $setCookieHeaders);
        $this->assertStringContainsString(
            "test_cookie=old_value",
            $setCookieHeaders[0]
        );
    }

    public function testClearAllCookies()
    {
        $this->setRequestCookie("cookie1=value1; cookie2=value2");

        Cookie::clearAllCookies($this->context);

        $setCookieHeaders = $this->context
            ->getResponse()
            ->getHeader("Set-Cookie");
        $this->assertCount(2, $setCookieHeaders);
        $this->assertStringContainsString(
            "cookie1=; Expires=",
            $setCookieHeaders[0]
        );
        $this->assertStringContainsString(
            "cookie2=; Expires=",
            $setCookieHeaders[1]
        );
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
