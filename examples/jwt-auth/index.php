<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\JWT;
use Dumbo\Helpers\Cookie;

$app = new Dumbo();

$users = [
    "jamie" => ["password" => "password123", "role" => "user"],
    "dumbo" => ["password" => "adminpass", "role" => "admin"],
];

const JWT_SECRET = "your_jwt_secret_key";
const JWT_COOKIE_NAME = "jwt_token";

$app->use(function ($context, $next) {
    $token = Cookie::get($context, JWT_COOKIE_NAME);

    if ($token) {
        try {
            $payload = JWT::verify($token, JWT_SECRET);
            $context->set("user", $payload);
        } catch (\Exception $e) {
            // If token is invalid, we don't set the user, but we also don't block the request
            // This allows public routes to still work
        }
    }
    return $next($context);
});

$app->post("/login", function ($context) use ($users) {
    $body = $context->req->body();
    $username = $body["username"] ?? "";
    $password = $body["password"] ?? "";

    if (
        !isset($users[$username]) ||
        $users[$username]["password"] !== $password
    ) {
        return $context->json(["error" => "Invalid credentials"], 401);
    }

    $payload = [
        "sub" => $username,
        "role" => $users[$username]["role"],
        "exp" => time() + 3600, // Token expires in 1 hour
    ];

    $token = JWT::sign($payload, JWT_SECRET);

    Cookie::set($context, JWT_COOKIE_NAME, $token, [
        "httpOnly" => true,
        "secure" => true,
        "maxAge" => 3600,
        "path" => "/",
    ]);

    return $context->json(["message" => "Login successful"]);
});

$app->get("/protected", function ($context) {
    $user = $context->get("user");

    if (!$user) {
        return $context->json(["error" => "Unauthorized"], 401);
    }

    return $context->json([
        "message" => "This is a protected route",
        "user" => $user->sub,
        "role" => $user->role,
    ]);
});

$app->post("/logout", function ($context) {
    Cookie::delete($context, JWT_COOKIE_NAME);

    return $context->json(["message" => "Logout successful"]);
});

$app->get("/", function ($context) {
    return $context->json(["message" => "Welcome to the JWT auth example"]);
});

$app->run();
