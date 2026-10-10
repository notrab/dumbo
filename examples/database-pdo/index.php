<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;

// Defaults to a local SQLite file. Set DATABASE_DSN (and DATABASE_USER /
// DATABASE_PASSWORD) to use MySQL or Postgres instead, for example:
// DATABASE_DSN="mysql:host=localhost;dbname=app;charset=utf8mb4"
$pdo = new PDO(
    getenv("DATABASE_DSN") ?: "sqlite:" . __DIR__ . "/database.db",
    getenv("DATABASE_USER") ?: null,
    getenv("DATABASE_PASSWORD") ?: null,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === "sqlite") {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE
        )
    ");
}

$app = new Dumbo();

// Share the connection with every route through the context
$app->use(function ($context, $next) use ($pdo) {
    $context->set("db", $pdo);

    return $next($context);
});

$app->get("/users", function ($context) {
    $users = $context
        ->get("db")
        ->query("SELECT * FROM users ORDER BY id")
        ->fetchAll();

    return $context->json($users);
});

$app->get("/users/:id", function ($context) {
    $stmt = $context->get("db")->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$context->req->param("id")]);
    $user = $stmt->fetch();

    if (!$user) {
        return $context->json(["error" => "User not found"], 404);
    }

    return $context->json($user);
});

$app->post("/users", function ($context) {
    $body = $context->req->body();

    if (empty($body["name"]) || empty($body["email"])) {
        return $context->json(["error" => "Name and email are required"], 400);
    }

    $db = $context->get("db");

    try {
        $stmt = $db->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
        $stmt->execute([$body["name"], $body["email"]]);
    } catch (PDOException $e) {
        // SQLSTATE 23000 is an integrity constraint violation (duplicate email)
        if ($e->getCode() === "23000") {
            return $context->json(["error" => "Email already exists"], 409);
        }

        throw $e;
    }

    return $context->json(["id" => (int) $db->lastInsertId()], 201);
});

$app->put("/users/:id", function ($context) {
    $body = $context->req->body();

    // Only ever build SQL from a fixed list of columns, never from request keys
    $fields = array_intersect_key($body, array_flip(["name", "email"]));

    if (!$fields) {
        return $context->json(["error" => "Name or email is required"], 400);
    }

    $set = implode(", ", array_map(fn($column) => "$column = ?", array_keys($fields)));

    $db = $context->get("db");
    $stmt = $db->prepare("UPDATE users SET $set WHERE id = ?");
    $stmt->execute([...array_values($fields), $context->req->param("id")]);

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$context->req->param("id")]);
    $user = $stmt->fetch();

    if (!$user) {
        return $context->json(["error" => "User not found"], 404);
    }

    return $context->json($user);
});

$app->delete("/users/:id", function ($context) {
    $stmt = $context->get("db")->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$context->req->param("id")]);

    if ($stmt->rowCount() === 0) {
        return $context->json(["error" => "User not found"], 404);
    }

    return $context->json(["message" => "User deleted successfully"]);
});

$app->run();
