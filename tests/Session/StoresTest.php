<?php

namespace Dumbo\Tests\Session;

use Dumbo\Session\CookieStore;
use Dumbo\Session\FileStore;
use Dumbo\Session\MemoryStore;
use Dumbo\Session\Session;
use PHPUnit\Framework\TestCase;

class StoresTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . "/dumbo-stores-test-" . uniqid();
    }

    protected function tearDown(): void
    {
        exec("rm -rf " . escapeshellarg($this->dir));
    }

    public static function stores(): array
    {
        return [
            "memory" => [fn() => new MemoryStore()],
            "file" => [fn(string $dir) => new FileStore($dir, 0)],
            "cookie" => [fn() => new CookieStore(str_repeat("k", 32))],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("stores")]
    public function testReadsWhatItWrites(callable $make): void
    {
        $store = $make($this->dir);
        $cookie = $store->write(null, ["a" => 1, "b" => ["c" => true]], 60);

        $this->assertSame(["a" => 1, "b" => ["c" => true]], $store->read($cookie));
        $this->assertNull($store->read("nonsense"));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("stores")]
    public function testExpiredSessionsAreGone(callable $make): void
    {
        $store = $make($this->dir);
        $cookie = $store->write(null, ["a" => 1], -1);

        $this->assertNull($store->read($cookie));
    }

    public function testServerStoresKeepTheirIdAndForgetDestroyedSessions(): void
    {
        foreach ([new MemoryStore(), new FileStore($this->dir, 0)] as $store) {
            $id = $store->write(null, ["a" => 1], 60);

            $this->assertSame($id, $store->write($id, ["a" => 2], 60));
            $this->assertSame(["a" => 2], $store->read($id));

            $store->destroy($id);
            $this->assertNull($store->read($id));
        }
    }

    public function testFileStoreRefusesIdsItDidntMake(): void
    {
        $store = new FileStore($this->dir, 0);

        $this->assertNull($store->read("../../etc/passwd"));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $store->write("../evil", [], 60));
        $this->assertSame([], glob(dirname($this->dir) . "/evil*"));
    }

    public function testFileStoreCleansUpExpiredSessions(): void
    {
        $store = new FileStore($this->dir, 0);
        $old = $store->write(null, [], -1);
        $current = $store->write(null, [], 60);

        $store->clean();

        $this->assertFileDoesNotExist("$this->dir/sess_$old.json");
        $this->assertFileExists("$this->dir/sess_$current.json");
    }

    public function testCookieStoreNeedsALongSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CookieStore("short");
    }

    public function testCookieStoreRefusesSessionsTooBigForACookie(): void
    {
        $this->expectException(\LengthException::class);

        (new CookieStore(str_repeat("k", 32)))->write(null, ["blob" => bin2hex(random_bytes(2000))], 60);
    }

    public function testCookiesFromAnotherSecretAreRejected(): void
    {
        $cookie = (new CookieStore(str_repeat("a", 32)))->write(null, ["a" => 1], 60);

        $this->assertNull((new CookieStore(str_repeat("b", 32)))->read($cookie));
    }

    public function testSessionValues(): void
    {
        $session = new Session(["a" => 1]);

        $this->assertSame(1, $session->get("a"));
        $this->assertSame("x", $session->get("missing", "x"));
        $this->assertTrue($session->has("a"));
        $this->assertSame(1, $session->pull("a"));
        $this->assertFalse($session->has("a"));

        $session->flash("notice", "Hi");
        $this->assertSame(["notice" => "Hi"], $session->all());
        $this->assertArrayNotHasKey("notice", (new Session($session->toStore()))->toStore());

        $this->expectException(\InvalidArgumentException::class);
        $session->set("_flash", []);
    }
}
