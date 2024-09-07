<?php

namespace Dumbo\Helpers;

use Dumbo\Context;
use Psr\Http\Message\ResponseInterface;

class BasicAuth
{
    private const STATUS_UNAUTHORIZED = 401;
    private const HEADER_AUTHORIZATION = "Authorization";
    private const HEADER_WWW_AUTHENTICATE = "WWW-Authenticate";

    /**
     * Create a middleware that checks for Basic Auth credentials in the Authorization header
     *
     * @param array|string $options The options for the basic authentication middleware
     * @param string|null $failureMessage The message to return when the credentials are invalid (only for simple auth)
     * @return callable The middleware
     * @throws \InvalidArgumentException If the options are invalid
     */
    public static function basicAuth(
        $options,
        ?string $failureMessage = null
    ): callable {
        if (is_array($options)) {
            return self::advancedBasicAuth($options);
        } elseif (is_string($options)) {
            $parts = explode(":", $options, 2);
            if (count($parts) !== 2) {
                throw new \InvalidArgumentException(
                    "Invalid format for basic auth credentials"
                );
            }
            return self::simpleBasicAuth(
                $parts[0],
                $parts[1],
                $failureMessage ?? "Unauthorized"
            );
        } else {
            throw new \InvalidArgumentException(
                "Invalid options provided for basic auth middleware"
            );
        }
    }

    private static function simpleBasicAuth(
        string $username,
        string $password,
        string $failureMessage
    ): callable {
        return function (Context $context, callable $next) use (
            $username,
            $password,
            $failureMessage
        ): ResponseInterface {
            $authHeader = $context->req->header(self::HEADER_AUTHORIZATION);

            if (
                !$authHeader ||
                !self::validateCredentials($authHeader, $username, $password)
            ) {
                return self::unauthorizedResponse(
                    $context,
                    "Restricted Area",
                    $failureMessage
                );
            }

            return $next($context);
        };
    }

    /**
     * Create a middleware for advanced basic authentication
     *
     * @param array $options The options for advanced basic authentication
     *                       - verifyUser: callable A function to verify the user credentials
     *                       - users: array An array of valid username/password pairs
     *                       - realm: string The realm for the authentication (optional)
     * @return callable The middleware
     * @throws \InvalidArgumentException If the options are invalid
     */
    private static function advancedBasicAuth(array $options): callable
    {
        if (!isset($options["verifyUser"]) && empty($options["users"])) {
            throw new \InvalidArgumentException(
                'Basic auth middleware requires either "verifyUser" function or "users" array'
            );
        }

        $realm = $options["realm"] ?? "Restricted Area";

        return function (
            Context $context,
            callable $next
        ) use ($options, $realm): ResponseInterface {
            $authHeader = $context->req->header(self::HEADER_AUTHORIZATION);

            if (!$authHeader) {
                return self::unauthorizedResponse(
                    $context,
                    $realm,
                    "Authorization required"
                );
            }

            $credentials = self::decodeCredentials($authHeader);
            if (!$credentials) {
                return self::unauthorizedResponse(
                    $context,
                    $realm,
                    "Invalid credentials format"
                );
            }

            [$username, $password] = $credentials;

            if (isset($options["verifyUser"])) {
                if ($options["verifyUser"]($username, $password, $context)) {
                    return $next($context);
                }
            } elseif (isset($options["users"])) {
                foreach ($options["users"] as $user) {
                    if (
                        $username === $user["username"] &&
                        $password === $user["password"]
                    ) {
                        return $next($context);
                    }
                }
            }

            return self::unauthorizedResponse(
                $context,
                $realm,
                "Invalid credentials"
            );
        };
    }

    private static function unauthorizedResponse(
        Context $context,
        string $realm,
        string $error
    ): ResponseInterface {
        return $context->text($error, self::STATUS_UNAUTHORIZED, [
            self::HEADER_WWW_AUTHENTICATE => sprintf(
                'Basic realm="%s"',
                $realm
            ),
        ]);
    }

    private static function validateCredentials(
        string $authHeader,
        string $username,
        string $password
    ): bool {
        $credentials = self::decodeCredentials($authHeader);
        return $credentials &&
            $credentials[0] === $username &&
            $credentials[1] === $password;
    }

    private static function decodeCredentials(string $authHeader): ?array
    {
        if (preg_match('/^Basic\s+(.*)$/i', $authHeader, $matches)) {
            $credentials = base64_decode($matches[1]);
            if ($credentials && strpos($credentials, ":") !== false) {
                return explode(":", $credentials, 2);
            }
        }
        return null;
    }
}
