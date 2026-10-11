<?php

namespace Dumbo\Tests;

use Dumbo\Globals;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;

class GlobalsTest extends TestCase
{
    private array $globals;

    protected function setUp(): void
    {
        $this->globals = [$_SERVER, $_GET, $_POST, $_COOKIE, $_FILES];
        $_GET = $_POST = $_COOKIE = $_FILES = [];
    }

    protected function tearDown(): void
    {
        [$_SERVER, $_GET, $_POST, $_COOKIE, $_FILES] = $this->globals;
    }

    public function testBuildsTheRequestFromGlobals()
    {
        $_SERVER = [
            "REQUEST_METHOD" => "post",
            "REQUEST_URI" => "/users/42?tab=posts",
            "HTTP_HOST" => "example.com:8080",
            "HTTPS" => "on",
            "SERVER_PROTOCOL" => "HTTP/2",
            "HTTP_ACCEPT" => "application/json",
        ];
        $_GET = ["tab" => "posts"];
        $_POST = ["name" => "Dumbo"];
        $_COOKIE = ["session" => "abc"];

        $request = Globals::request();

        $this->assertEquals("POST", $request->getMethod());
        $this->assertEquals("https://example.com:8080/users/42?tab=posts", (string) $request->getUri());
        $this->assertEquals("2", $request->getProtocolVersion());
        $this->assertEquals("application/json", $request->getHeaderLine("Accept"));
        $this->assertEquals(["tab" => "posts"], $request->getQueryParams());
        $this->assertEquals(["name" => "Dumbo"], $request->getParsedBody());
        $this->assertEquals(["session" => "abc"], $request->getCookieParams());
        $this->assertEquals("", (string) $request->getBody());
    }

    public function testInvalidHostHeadersAreDropped()
    {
        $_SERVER = ["REQUEST_URI" => "/", "HTTP_HOST" => "evil.com/path", "SERVER_PORT" => "80"];

        $request = Globals::request();

        $this->assertEquals("localhost", $request->getUri()->getHost());
        $this->assertEquals("localhost", $request->getHeaderLine("Host"));
    }

    public function testFallsBackToTheServerNameAndPort()
    {
        $_SERVER = ["REQUEST_URI" => "/", "SERVER_NAME" => "server.local", "SERVER_PORT" => "8000", "QUERY_STRING" => "a=1"];

        $this->assertEquals("http://server.local:8000/?a=1", (string) Globals::request()->getUri());
    }

    public function testIpv6Hosts()
    {
        $_SERVER = ["REQUEST_URI" => "/", "HTTP_HOST" => "[::1]:8000"];

        $this->assertEquals("http://[::1]:8000/", (string) Globals::request()->getUri());
    }

    public function testUploadedFilesIncludingMultipleAndNested()
    {
        $_SERVER = ["REQUEST_URI" => "/"];
        $_FILES = [
            "avatar" => ["tmp_name" => "/tmp/a", "size" => 3, "error" => 0, "name" => "a.png", "type" => "image/png"],
            "docs" => [
                "tmp_name" => ["/tmp/b", "/tmp/c"],
                "size" => [1, 2],
                "error" => [0, 4],
                "name" => ["b.txt", "c.txt"],
                "type" => ["text/plain", "text/plain"],
            ],
            "profile" => ["photo" => ["tmp_name" => "/tmp/d", "size" => 4, "error" => 0, "name" => "d.jpg", "type" => "image/jpeg"]],
        ];

        $files = Globals::request()->getUploadedFiles();

        $this->assertInstanceOf(UploadedFileInterface::class, $files["avatar"]);
        $this->assertEquals("a.png", $files["avatar"]->getClientFilename());
        $this->assertEquals("c.txt", $files["docs"][1]->getClientFilename());
        $this->assertEquals(UPLOAD_ERR_NO_FILE, $files["docs"][1]->getError());
        $this->assertEquals("d.jpg", $files["profile"]["photo"]->getClientFilename());
    }
}
