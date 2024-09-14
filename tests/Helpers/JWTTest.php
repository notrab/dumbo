<?php

namespace Dumbo\Tests\Helpers;

use Dumbo\Helpers\JWT;
use PHPUnit\Framework\TestCase;
use Firebase\JWT\ExpiredException;

class JWTTest extends TestCase
{
    private const SECRET = "test_secret";
    private const ALG = "HS256";

    public function testSignAndVerify()
    {
        $payload = ["user_id" => 123, "username" => "testuser"];

        $token = JWT::sign($payload, self::SECRET, self::ALG);

        $this->assertIsString($token);

        $decoded = JWT::verify($token, self::SECRET, self::ALG);

        $this->assertIsObject($decoded);
        $this->assertEquals($payload["user_id"], $decoded->user_id);
        $this->assertEquals($payload["username"], $decoded->username);
    }

    public function testExpiredToken()
    {
        $payload = [
            "user_id" => 123,
            "exp" => time() - 3600, // 1 hour in the past
        ];

        $token = JWT::sign($payload, self::SECRET, self::ALG);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Expired token");
        JWT::verify($token, self::SECRET, self::ALG);
    }

    public function testInvalidSignature()
    {
        $payload = ["user_id" => 123];

        $token = JWT::sign($payload, self::SECRET, self::ALG);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Invalid token");
        JWT::verify($token, "wrong_secret", self::ALG);
    }

    public function testDecode()
    {
        $payload = ["user_id" => 123, "username" => "testuser"];

        $token = JWT::sign($payload, self::SECRET, self::ALG);

        $decoded = JWT::decode($token);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey("header", $decoded);
        $this->assertArrayHasKey("payload", $decoded);
        $this->assertEquals(
            $payload["user_id"],
            $decoded["payload"]["user_id"]
        );
        $this->assertEquals(
            $payload["username"],
            $decoded["payload"]["username"]
        );
    }

    public function testInvalidTokenFormat()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Invalid token format");
        JWT::decode("invalid.token");
    }
}
