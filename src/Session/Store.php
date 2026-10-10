<?php

namespace Dumbo\Session;

/**
 * Where sessions are kept. The session cookie holds whatever write()
 * returns: an ID for stores that keep data on the server, or the data
 * itself for the cookie store.
 */
interface Store
{
    /**
     * The data for a session cookie's value, or null if it's unknown,
     * expired or has been tampered with
     */
    public function read(string $cookie): ?array;

    /**
     * Save a session's data and return the value for its cookie
     *
     * @param string|null $cookie The session's current cookie value, or null for a new session
     * @param int $lifetime Seconds until the session expires without another write
     */
    public function write(?string $cookie, array $data, int $lifetime): string;

    public function destroy(string $cookie): void;
}
