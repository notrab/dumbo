<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\Cookie;
use Latte\Engine as LatteEngine;
use Darkterminal\TursoHttp\LibSQL;

$app = new Dumbo();
$latte = new LatteEngine();

$dsn = "http://127.0.0.1:8001";
$db = new LibSQL($dsn);

$latte->setAutoRefresh(true);
$latte->setTempDirectory(null);

const COOKIE_SECRET = "somesecretkey";

const SESSION_COOKIE_NAME = "dumbo_session_id";

$db->execute("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL
    )
");

$db->execute("
    CREATE TABLE IF NOT EXISTS sessions (
        id TEXT PRIMARY KEY,
        user_id INTEGER NOT NULL,
        expires_at INTEGER NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )
");

function render($latte, $view, $params = [])
{
    return $latte->renderToString(__DIR__ . "/views/$view.latte", $params);
}

$app->onError(function ($error, $c) {
    error_log($error->getMessage());

    return $c->json(
        [
            "error" => "Internal Server Error",
            "message" => $error->getMessage(),
            "trace" => $error->getTraceAsString(),
        ],
        500
    );
});

$app->use(function ($c, $next) use ($db) {
    $sessionId = Cookie::getSignedCookie(
        $c,
        COOKIE_SECRET,
        SESSION_COOKIE_NAME
    );

    if ($sessionId) {
        $result = $db
            ->query("SELECT * FROM sessions WHERE id = ? AND expires_at > ?", [
                $sessionId,
                time(),
            ])
            ->fetchArray(LibSQL::LIBSQL_ASSOC);

        if (!empty($result)) {
            $user = $db
                ->query("SELECT * FROM users WHERE id = ?", [
                    $result[0]["user_id"],
                ])
                ->fetchArray(LibSQL::LIBSQL_ASSOC);

            if (!empty($user)) {
                $c->set("user", $user[0]);
            }
        }
    }

    return $next($c);
});

$app->get("/", function ($c) use ($latte) {
    $user = $c->get("user");
    $html = render($latte, "home", ["user" => $user]);

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

$app->post("/login", function ($c) use ($db) {
    $body = $c->req->body();
    $username = $body["username"] ?? "";
    $password = $body["password"] ?? "";

    $result = $db
        ->query("SELECT * FROM users WHERE username = ?", [$username])
        ->fetchArray(LibSQL::LIBSQL_ASSOC);

    if (!empty($result) && password_verify($password, $result[0]["password"])) {
        $sessionId = bin2hex(random_bytes(16));
        $expiresAt = time() + 30 * 24 * 60 * 60; // 30 days

        $db->prepare(
            "INSERT INTO sessions (id, user_id, expires_at) VALUES (?, ?, ?)"
        )->execute([$sessionId, $result[0]["id"], $expiresAt]);

        Cookie::setSignedCookie(
            $c,
            SESSION_COOKIE_NAME,
            $sessionId,
            COOKIE_SECRET,
            [
                "httpOnly" => true,
                "secure" => true,
                "path" => "/",
                "maxAge" => 30 * 24 * 60 * 60, // 30 days
                "sameSite" => Cookie::SAME_SITE_LAX,
            ]
        );

        return $c->redirect("/");
    } else {
        $html = render($latte, "login", [
            "error" => "Invalid username or password",
        ]);

        return $c->html($html);
    }
});

$app->get("/login", function ($c) use ($latte) {
    $html = render($latte, "login");

    return $c->html($html);
});

$app->get("/logout", function ($c) use ($db) {
    $sessionId = Cookie::getSignedCookie(
        $c,
        COOKIE_SECRET,
        SESSION_COOKIE_NAME
    );

    if ($sessionId) {
        $db->prepare("DELETE FROM sessions WHERE id = ?")->execute([
            $sessionId,
        ]);
    }

    Cookie::deleteCookie($c, SESSION_COOKIE_NAME, [
        "httpOnly" => true,
        "secure" => true,
        "path" => "/",
    ]);

    return $c->redirect("/");
});

$app->run();
