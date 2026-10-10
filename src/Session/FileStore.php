<?php

namespace Dumbo\Session;

/**
 * Sessions kept as JSON files, one per session, readable only by the
 * server's user. The cookie holds a random 256-bit ID. Expired files are
 * cleared out now and then as sessions are written.
 */
class FileStore implements Store
{
    /**
     * @param string|null $directory Defaults to dumbo-sessions in the temp directory
     * @param float $cleanup The chance each write also deletes expired files
     */
    public function __construct(private ?string $directory = null, private float $cleanup = 0.01)
    {
        $this->directory ??= sys_get_temp_dir() . "/dumbo-sessions";
    }

    public function read(string $cookie): ?array
    {
        $file = $this->file($cookie);

        if ($file === null || !is_file($file)) {
            return null;
        }

        $session = json_decode((string) @file_get_contents($file), true);

        if (!is_array($session) || ($session["expires"] ?? 0) <= time()) {
            return null;
        }

        return is_array($session["data"] ?? null) ? $session["data"] : null;
    }

    public function write(?string $cookie, array $data, int $lifetime): string
    {
        $id = $cookie !== null && $this->file($cookie) !== null ? $cookie : bin2hex(random_bytes(32));

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }

        // Written then moved, so a request never reads half a session
        $file = $this->file($id);
        $temporary = $file . "." . bin2hex(random_bytes(4)) . ".tmp";
        file_put_contents($temporary, json_encode(["expires" => time() + $lifetime, "data" => $data], JSON_THROW_ON_ERROR));
        chmod($temporary, 0600);
        rename($temporary, $file);

        if ($this->cleanup > 0 && mt_rand() / mt_getrandmax() < $this->cleanup) {
            $this->clean();
        }

        return $id;
    }

    public function destroy(string $cookie): void
    {
        $file = $this->file($cookie);

        if ($file !== null && is_file($file)) {
            unlink($file);
        }
    }

    /**
     * Delete every expired session
     */
    public function clean(): void
    {
        foreach (glob($this->directory . "/sess_*.json") ?: [] as $file) {
            $session = json_decode((string) @file_get_contents($file), true);

            if (($session["expires"] ?? 0) <= time()) {
                @unlink($file);
            }
        }
    }

    /**
     * The file for a session ID, or null if it isn't one this store made
     */
    private function file(string $id): ?string
    {
        return preg_match('/^[a-f0-9]{64}$/', $id) ? "$this->directory/sess_$id.json" : null;
    }
}
