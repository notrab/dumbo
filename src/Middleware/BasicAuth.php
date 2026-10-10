<?php

namespace Dumbo\Middleware;

use Dumbo\Context;
use Psr\Http\Message\ResponseInterface;

class BasicAuth
{
    /**
     * Create a middleware that requires HTTP Basic credentials.
     *
     * Pass "username:password", or an array of options:
     *  - users: array of ["username" => ..., "password" => ...]
     *  - verifyUser: callable(string $username, string $password, Context $context): bool
     *  - realm: string (default "Restricted Area")
     *  - message: string to respond with on failure
     *
     * @throws \InvalidArgumentException If the options are invalid
     */
    public static function basicAuth(string|array $options, ?string $failureMessage = null): callable
    {
        if (is_string($options)) {
            $parts = explode(":", $options, 2);

            if (count($parts) !== 2) {
                throw new \InvalidArgumentException("Invalid format for basic auth credentials");
            }

            $options = [
                "users" => [["username" => $parts[0], "password" => $parts[1]]],
                "message" => $failureMessage ?? "Unauthorized",
            ];
        }

        if (!isset($options["verifyUser"]) && empty($options["users"])) {
            throw new \InvalidArgumentException(
                'Basic auth middleware requires either "verifyUser" function or "users" array'
            );
        }

        $realm = $options["realm"] ?? "Restricted Area";
        $message = $options["message"] ?? null;
        $verify = $options["verifyUser"] ?? function (string $username, string $password) use ($options): bool {
            foreach ($options["users"] as $user) {
                if (hash_equals($user["username"], $username) && hash_equals($user["password"], $password)) {
                    return true;
                }
            }

            return false;
        };

        return function (Context $context, callable $next) use ($realm, $message, $verify): ResponseInterface {
            $header = $context->req->header("Authorization");
            $credentials = self::decode($header ?? "");

            if ($credentials !== null && $verify($credentials[0], $credentials[1], $context)) {
                return $next($context);
            }

            $error = $message ?? ($header ? "Invalid credentials" : "Authorization required");

            return $context->text($error, 401, [
                "WWW-Authenticate" => sprintf('Basic realm="%s"', $realm),
            ]);
        };
    }

    /**
     * @return array{0: string, 1: string}|null The username and password
     */
    private static function decode(string $header): ?array
    {
        if (!preg_match('/^Basic\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $credentials = base64_decode($matches[1], true);

        return $credentials && str_contains($credentials, ":")
            ? explode(":", $credentials, 2)
            : null;
    }
}
