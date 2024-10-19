<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\Cookie;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Latte\Engine as LatteEngine;
use Darkterminal\TursoHttp\LibSQL;

$app = new Dumbo();
$latte = new LatteEngine();

$dsn = "http://127.0.0.1:8001";
$db = new LibSQL($dsn);

$latte->setAutoRefresh(true);
$latte->setTempDirectory(null);

const JWT_SECRET = "your_jwt_secret_key";
const JWT_EXPIRATION = 3600; // 1 hour
const COOKIE_NAME = "jwt_session";

$db->execute("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL
    )
");

function render($latte, $view, $params = [])
{
    return $latte->renderToString(__DIR__ . "/views/$view.latte", $params);
}

function createJWT($userId, $username)
{
    $issuedAt = time();
    $expirationTime = $issuedAt + JWT_EXPIRATION;

    $payload = [
        "iat" => $issuedAt,
        "exp" => $expirationTime,
        "userId" => $userId,
        "username" => $username,
    ];

    return JWT::encode($payload, JWT_SECRET, "HS256");
}

function verifyJWT($jwt)
{
    try {
        $decoded = JWT::decode($jwt, new Key(JWT_SECRET, "HS256"));
        return (array) $decoded;
    } catch (Exception $e) {
        return null;
    }
}

$app->use(function ($c, $next) {
    $jwt = Cookie::get($c, COOKIE_NAME);
    if ($jwt) {
        $payload = verifyJWT($jwt);
        if ($payload) {
            $c->set("user", $payload);

            // Refresh JWT if it's close to expiration
            if (time() > $payload["exp"] - 300) {
                // Refresh if less than 5 minutes left
                $newJwt = createJWT($payload["userId"], $payload["username"]);
                Cookie::set($c, COOKIE_NAME, $newJwt, [
                    "httpOnly" => true,
                    "secure" => true,
                    "path" => "/",
                    "maxAge" => JWT_EXPIRATION,
                    "sameSite" => Cookie::SAME_SITE_LAX,
                ]);
            }
        } else {
            Cookie::delete($c, COOKIE_NAME);
        }
    }
    return $next($c);
});

$app->get("/", function ($c) use ($latte) {
    $user = $c->get("user");
    $html = render($latte, "home", [
        "user" => $user,
    ]);
    return $c->html($html);
});

$app->get("/register", function ($c) use ($latte) {
    $html = render($latte, "register");
    return $c->html($html);
});

$app->post("/register", function ($c) use ($db, $latte) {
    $body = $c->req->body();
    $username = $body["username"] ?? "";
    $password = $body["password"] ?? "";

    if (empty($username) || empty($password)) {
        $html = render($latte, "register", [
            "error" => "Username and password are required",
        ]);
        return $c->html($html);
    }

    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare(
            "INSERT INTO users (username, password) VALUES (?, ?)"
        )->execute([$username, $hashedPassword]);

        return $c->redirect("/login");
    } catch (Exception $e) {
        $html = render($latte, "register", [
            "error" => "Username already exists",
        ]);
        return $c->html($html);
    }
});

$app->get("/login", function ($c) use ($latte) {
    $html = render($latte, "login");
    return $c->html($html);
});

$app->post("/login", function ($c) use ($db, $latte) {
    $body = $c->req->body();
    $username = $body["username"] ?? "";
    $password = $body["password"] ?? "";

    $result = $db
        ->query("SELECT * FROM users WHERE username = ?", [$username])
        ->fetchArray(LibSQL::LIBSQL_ASSOC);

    if (!empty($result) && password_verify($password, $result[0]["password"])) {
        $jwt = createJWT($result[0]["id"], $result[0]["username"]);
        Cookie::set($c, COOKIE_NAME, $jwt, [
            "httpOnly" => true,
            "secure" => true,
            "path" => "/",
            "maxAge" => JWT_EXPIRATION,
            "sameSite" => Cookie::SAME_SITE_LAX,
        ]);
        return $c->redirect("/");
    } else {
        $html = render($latte, "login", [
            "error" => "Invalid username or password",
        ]);
        return $c->html($html);
    }
});

$app->get("/logout", function ($c) {
    Cookie::delete($c, COOKIE_NAME);
    return $c->redirect("/");
});

$app->get("/protected", function ($c) use ($latte) {
    $user = $c->get("user");
    if (!$user) {
        return $c->redirect("/login");
    }
    $html = render($latte, "protected", [
        "user" => $user,
    ]);
    return $c->html($html);
});

$app->run();
