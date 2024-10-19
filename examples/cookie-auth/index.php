<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\Cookie;
use Latte\Engine as LatteEngine;
use Libsql\Database;

$app = new Dumbo();
$latte = new LatteEngine();

$db = new Database(path: "file.db");
$conn = $db->connect();

$latte->setAutoRefresh(true);
$latte->setTempDirectory(null);

const COOKIE_SECRET = "somesecretkey";
const SESSION_COOKIE_NAME = "dumbo_session_id";

$conn->executeBatch("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS sessions (
        id TEXT PRIMARY KEY,
        user_id INTEGER NOT NULL,
        expires_at INTEGER NOT NULL,
        user_agent TEXT,
        ip_address TEXT
    );
");

function render($latte, $view, $params = [])
{
    return $latte->renderToString(__DIR__ . "/views/$view.latte", $params);
}

function invalidateAllUserSessions($userId, $conn)
{
    $conn->execute("DELETE FROM sessions WHERE user_id = ?", [$userId]);
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

$app->use(function ($c, $next) use ($conn) {
    $sessionId = Cookie::getSigned($c, COOKIE_SECRET, SESSION_COOKIE_NAME);

    if ($sessionId) {
        $result = $conn
            ->query("SELECT * FROM sessions WHERE id = ? AND expires_at > ?", [
                $sessionId,
                time(),
            ])
            ->fetchArray();

        if (!empty($result)) {
            $user = $conn
                ->query("SELECT * FROM users WHERE id = ?", [
                    $result[0]["user_id"],
                ])
                ->fetchArray();

            if (!empty($user)) {
                $c->set("user", $user[0]);
            }
        }
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
    $html = render($latte, "register");
    return $c->html($html);
});

$app->post("/register", function ($c) use ($conn, $latte) {
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
        $result = $conn
            ->query(
                "INSERT INTO users (username, password) VALUES (?, ?) RETURNING id",
                [$username, $hashedPassword]
            )
            ->fetchArray();

        $c->set("flash_message", "Registration successful. Please log in.");
        return $c->redirect("/login");
    } catch (Exception $e) {
        $errorMessage = "Registration failed: " . $e->getMessage();
        error_log($errorMessage);
        $html = render($latte, "register", [
            "error" => $errorMessage,
        ]);
        return $c->html($html);
    }
});

$app->get("/login", function ($c) use ($latte) {
    $flashMessage = $c->get("flash_message");

    $html = render($latte, "login", [
        "flash_message" => $flashMessage,
    ]);
    $c->set("flash_message", null);

    return $c->html($html);
});

$app->post("/login", function ($c) use ($conn, $latte) {
    $body = $c->req->body();
    $username = $body["username"] ?? "";
    $password = $body["password"] ?? "";

    error_log("Login attempt for username: " . $username);

    $result = $conn
        ->query("SELECT * FROM users WHERE username = ?", [$username])
        ->fetchArray();

    if (!empty($result) && password_verify($password, $result[0]["password"])) {
        $sessionId = bin2hex(random_bytes(16));
        $expiresAt = time() + 30 * 24 * 60 * 60; // 30 days
        $userAgent = $c->req->header("User-Agent");
        $ipAddress = $_SERVER["REMOTE_ADDR"];

        try {
            $conn->query(
                "INSERT INTO sessions (id, user_id, expires_at, user_agent, ip_address) VALUES (?, ?, ?, ?, ?)",
                [
                    $sessionId,
                    $result[0]["id"],
                    $expiresAt,
                    $userAgent,
                    $ipAddress,
                ]
            );

            Cookie::setSigned(
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

            $c->set("flash_message", "Login successful.");

            return $c->redirect("/");
        } catch (Exception $e) {
            $html = render($latte, "login", [
                "error" => "An error occurred during login. Please try again.",
            ]);
            return $c->html($html);
        }
    } else {
        $html = render($latte, "login", [
            "error" => "Invalid username or password",
        ]);
        return $c->html($html);
    }
});

$app->get("/logout", function ($c) use ($conn) {
    $sessionId = Cookie::getSigned($c, COOKIE_SECRET, SESSION_COOKIE_NAME);

    if ($sessionId) {
        $conn->execute("DELETE FROM sessions WHERE id = ?", [$sessionId]);
    }

    Cookie::delete($c, SESSION_COOKIE_NAME, [
        "httpOnly" => true,
        "secure" => true,
        "path" => "/",
    ]);

    $c->set("flash_message", "You have been logged out.");
    return $c->redirect("/");
});

$app->get("/settings", function ($c) use ($conn, $latte) {
    $user = $c->get("user");
    if (!$user) {
        return $c->redirect("/login");
    }

    $sessions = $conn
        ->query(
            "SELECT id, user_agent, ip_address, expires_at FROM sessions WHERE user_id = ? AND expires_at > ?",
            [$user["id"], time()]
        )
        ->fetchArray();

    $html = render($latte, "settings", [
        "user" => $user,
        "sessions" => $sessions,
    ]);

    return $c->html($html);
});

$app->post("/settings", function ($c) use ($conn, $latte) {
    $user = $c->get("user");
    if (!$user) {
        return $c->redirect("/login");
    }

    $body = $c->req->body();
    $newUsername = $body["username"] ?? "";
    $newPassword = $body["password"] ?? "";
    $currentPassword = $body["current_password"] ?? "";

    $result = $conn
        ->query("SELECT * FROM users WHERE id = ?", [$user["id"]])
        ->fetchArray();
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
        $query =
            "UPDATE users SET " .
            implode(", ", $updateFields) .
            " WHERE id = ? RETURNING *";
        $updatedUser = $conn->query($query, $updateParams)->fetchArray();

        if (!empty($updatedUser)) {
            if (!empty($newPassword)) {
                invalidateAllUserSessions($user["id"], $conn);
                $c->set(
                    "flash_message",
                    "Password changed. Please log in again."
                );
                return $c->redirect("/login");
            }

            $c->set("user", $updatedUser[0]);
            $c->set("flash_message", "Settings updated successfully.");
        }
    }

    return $c->redirect("/settings");
});

$app->post("/invalidate-session", function ($c) use ($conn) {
    $user = $c->get("user");
    if (!$user) {
        return $c->redirect("/login");
    }

    $sessionToInvalidate = $c->req->body()["session_id"] ?? "";
    if (empty($sessionToInvalidate)) {
        $c->set("flash_message", "No session specified");
        return $c->redirect("/settings");
    }

    $result = $conn
        ->query("SELECT id FROM sessions WHERE id = ? AND user_id = ?", [
            $sessionToInvalidate,
            $user["id"],
        ])
        ->fetchArray();

    if (!empty($result)) {
        $conn->execute("DELETE FROM sessions WHERE id = ?", [
            $sessionToInvalidate,
        ]);

        $currentSessionId = Cookie::getSigned(
            $c,
            COOKIE_SECRET,
            SESSION_COOKIE_NAME
        );
        if ($sessionToInvalidate === $currentSessionId) {
            Cookie::delete($c, SESSION_COOKIE_NAME);
            return $c->redirect("/login");
        }

        $c->set("flash_message", "Session invalidated successfully");
    } else {
        $c->set("flash_message", "Invalid session");
    }

    return $c->redirect("/settings");
});

$app->run();
