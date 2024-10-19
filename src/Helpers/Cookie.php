<?php

namespace Dumbo\Helpers;

use Dumbo\Context;

class Cookie
{
    public const SAME_SITE_STRICT = "Strict";
    public const SAME_SITE_LAX = "Lax";
    public const SAME_SITE_NONE = "None";

    public const PREFIX_SECURE = "secure";
    public const PREFIX_HOST = "host";

    /**
     * Check if a cookie exists
     *
     * @param Context $context The context object containing request information
     * @param string $name The name of the cookie to check
     * @param string|null $value Optional value to check against
     * @param string $prefix Optional prefix for the cookie name
     * @return bool True if the cookie exists (and optionally matches the value), false otherwise
     */
    public static function has(
        Context $context,
        string $name,
        ?string $value = null,
        string $prefix = ""
    ): bool {
        $cookies = self::parse($context->req->header("Cookie") ?? "");
        $fullName = self::getPrefixedName($name, $prefix);
        $cookieValue = $cookies[$fullName] ?? null;

        return $cookieValue !== null &&
            ($value === null || $cookieValue === $value);
    }

    /**
     * Get all cookies or a specific cookie.
     *
     * @param Context $context The context object containing request information
     * @param string|null $name The name of the specific cookie to retrieve (null for all cookies)
     * @param string|null $prefix Optional prefix for the cookie name (e.g., "secure" or "host")
     * @return array|string|null An array of all cookies, the value of a specific cookie, or null if not found
     */
    public static function get(
        Context $context,
        ?string $name = null,
        ?string $prefix = null
    ): array|string|null {
        $cookies = self::parse($context->req->header("Cookie") ?? "");

        if ($name === null) {
            return $cookies;
        }

        $fullName = self::getPrefixedName($name, $prefix);

        return $cookies[$fullName] ?? null;
    }

    /**
     * Set a cookie.
     *
     * @param Context $context The context object for setting the response header
     * @param string $name The name of the cookie
     * @param string $value The value of the cookie
     * @param array $options Additional options for the cookie (e.g., 'expires', 'path', 'domain', etc.)
     */
    public static function set(
        Context $context,
        string $name,
        string $value,
        array $options = []
    ): void {
        $cookieString = self::buildString($name, $value, $options);
        $context->header("Set-Cookie", $cookieString);
    }

    /**
     * Delete a cookie.
     *
     * @param Context $context The context object for setting the response header
     * @param string $name The name of the cookie to delete
     * @param array $options Additional options for the cookie deletion (e.g., 'path', 'domain')
     * @return string|null The value of the deleted cookie, or null if it didn't exist
     */
    public static function delete(
        Context $context,
        string $name,
        array $options = []
    ): ?string {
        $prefix = $options["prefix"] ?? "";
        $value = self::get($context, $name, $prefix);

        if ($value === null) {
            return null;
        }

        $options["expires"] = 1;
        $options["path"] = $options["path"] ?? "/";
        self::set($context, $name, "", $options);

        return $value;
    }

    /**
     * Get a signed cookie or all signed cookies.
     *
     * @param Context $context The context object containing request information
     * @param string $secret The secret key used for signing
     * @param string|null $name The name of the specific signed cookie to retrieve (null for all signed cookies)
     * @param string|null $prefix Optional prefix for the cookie name
     * @return mixed The value of the signed cookie, an array of all signed cookies, or null/false if verification fails
     */
    public static function getSigned(
        Context $context,
        string $secret,
        ?string $name = null,
        ?string $prefix = null
    ): mixed {
        $value = self::get($context, $name, $prefix);

        if ($value === null) {
            return null;
        }

        return self::verifySigned($value, $secret);
    }

    /**
     * Set a signed cookie.
     *
     * @param Context $context The context object for setting the response header
     * @param string $name The name of the cookie
     * @param string $value The value to be signed and set in the cookie
     * @param string $secret The secret key used for signing
     * @param array $options Additional options for the cookie
     */
    public static function setSigned(
        Context $context,
        string $name,
        string $value,
        string $secret,
        array $options = []
    ): void {
        $signature = self::sign($value, $secret);
        $signedValue = $value . "." . $signature;

        self::set($context, $name, $signedValue, $options);
    }

    /**
     * Refresh a cookie's expiration time if it exists.
     *
     * @param Context $context The context object for setting the response header
     * @param string $name The name of the cookie to refresh
     * @param array $options Additional options for the cookie
     * @return bool True if the cookie was refreshed, false if it doesn't exist
     */
    public static function refresh(
        Context $context,
        string $name,
        array $options = []
    ): bool {
        $prefix = $options["prefix"] ?? "";
        $value = self::get($context, $name, $prefix);

        if ($value === null) {
            return false;
        }

        self::set($context, $name, $value, $options);

        return true;
    }

    /**
     * Clear all cookies.
     *
     * @param Context $context The context object for setting the response header
     */
    public static function clearAll(Context $context): void
    {
        $cookies = self::get($context);

        foreach ($cookies as $name => $value) {
            $context->header(
                "Set-Cookie",
                $name . "=; Expires=Thu, 01 Jan 1970 00:00:01 GMT"
            );
        }
    }

    /**
     * Parse a cookie string into an associative array.
     *
     * @param string $cookieString The raw cookie string from the HTTP header
     * @return array An associative array of cookie names and values
     */
    private static function parse(string $cookieString): array
    {
        $cookies = [];
        if (empty($cookieString)) {
            return $cookies;
        }

        $pairs = explode("; ", $cookieString);

        foreach ($pairs as $pair) {
            $parts = explode("=", $pair, 2);
            if (count($parts) === 2) {
                $cookies[urldecode($parts[0])] = urldecode($parts[1]);
            }
        }

        return $cookies;
    }

    /**
     * Build a cookie string for the Set-Cookie header.
     *
     * @param string $name The name of the cookie
     * @param string $value The value of the cookie
     * @param array $options Additional options for the cookie
     * @return string The formatted cookie string
     */
    private static function buildString(
        string $name,
        string $value,
        array $options
    ): string {
        $parts = [urlencode($name) . "=" . urlencode($value)];

        if (isset($options["domain"])) {
            $parts[] = "Domain=" . $options["domain"];
        }

        if (isset($options["path"])) {
            $parts[] = "Path=" . $options["path"];
        }

        if (isset($options["expires"])) {
            if ($options["expires"] instanceof \DateTime) {
                $parts[] =
                    "Expires=" . $options["expires"]->format(\DateTime::COOKIE);
            } else {
                $parts[] =
                    "Expires=" . gmdate(\DateTime::COOKIE, $options["expires"]);
            }
        }

        if (isset($options["maxAge"])) {
            $parts[] = "Max-Age=" . $options["maxAge"];
        }

        if (isset($options["secure"]) && $options["secure"]) {
            $parts[] = "Secure";
        }

        if (isset($options["httpOnly"]) && $options["httpOnly"]) {
            $parts[] = "HttpOnly";
        }

        if (isset($options["sameSite"])) {
            $parts[] = "SameSite=" . $options["sameSite"];
        }

        return implode("; ", $parts);
    }

    /**
     * Generate a signature for a cookie value.
     *
     * @param string $value The value to sign
     * @param string $secret The secret key used for signing
     * @return string The generated signature
     */
    private static function sign(string $value, string $secret): string
    {
        return hash_hmac("sha256", $value, $secret);
    }

    /**
     * Get the prefixed name of a cookie.
     *
     * @param string $name The original cookie name
     * @param string|null $prefix The prefix to apply (null, 'secure', or 'host')
     * @return string The prefixed cookie name
     */
    private static function getPrefixedName(
        string $name,
        ?string $prefix
    ): string {
        if ($prefix === self::PREFIX_SECURE) {
            return "__Secure-" . $name;
        } elseif ($prefix === self::PREFIX_HOST) {
            return "__Host-" . $name;
        }
        return $name;
    }

    /**
     * Verify and extract the value from a signed cookie.
     *
     * @param string $value The signed cookie value
     * @param string $secret The secret key used for signing
     * @return string|false The verified cookie value, or false if verification fails
     */
    private static function verifySigned(
        string $value,
        string $secret
    ): string|false {
        $parts = explode(".", $value, 2);

        if (count($parts) !== 2) {
            return false;
        }

        list($value, $signature) = $parts;

        $expectedSignature = self::sign($value, $secret);

        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }

        return $value;
    }
}
