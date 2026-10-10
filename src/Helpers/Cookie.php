<?php

namespace Dumbo\Helpers;

use Dumbo\Context;

/**
 * Read request cookies and set response cookies.
 *
 * Methods that take $options accept: domain, path, expires (timestamp or
 * DateTime), maxAge, secure, httpOnly, sameSite and prefix (PREFIX_SECURE
 * or PREFIX_HOST).
 */
class Cookie
{
    public const SAME_SITE_STRICT = "Strict";
    public const SAME_SITE_LAX = "Lax";
    public const SAME_SITE_NONE = "None";

    public const PREFIX_SECURE = "secure";
    public const PREFIX_HOST = "host";

    /**
     * Check a cookie exists, and optionally that it has the given value
     */
    public static function has(Context $context, string $name, ?string $value = null, ?string $prefix = null): bool
    {
        $cookieValue = self::get($context, $name, $prefix);

        return $cookieValue !== null && ($value === null || $cookieValue === $value);
    }

    /**
     * Get one cookie's value, or every cookie when $name is null
     */
    public static function get(Context $context, ?string $name = null, ?string $prefix = null): array|string|null
    {
        return $context->req->cookie($name === null ? null : self::getPrefixedName($name, $prefix));
    }

    public static function set(Context $context, string $name, string $value, array $options = []): void
    {
        $prefix = $options["prefix"] ?? null;

        // Browsers reject prefixed cookies that don't meet these requirements
        if ($prefix === self::PREFIX_SECURE || $prefix === self::PREFIX_HOST) {
            $options["secure"] = true;
        }

        if ($prefix === self::PREFIX_HOST) {
            $options["path"] = "/";
            unset($options["domain"]);
        }

        $name = self::getPrefixedName($name, $prefix);
        $context->header("Set-Cookie", self::buildString($name, $value, $options), true);
    }

    /**
     * Expire a cookie
     *
     * @return string|null The deleted cookie's value, or null if it didn't exist
     */
    public static function delete(Context $context, string $name, array $options = []): ?string
    {
        $value = self::get($context, $name, $options["prefix"] ?? null);

        if ($value === null) {
            return null;
        }

        $options["expires"] = 1;
        $options["path"] ??= "/";
        self::set($context, $name, "", $options);

        return $value;
    }

    /**
     * Get one verified signed cookie, or every cookie verified when $name is
     * null. Values that fail verification are returned as false.
     */
    public static function getSigned(
        Context $context,
        string $secret,
        ?string $name = null,
        ?string $prefix = null
    ): array|string|false|null {
        if ($name === null) {
            return array_map(
                fn(string $value) => self::verifySigned($value, $secret),
                self::get($context)
            );
        }

        $value = self::get($context, $name, $prefix);

        return $value === null ? null : self::verifySigned($value, $secret);
    }

    public static function setSigned(
        Context $context,
        string $name,
        string $value,
        string $secret,
        array $options = []
    ): void {
        self::set($context, $name, $value . "." . self::sign($value, $secret), $options);
    }

    /**
     * Re-send a cookie with new options, e.g. to extend its expiry
     *
     * @return bool False if the cookie doesn't exist
     */
    public static function refresh(Context $context, string $name, array $options = []): bool
    {
        $value = self::get($context, $name, $options["prefix"] ?? null);

        if ($value === null) {
            return false;
        }

        self::set($context, $name, $value, $options);

        return true;
    }

    public static function clearAll(Context $context): void
    {
        foreach (array_keys(self::get($context)) as $name) {
            $context->header(
                "Set-Cookie",
                urlencode($name) . "=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT",
                true
            );
        }
    }

    private static function buildString(string $name, string $value, array $options): string
    {
        $parts = [urlencode($name) . "=" . urlencode($value)];

        if (isset($options["domain"])) {
            $parts[] = "Domain=" . $options["domain"];
        }

        if (isset($options["path"])) {
            $parts[] = "Path=" . $options["path"];
        }

        if (isset($options["expires"])) {
            $parts[] = "Expires=" . ($options["expires"] instanceof \DateTimeInterface
                ? $options["expires"]->format(\DateTime::COOKIE)
                : gmdate(\DateTime::COOKIE, $options["expires"]));
        }

        if (isset($options["maxAge"])) {
            $parts[] = "Max-Age=" . $options["maxAge"];
        }

        if (!empty($options["secure"])) {
            $parts[] = "Secure";
        }

        if (!empty($options["httpOnly"])) {
            $parts[] = "HttpOnly";
        }

        if (isset($options["sameSite"])) {
            $parts[] = "SameSite=" . $options["sameSite"];
        }

        return implode("; ", $parts);
    }

    private static function sign(string $value, string $secret): string
    {
        return hash_hmac("sha256", $value, $secret);
    }

    private static function getPrefixedName(string $name, ?string $prefix): string
    {
        return match ($prefix) {
            self::PREFIX_SECURE => "__Secure-" . $name,
            self::PREFIX_HOST => "__Host-" . $name,
            default => $name,
        };
    }

    /**
     * @return string|false The value without its signature, or false if the signature is invalid
     */
    private static function verifySigned(string $value, string $secret): string|false
    {
        $separator = strrpos($value, ".");

        if ($separator === false) {
            return false;
        }

        $signature = substr($value, $separator + 1);
        $value = substr($value, 0, $separator);

        return hash_equals(self::sign($value, $secret), $signature) ? $value : false;
    }
}
