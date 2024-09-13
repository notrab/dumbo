<?php

namespace Dumbo\Helpers;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class JWT
{
    /**
     * Sign a payload and create a JWT token.
     *
     * @param array $payload The payload to sign
     * @param string $secret The secret key
     * @param string $alg The algorithm to use (default: 'HS256')
     * @return string The JWT token
     */
    public static function sign(
        array $payload,
        string $secret,
        string $alg = "HS256"
    ): string {
        return FirebaseJWT::encode($payload, $secret, $alg);
    }

    /**
     * Verify and decode a JWT token.
     *
     * @param string $token The JWT token to verify
     * @param string $secret The secret key
     * @param string $alg The algorithm to use (default: 'HS256')
     * @return object The decoded payload
     * @throws \Exception If the token is invalid or expired
     */
    public static function verify(
        string $token,
        string $secret,
        string $alg = "HS256"
    ): object {
        try {
            $decoded = FirebaseJWT::decode($token, new Key($secret, $alg));
            self::validateClaims($decoded);
            return $decoded;
        } catch (\Exception $e) {
            throw new \Exception("Invalid token: " . $e->getMessage());
        }
    }

    /**
     * Decode a JWT token without verification.
     *
     * @param string $token The JWT token to decode
     * @return array An array containing the decoded header and payload
     */
    public static function decode(string $token): array
    {
        $parts = explode(".", $token);
        if (count($parts) != 3) {
            throw new \Exception("Invalid token format");
        }

        $header = json_decode(base64_decode($parts[0]), true);
        $payload = json_decode(base64_decode($parts[1]), true);

        return [
            "header" => $header,
            "payload" => $payload,
        ];
    }

    /**
     * Validate the claims in the decoded payload.
     *
     * @param object $payload The decoded payload
     * @throws \Exception If any claim validation fails
     */
    private static function validateClaims(object $payload): void
    {
        $now = time();

        if (isset($payload->exp) && $now >= $payload->exp) {
            throw new \Exception("Token has expired");
        }

        if (isset($payload->nbf) && $now < $payload->nbf) {
            throw new \Exception("Token not yet valid");
        }

        if (isset($payload->iat) && $now < $payload->iat) {
            throw new \Exception("Token issued in the future");
        }
    }
}
