<?php

namespace Dumbo\Session;

/**
 * One visitor's session, from $context->session(). Values are anything
 * that can be encoded as JSON.
 *
 *   $context->session()->set("user_id", 42);
 *   $context->session()->flash("notice", "Saved");   // Only on the next request
 *   $context->session()->regenerate();               // After signing in
 */
final class Session
{
    /** Holds the keys flashed this request ("new") and last request ("old") */
    private const FLASH = "_flash";

    /** When the session was last saved, to keep it alive without saving on every request */
    private const SAVED = "_saved";

    private bool $changed = false;

    private bool $regenerate = false;

    private bool $destroyed = false;

    /**
     * @param string|null $cookie The cookie value it was loaded from, or null for a new session
     */
    public function __construct(private array $data = [], private ?string $cookie = null)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->has($key) ? $this->data[$key] : $default;
    }

    public function has(string $key): bool
    {
        return !self::internal($key) && array_key_exists($key, $this->data);
    }

    public function set(string $key, mixed $value): void
    {
        self::guard($key);
        $this->data[$key] = $value;
        $this->changed = true;
    }

    public function forget(string $key): void
    {
        self::guard($key);

        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
            $this->changed = true;
        }
    }

    /**
     * Get a value and remove it
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    /**
     * Set a value for this request and the next one only, like a message to
     * show after a redirect
     */
    public function flash(string $key, mixed $value): void
    {
        $this->set($key, $value);

        $flash = $this->data[self::FLASH] ?? ["new" => [], "old" => []];
        $flash["new"] = array_values(array_unique([...$flash["new"], $key]));
        $flash["old"] = array_values(array_diff($flash["old"], [$key]));
        $this->data[self::FLASH] = $flash;
    }

    /**
     * @return array<string, mixed> Every value
     */
    public function all(): array
    {
        return array_filter($this->data, fn($key) => !self::internal($key), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Remove every value, keeping the session
     */
    public function clear(): void
    {
        $this->data = [];
        $this->changed = true;
    }

    /**
     * Move the session to a new ID, keeping its values. Do this when someone
     * signs in, so an ID planted before then is useless.
     */
    public function regenerate(): void
    {
        $this->regenerate = true;
        $this->changed = true;
    }

    /**
     * End the session: remove its values, delete it from the store and
     * expire the cookie. Do this when someone signs out.
     */
    public function destroy(): void
    {
        $this->data = [];
        $this->destroyed = true;
    }

    /**
     * @internal The cookie value the session was loaded from
     */
    public function cookie(): ?string
    {
        return $this->cookie;
    }

    /**
     * @internal
     */
    public function regenerated(): bool
    {
        return $this->regenerate;
    }

    /**
     * @internal
     */
    public function destroyed(): bool
    {
        return $this->destroyed;
    }

    /**
     * @internal Whether the session needs writing at the end of the request:
     * it changed, has flashed values to age, or hasn't been saved for a
     * while and needs its expiry pushed back
     */
    public function needsSaving(int $refreshAfter): bool
    {
        if ($this->destroyed) {
            return false;
        }

        $flash = $this->data[self::FLASH] ?? ["new" => [], "old" => []];

        return $this->changed
            || $flash["new"] !== []
            || $flash["old"] !== []
            || ($this->cookie !== null && time() - ($this->data[self::SAVED] ?? 0) >= $refreshAfter);
    }

    /**
     * @internal The data to save: values flashed last request are dropped,
     * and this request's are kept for one more
     */
    public function toStore(): array
    {
        $data = $this->data;
        $flash = $data[self::FLASH] ?? ["new" => [], "old" => []];

        foreach ($flash["old"] as $key) {
            unset($data[$key]);
        }

        unset($data[self::FLASH]);

        if ($flash["new"] !== []) {
            $data[self::FLASH] = ["new" => [], "old" => $flash["new"]];
        }

        $data[self::SAVED] = time();

        return $data;
    }

    private static function internal(string $key): bool
    {
        return $key === self::FLASH || $key === self::SAVED;
    }

    private static function guard(string $key): void
    {
        if (self::internal($key)) {
            throw new \InvalidArgumentException("\"$key\" is used by the session itself");
        }
    }
}
