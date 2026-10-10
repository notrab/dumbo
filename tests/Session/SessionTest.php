<?php

namespace Dumbo\Tests\Session;

use Dumbo\Context;
use Dumbo\Dumbo;
use Dumbo\Middleware\Session;
use Dumbo\Session\CookieStore;
use Dumbo\Session\FileStore;
use Dumbo\Session\MemoryStore;
use Dumbo\Session\Store;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class SessionTest extends TestCase
{
    private Dumbo $app;

    private Store $store;

    /** @var array<string, string> Cookies kept between requests, like a browser */
    private array $jar = [];

    protected function setUp(): void
    {
        $this->store = new MemoryStore();
        $this->app = $this->app($this->store);
    }

    private function app(Store $store, array $options = []): Dumbo
    {
        $app = new Dumbo();
        $app->use(Session::session(["store" => $store, ...$options]));

        $app->get("/count", function (Context $context) {
            $session = $context->session();
            $session->set("count", $session->get("count", 0) + 1);

            return $context->text((string) $session->get("count"));
        });
        $app->get("/read", fn(Context $context) => $context->json($context->session()->all()));
        $app->get("/flash", function (Context $context) {
            $context->session()->flash("notice", "Saved");

            return $context->redirect("/read");
        });
        $app->get("/login", function (Context $context) {
            $context->session()->regenerate();
            $context->session()->set("user", 1);

            return $context->text("in");
        });
        $app->get("/logout", function (Context $context) {
            $context->session()->destroy();

            return $context->text("out");
        });
        // A response that isn't built from the context still gets the cookie
        $app->get("/raw", function (Context $context) {
            $context->session()->set("raw", true);

            return new \GuzzleHttp\Psr7\Response(200, [], "raw");
        });

        return $app;
    }

    private function get(string $path, ?Dumbo $app = null): ResponseInterface
    {
        $cookies = implode("; ", array_map(fn($name, $value) => "$name=$value", array_keys($this->jar), $this->jar));
        $response = ($app ?? $this->app)->request($path, "GET", $cookies === "" ? [] : ["Cookie" => $cookies]);

        foreach ($response->getHeader("Set-Cookie") as $header) {
            [$pair] = explode(";", $header);
            [$name, $value] = explode("=", $pair, 2);
            $value = urldecode($value);

            if ($value === "" || str_contains($header, "Max-Age=0")) {
                unset($this->jar[$name]);
            } else {
                $this->jar[$name] = $value;
            }
        }

        return $response;
    }

    public function testKeepsValuesBetweenRequests(): void
    {
        $this->assertSame("1", (string) $this->get("/count")->getBody());
        $this->assertSame("2", (string) $this->get("/count")->getBody());
        $this->assertSame("3", (string) $this->get("/count")->getBody());
    }

    public function testSendsASecureHttpOnlyCookie(): void
    {
        $header = $this->get("/count")->getHeaderLine("Set-Cookie");

        $this->assertMatchesRegularExpression('/^dumbo_session=[a-f0-9]{64}; Path=\/; Max-Age=7200; HttpOnly; SameSite=Lax$/', $header);

        $https = $this->app->request("https://example.com/count");
        $this->assertStringContainsString("; Secure", $https->getHeaderLine("Set-Cookie"));
    }

    public function testNoCookieUntilSomethingIsSet(): void
    {
        $response = $this->get("/read");

        $this->assertSame("[]", (string) $response->getBody());
        $this->assertFalse($response->hasHeader("Set-Cookie"));
    }

    public function testUnchangedSessionsArentRewritten(): void
    {
        $this->get("/count");

        $this->assertFalse($this->get("/read")->hasHeader("Set-Cookie"));
    }

    public function testFlashedValuesLastOneRequest(): void
    {
        $this->get("/flash");

        $this->assertSame(["notice" => "Saved"], json_decode((string) $this->get("/read")->getBody(), true));
        $this->assertSame([], json_decode((string) $this->get("/read")->getBody(), true));
    }

    public function testRegenerateMovesTheSessionToANewId(): void
    {
        $this->get("/count");
        $before = $this->jar["dumbo_session"];

        $this->get("/login");
        $after = $this->jar["dumbo_session"];

        $this->assertNotSame($before, $after);
        $this->assertNull($this->store->read($before));
        $this->assertSame(1, $this->store->read($after)["count"]);
        $this->assertSame(1, $this->store->read($after)["user"]);
    }

    public function testDestroyEndsTheSession(): void
    {
        $this->get("/count");
        $id = $this->jar["dumbo_session"];
        $response = $this->get("/logout");

        $this->assertStringContainsString("dumbo_session=;", $response->getHeaderLine("Set-Cookie"));
        $this->assertNull($this->store->read($id));
        $this->assertSame("1", (string) $this->get("/count")->getBody());
    }

    public function testUnknownSessionsStartAfresh(): void
    {
        $this->jar["dumbo_session"] = str_repeat("a", 64);

        $this->assertSame("1", (string) $this->get("/count")->getBody());
        $this->assertNotSame(str_repeat("a", 64), $this->jar["dumbo_session"]);
    }

    public function testCookieIsAddedToResponsesNotBuiltFromTheContext(): void
    {
        $this->assertStringStartsWith("dumbo_session=", $this->get("/raw")->getHeaderLine("Set-Cookie"));
    }

    public function testCookieStoreKeepsTheDataInTheCookie(): void
    {
        $app = $this->app(new CookieStore(str_repeat("s", 32)));

        $this->assertSame("1", (string) $this->get("/count", $app)->getBody());
        $this->assertSame("2", (string) $this->get("/count", $app)->getBody());
        $this->assertStringNotContainsString("count", $this->jar["dumbo_session"]);

        // A tampered cookie is a new session
        $this->jar["dumbo_session"] = substr($this->jar["dumbo_session"], 0, -2) . "AA";
        $this->assertSame("1", (string) $this->get("/count", $app)->getBody());
    }

    public function testFileStore(): void
    {
        $dir = sys_get_temp_dir() . "/dumbo-sessions-test-" . uniqid();
        $app = $this->app(new FileStore($dir));

        $this->get("/count", $app);
        $this->assertSame("2", (string) $this->get("/count", $app)->getBody());
        $this->assertSame("0600", substr(sprintf("%o", fileperms("$dir/sess_{$this->jar["dumbo_session"]}.json")), -4));

        exec("rm -rf " . escapeshellarg($dir));
    }

    public function testCookieOptions(): void
    {
        $app = $this->app($this->store, ["name" => "sid", "lifetime" => 60, "cookie" => ["sameSite" => "Strict", "domain" => "example.com"]]);

        $this->assertMatchesRegularExpression('/^sid=\w+; Domain=example.com; Path=\/; Max-Age=60; HttpOnly; SameSite=Strict$/', $this->get("/count", $app)->getHeaderLine("Set-Cookie"));
    }

    public function testSessionNeedsTheMiddleware(): void
    {
        $app = new Dumbo();
        $app->get("/", fn(Context $context) => $context->session()->get("x"));

        $this->assertSame(500, $app->request("/")->getStatusCode());

        $this->expectException(\LogicException::class);
        (new Context(new \GuzzleHttp\Psr7\ServerRequest("GET", "/"), [], "/"))->session();
    }

    public function testInvalidOptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Session::session(["store" => new \stdClass()]);
    }
}
