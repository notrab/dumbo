<?php

namespace Dumbo\Middleware;

use Closure;
use Dumbo\Context;
use Dumbo\Helpers\Cookie;
use Dumbo\Session\FileStore;
use Dumbo\Session\Session as SessionData;
use Dumbo\Session\Store;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

class Session
{
    /**
     * Create a middleware that loads the visitor's session, makes it
     * available as $context->session(), and saves it after the handler.
     * Sessions start when something is first set, so visitors who never
     * get one aren't sent a cookie.
     *
     * Options:
     *  - store: a Dumbo\Session\Store (default: a FileStore in the temp directory)
     *  - name: the cookie name (default "dumbo_session")
     *  - lifetime: seconds a session lasts without a request (default 7200)
     *  - cookie: cookie options (default path "/", httpOnly, sameSite Lax,
     *    and secure on HTTPS requests)
     *
     * @throws InvalidArgumentException If the options are invalid
     */
    public static function session(array $options = []): Closure
    {
        $options = array_merge([
            "store" => null,
            "name" => "dumbo_session",
            "lifetime" => 7200,
            "cookie" => [],
        ], $options);

        $store = $options["store"] ?? new FileStore();
        $lifetime = (int) $options["lifetime"];

        if (!$store instanceof Store) {
            throw new InvalidArgumentException("store must be a " . Store::class);
        }

        if ($lifetime < 1) {
            throw new InvalidArgumentException("lifetime must be at least one second");
        }

        return function (Context $context, callable $next) use ($store, $options, $lifetime): ResponseInterface {
            $cookie = Cookie::get($context, $options["name"]);
            $cookie = is_string($cookie) && $cookie !== "" ? $cookie : null;
            $data = $cookie !== null ? $store->read($cookie) : null;

            $session = new SessionData($data ?? [], $data === null ? null : $cookie);
            $context->set("session", $session);

            $response = $next($context);
            $attributes = [
                "path" => "/",
                "httpOnly" => true,
                "sameSite" => Cookie::SAME_SITE_LAX,
                "secure" => $context->req->raw()->getUri()->getScheme() === "https",
                ...$options["cookie"],
            ];

            if ($session->destroyed()) {
                if ($cookie !== null) {
                    $store->destroy($cookie);
                    $response = $response->withAddedHeader("Set-Cookie", Cookie::header($options["name"], "", [...$attributes, "expires" => 1, "maxAge" => 0]));
                }

                return $response;
            }

            // Saved at most once a minute when nothing changed, to keep it alive
            if (!$session->needsSaving(min(60, $lifetime))) {
                return $response;
            }

            if ($session->regenerated() && $session->cookie() !== null) {
                $store->destroy($session->cookie());
            }

            $value = $store->write($session->regenerated() ? null : $session->cookie(), $session->toStore(), $lifetime);

            return $response->withAddedHeader("Set-Cookie", Cookie::header($options["name"], $value, [...$attributes, "maxAge" => $lifetime]));
        };
    }
}
