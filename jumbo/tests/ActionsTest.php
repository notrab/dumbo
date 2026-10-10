<?php

namespace Dumbo\Jumbo\Tests;

use Dumbo\Dumbo;
use Dumbo\Jumbo\Action;
use Dumbo\Jumbo\Page;
use Dumbo\Jumbo\Site;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class ActionsTest extends TestCase
{
    private function site(array $config = []): Site
    {
        return new Site(__DIR__ . "/fixtures/features", $config, new Dumbo(Dumbo::ENV_TESTING));
    }

    private function post(Site $site, string $path, string $body, array $headers = []): ResponseInterface
    {
        return $site->app->request($path, "POST", ["Content-Type" => "application/x-www-form-urlencoded", ...$headers], $body);
    }

    public function testThePageRendersAsUsual(): void
    {
        $this->assertStringContainsString("<p>form</p>", (string) $this->site()->app->request("/contact")->getBody());
    }

    public function testADefaultActionRendersThePageWithItsResult(): void
    {
        $response = $this->post($this->site(), "/contact", "email=jamie%40example.com");

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString("<p>sent: {&quot;to&quot;:&quot;jamie@example.com&quot;}</p>", (string) $response->getBody());
    }

    public function testAFailedActionShowsTheErrorsAndWhatWasSent(): void
    {
        $response = $this->post($this->site(), "/contact", "email=nope");
        $body = (string) $response->getBody();

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString("<p>error: Enter an email address</p>", $body);
        $this->assertStringContainsString('<input name="email" value="nope">', $body);
    }

    public function testNamedActions(): void
    {
        $site = $this->site();

        $this->assertStringContainsString("<p>subscribed</p>", (string) $this->post($site, "/contact", "_action=subscribe")->getBody());
        $this->assertStringContainsString("<p>subscribed</p>", (string) $this->post($site, "/contact?_action=subscribe", "")->getBody());
        $this->assertStringNotContainsString("subscribed", (string) $this->post($site, "/contact", "email=a%40b.co")->getBody());
    }

    public function testActionsCanRespondThemselves(): void
    {
        $response = $this->post($this->site(), "/contact", "_action=redirect");

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame("/contact?sent=1", $response->getHeaderLine("Location"));
    }

    public function testUnknownActions(): void
    {
        $response = $this->post($this->site(), "/contact", "_action=missing");

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('This page has no "missing" action', (string) $response->getBody());
        $this->assertSame(400, $this->post($this->site(), "/contact", "_action[]=x")->getStatusCode());
    }

    public function testFetchGetsJson(): void
    {
        $response = $this->post($this->site(), "/contact", "email=nope", ["Accept" => "application/json"]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(["ok" => false, "data" => null, "errors" => ["email" => "Enter an email address"]], json_decode((string) $response->getBody(), true));

        $response = $this->site()->app->request("/contact", "POST", ["Accept" => "application/json"], ["email" => "a@b.co"]);
        $this->assertSame('{"ok":true,"data":{"to":"a@b.co"},"errors":{}}', (string) $response->getBody());
    }

    public function testPostsFromOtherSitesAreRefused(): void
    {
        $site = $this->site();

        $this->assertSame(403, $this->post($site, "/contact", "email=a%40b.co", ["Sec-Fetch-Site" => "cross-site"])->getStatusCode());
        $this->assertSame(403, $this->post($site, "http://example.com/contact", "email=a%40b.co", ["Origin" => "https://evil.example"])->getStatusCode());
        $this->assertSame(403, $this->post($site, "http://example.com/contact", "email=a%40b.co", ["Origin" => "null"])->getStatusCode());
        $this->assertSame(200, $this->post($site, "http://example.com/contact", "email=a%40b.co", ["Origin" => "http://example.com"])->getStatusCode());
        $this->assertSame(200, $this->post($site, "/contact", "email=a%40b.co", ["Sec-Fetch-Site" => "same-origin"])->getStatusCode());
    }

    public function testOriginChecksCanBeLoosenedOrTurnedOff(): void
    {
        $headers = ["Sec-Fetch-Site" => "cross-site", "Origin" => "https://admin.example.com"];

        $this->assertSame(200, $this->post($this->site(["actions" => ["origins" => ["https://admin.example.com/"]]]), "/contact", "email=a%40b.co", $headers)->getStatusCode());
        $this->assertSame(200, $this->post($this->site(["actions" => ["checkOrigin" => false]]), "/contact", "email=a%40b.co", $headers)->getStatusCode());
    }

    public function testPagesWithoutActionsDontTakePosts(): void
    {
        $this->assertSame(405, $this->post($this->site(), "/about", "x=1")->getStatusCode());
    }

    public function testActionsWorkInEveryLocale(): void
    {
        $response = $this->post($this->site(["i18n" => ["locales" => ["en", "fr"]]]), "/fr/contact", "email=nope");

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('<html lang="fr">', (string) $response->getBody());
    }

    public function testActionsNeedNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Page(render: fn() => "", actions: [fn() => null]);
    }

    public function testFailuresNeedAnErrorStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Action::fail([], 200);
    }
}
