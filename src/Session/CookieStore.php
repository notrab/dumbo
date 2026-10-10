<?php

namespace Dumbo\Session;

/**
 * Sessions kept in the cookie itself, encrypted and authenticated with
 * libsodium, so the server stores nothing. Browsers limit cookies to about
 * 4KB, so keep sessions small: an ID and a few flags, not a cart.
 *
 * A cookie session can't be revoked from the server: until it expires, a
 * copy of an old cookie still works. Use a server-side store if you need to
 * sign people out everywhere.
 */
class CookieStore implements Store
{
    /** Browsers drop cookies larger than 4096 bytes, name and attributes included */
    private const MAX_LENGTH = 3800;

    private string $key;

    /**
     * @param string $secret At least 32 characters, kept out of your code
     */
    public function __construct(#[\SensitiveParameter] string $secret)
    {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException("The session secret needs at least 32 characters");
        }

        if (!function_exists("sodium_crypto_secretbox")) {
            throw new \RuntimeException("The cookie session store needs the sodium extension");
        }

        $this->key = hash_hkdf("sha256", $secret, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, "dumbo-session");
    }

    public function read(string $cookie): ?array
    {
        try {
            $decoded = sodium_base642bin($cookie, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (\SodiumException) {
            return null;
        }

        if (strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $plain = sodium_crypto_secretbox_open(
            substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key
        );
        $session = $plain === false ? null : json_decode($plain, true);

        if (!is_array($session) || ($session["expires"] ?? 0) <= time() || !is_array($session["data"] ?? null)) {
            return null;
        }

        return $session["data"];
    }

    public function write(?string $cookie, array $data, int $lifetime): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = json_encode(["expires" => time() + $lifetime, "data" => $data], JSON_THROW_ON_ERROR);
        $value = sodium_bin2base64($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        if (strlen($value) > self::MAX_LENGTH) {
            throw new \LengthException("The session is too big for a cookie (" . strlen($value) . " bytes). Store less in it, or use FileStore or your own Store.");
        }

        return $value;
    }

    public function destroy(string $cookie): void
    {
        // Nothing is kept on the server, so expiring the cookie is enough
    }
}
