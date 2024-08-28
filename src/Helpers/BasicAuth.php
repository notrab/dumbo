<?php
namespace Dumbo\Helpers;

use Dumbo\Context;

class BasicAuth
{
    /**
     * Create a middleware that checks for Basic Auth credentials in the Authorization header
     *
     * @param array $options The options for the basic authentication middleware
     * @return callable The middleware
     */
    public static function basicAuth(array $options): callable
    {
        if (!isset($options['username']) && !isset($options['password']) && !isset($options['verifyUser']) && empty($options['users'])) {
            throw new \Exception('Basic auth middleware requires options for "username and password" or "verifyUser" or "users"');
        }

        $realm = $options['realm'] ?? 'Secure Area';

        return function (Context $ctx, callable $next) use ($options, $realm) {
            $authHeader = $ctx->req->header('Authorization');

            if (!$authHeader) {
                return $ctx->json(
                    ['error' => 'Authorization header missing'], 
                    401, 
                    ['WWW-Authenticate' => "Basic realm=\"$realm\""]
                );
            }
            
            $parts = explode(' ', $authHeader);
            if (count($parts) !== 2 || strtolower($parts[0]) !== 'basic') {
                return $ctx->json(
                    ['error' => 'Invalid Authorization header format'], 
                    401, 
                    ['WWW-Authenticate' => "Basic realm=\"$realm\""]
                );
            }

            $decoded = base64_decode($parts[1]);
            $credentialsParts = explode(':', $decoded, 2);
            if (count($credentialsParts) !== 2) {
                return $ctx->json(
                    ['error' => 'Invalid credentials format'], 
                    401, 
                    ['WWW-Authenticate' => "Basic realm=\"$realm\""]
                );
            }

            $username = $credentialsParts[0];
            $password = $credentialsParts[1];

            if (isset($options['verifyUser'])) {
                if ($options['verifyUser']($username, $password, $ctx)) {
                    return $next($ctx);
                }else {
                    return $ctx->json(
                        ['error' => 'Invalid credentials'], 
                        401, 
                        ['WWW-Authenticate' => "Basic realm=\"$realm\""]
                    );
                }
            } elseif (isset($options['username']) && isset($options['password'])) {
                if ($username === $options['username'] && $password === $options['password']) {
                    return $next($ctx);
                }
            } elseif (isset($options['users'])) {
                foreach ($options['users'] as $user) {
                    if ($username === $user['username'] && $password === $user['password']) {
                        return $next($ctx);
                    }
                }
            }

            return $ctx->json(
                ['error' => 'Invalid credentials'], 
                401, 
                ['WWW-Authenticate' => "Basic realm=\"$realm\""]
            );
        };
    }
}