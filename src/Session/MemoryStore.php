<?php

namespace Dumbo\Session;

/**
 * Sessions kept in memory, for tests and long-running servers with a single
 * process. Everything is lost when the process ends.
 */
class MemoryStore implements Store
{
    /** @var array<string, array{data: array, expires: int}> */
    private array $sessions = [];

    public function read(string $cookie): ?array
    {
        $session = $this->sessions[$cookie] ?? null;

        return $session && $session["expires"] > time() ? $session["data"] : null;
    }

    public function write(?string $cookie, array $data, int $lifetime): string
    {
        $id = $cookie ?? bin2hex(random_bytes(32));
        $this->sessions[$id] = ["data" => $data, "expires" => time() + $lifetime];

        return $id;
    }

    public function destroy(string $cookie): void
    {
        unset($this->sessions[$cookie]);
    }
}
