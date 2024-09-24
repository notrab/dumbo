<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\Cookie;
use Dumbo\Middleware\CsrfMiddleware;
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
        user_agent TEXT,
        ip_address TEXT
    )
");

function render($latte, $view, $params = [])
{
    return $latte->renderToString(__DIR__ . "/views/$view.latte", $params);
}

function invalidateAllUserSessions($userId, $db)
{
    $db->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$userId]);
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

$app->use(
    CsrfMiddleware::csrf([
        "getToken" => function ($ctx) {
            return Cookie::getCookie($ctx, "csrf_token") ?? null;
        },
        "setToken" => function ($ctx, $token) {
            Cookie::setCookie($ctx, "csrf_token", $token, [
                "httpOnly" => true,
                "secure" => true,
                "sameSite" => "Lax",
            ]);
        },
    ])
);

$app->use(function ($c, $next) use ($db) {
    $sessionId = Cookie::getSignedCookie(
        $c,
        COOKIE_SECRET,
        SESSION_COOKIE_NAME
    );

    $debugSessionId = $_COOKIE["debug_session"] ?? "Not set";
    error_log(
        "Middleware: Session ID from cookie: " .
            ($sessionId ? $sessionId : "Not set")
    );
    error_log("Middleware: Debug Session ID: " . $debugSessionId);

    if ($sessionId) {
        $result = $db
            ->query("SELECT * FROM sessions WHERE id = ? AND expires_at > ?", [
                $sessionId,
                time(),
            ])
            ->fetchArray(LibSQL::LIBSQL_ASSOC);

        if (!empty($result)) {
            error_log("Middleware: Valid session found for ID: " . $sessionId);
            $user = $db
                ->query("SELECT * FROM users WHERE id = ?", [
                    $result[0]["user_id"],
                ])
                ->fetchArray(LibSQL::LIBSQL_ASSOC);

            if (!empty($user)) {
                error_log(
                    "Middleware: User found for session: " .
                        $user[0]["username"]
                );
                $c->set("user", $user[0]);
            } else {
                error_log(
                    "Middleware: No user found for session ID: " . $sessionId
                );
            }
        } else {
            error_log(
                "Middleware: No valid session found for ID: " . $sessionId
            );
        }
    } else {
        error_log("Middleware: No session cookie found");
    }

    return $next($c);
});

$app->get("/", function ($c) use ($latte) {
    $user = $c->get("user");
    $flashMessage = $c->get("flash_message");
    error_log(
        "Home route: User " .
            ($user
                ? "is logged in as " . $user["username"]
                : "is not logged in")
    );
    error_log(
        "Home route: Flash message: " .
            ($flashMessage ? $flashMessage : "No flash message")
    );

    $html = render($latte, "home", [
        "user" => $user,
        "flash_message" => $flashMessage,
    ]);
    $c->set("flash_message", null);
    return $c->html($html);
});

$app->get("/register", function ($c) use ($latte) {
    $csrfToken = $c->get("csrf_token");
    $html = render($latte, "register", [
        "csrf_token" => $csrfToken,
    ]);
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

        $c->set("flash_message", "Registration successful. Please log in.");
        return $c->redirect("/login");
    } catch (Exception $e) {
        $html = render($latte, "register", [
            "error" => "Username already exists",
        ]);
        return $c->html($html);
    }
});

$app->get("/login", function ($c) use ($latte) {
    $flashMessage = $c->get("flash_message");
    $csrfToken = $c->get("csrf_token");
    $html = render($latte, "login", [
        "flash_message" => $flashMessage,
        "csrf_token" => $csrfToken,
    ]);
    $c->set("flash_message", null); // Clear the flash message after displaying

    return $c->html($html);
});

$app->post("/login", function ($c) use ($db, $latte) {
    $body = $c->req->body();
    $username = $body["username"] ?? "";
    $password = $body["password"] ?? "";

    error_log("Login attempt for username: " . $username);

    $result = $db
        ->query("SELECT * FROM users WHERE username = ?", [$username])
        ->fetchArray(LibSQL::LIBSQL_ASSOC);

    if (!empty($result) && password_verify($password, $result[0]["password"])) {
        $sessionId = bin2hex(random_bytes(16));
        $expiresAt = time() + 30 * 24 * 60 * 60; // 30 days
        $userAgent = $c->req->header("User-Agent");
        $ipAddress = $_SERVER["REMOTE_ADDR"];

        try {
            $db->prepare(
                "INSERT INTO sessions (id, user_id, expires_at, user_agent, ip_address) VALUES (?, ?, ?, ?, ?)"
            )->execute([
                $sessionId,
                $result[0]["id"],
                $expiresAt,
                $userAgent,
                $ipAddress,
            ]);

            error_log("Session created: " . $sessionId);

            // Set a debug cookie
            setcookie(
                "debug_session",
                $sessionId,
                time() + 3600,
                "/",
                "",
                true,
                false
            );

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

            error_log(
                "Session cookie set: " .
                    SESSION_COOKIE_NAME .
                    " = " .
                    $sessionId
            );

            $c->set("flash_message", "Login successful.");
            error_log("Login successful for user: " . $username);
            return $c->redirect("/");
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $html = render($latte, "login", [
                "error" => "An error occurred during login. Please try again.",
            ]);
            return $c->html($html);
        }
    } else {
        error_log("Login failed for username: " . $username);
        $html = render($latte, "login", [
            "error" => "Invalid username or password",
        ]);
        return $c->html($html);
    }
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

    $c->set("flash_message", "You have been logged out.");
    return $c->redirect("/");
});

$app->get("/settings", function ($c) use ($db, $latte) {
    $user = $c->get("user");
    if (!$user) {
        return $c->redirect("/login");
    }

    $csrfToken = $c->get("csrf_token");
    $sessions = $db
        ->query(
            "SELECT id, user_agent, ip_address, expires_at FROM sessions WHERE user_id = ? AND expires_at > ?",
            [$user["id"], time()]
        )
        ->fetchArray(LibSQL::LIBSQL_ASSOC);

    $html = render($latte, "settings", [
        "user" => $user,
        "sessions" => $sessions,
        "csrf_token" => $csrfToken,
    ]);

    return $c->html($html);
});

$app->post("/settings", function ($c) use ($db, $latte) {
    $user = $c->get("user");
    if (!$user) {
        return $c->redirect("/login");
    }

    $body = $c->req->body();
    $newUsername = $body["username"] ?? "";
    $newPassword = $body["password"] ?? "";
    $currentPassword = $body["current_password"] ?? "";

    $result = $db
        ->query("SELECT * FROM users WHERE id = ?", [$user["id"]])
        ->fetchArray(LibSQL::LIBSQL_ASSOC);
    if (
        empty($result) ||
        !password_verify($currentPassword, $result[0]["password"])
    ) {
        $html = render($latte, "settings", [
            "user" => $user,
            "error" => "Current password is incorrect",
        ]);
        return $c->html($html);
    }

    $updateFields = [];
    $updateParams = [];

    if (!empty($newUsername) && $newUsername !== $user["username"]) {
        $updateFields[] = "username = ?";
        $updateParams[] = $newUsername;
    }

    if (!empty($newPassword)) {
        $updateFields[] = "password = ?";
        $updateParams[] = password_hash($newPassword, PASSWORD_DEFAULT);
    }

    if (!empty($updateFields)) {
        $updateParams[] = $user["id"];
        $db->prepare(
            "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?"
        )->execute($updateParams);

        if (!empty($newPassword)) {
            invalidateAllUserSessions($user["id"], $db);
            $c->set("flash_message", "Password changed. Please log in again.");
            return $c->redirect("/login");
        }

        $user = $db
            ->query("SELECT * FROM users WHERE id = ?", [$user["id"]])
            ->fetchArray(LibSQL::LIBSQL_ASSOC)[0];
        $c->set("user", $user);
        $c->set("flash_message", "Settings updated successfully.");
    }

    return $c->redirect("/settings");
});

$app->post("/invalidate-session", function ($c) use ($db) {
    $user = $c->get("user");
    if (!$user) {
        return $c->redirect("/login");
    }

    $sessionToInvalidate = $c->req->body()["session_id"] ?? "";
    if (empty($sessionToInvalidate)) {
        $c->set("flash_message", "No session specified");
        return $c->redirect("/settings");
    }

    $result = $db
        ->query("SELECT id FROM sessions WHERE id = ? AND user_id = ?", [
            $sessionToInvalidate,
            $user["id"],
        ])
        ->fetchArray(LibSQL::LIBSQL_ASSOC);

    if (!empty($result)) {
        $db->prepare("DELETE FROM sessions WHERE id = ?")->execute([
            $sessionToInvalidate,
        ]);
        $c->set("flash_message", "Session invalidated successfully");
    } else {
        $c->set("flash_message", "Invalid session");
    }

    return $c->redirect("/settings");
});

$app->run();
