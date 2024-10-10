<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Libsql\Database;

$db = new Database(path: "file.db");

$conn = $db->connect();

$conn->execute("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE
    )
");

$app = new Dumbo();

$app->get("/users", function ($context) use ($conn) {
    $result = $conn->query("SELECT * FROM users")->fetchArray();

    return $context->json($result);
});

$app->get("/users/:id", function ($context) use ($conn) {
    $id = intval($context->req->param("id"));

    $result = $conn
        ->query("SELECT * FROM users WHERE id = ?", [$id])
        ->fetchArray();

    if (empty($result)) {
        return $context->json(["error" => "User not found"], 404);
    }

    return $context->json($result[0]);
});

$app->post("/users", function ($context) use ($conn) {
    $body = $context->req->body();

    if (!isset($body["name"]) || !isset($body["email"])) {
        return $context->json(["error" => "Name and email are required"], 400);
    }

    $result = $conn
        ->query(
            "INSERT INTO users (name, email) VALUES (:name, :email) RETURNING id",
            [
                ":name" => $body["name"],
                ":email" => $body["email"],
            ]
        )
        ->fetchArray();

    return $context->json(["id" => $result[0]["id"]], 201);
});

$app->put("/users/:id", function ($context) use ($conn) {
    $id = intval($context->req->param("id"));
    $body = $context->req->body();

    if (!isset($body["name"]) && !isset($body["email"])) {
        return $context->json(["error" => "Name or email is required"], 400);
    }

    $setClause = [];
    $params = [];

    if (isset($body["name"])) {
        $setClause[] = "name = ?";
        $params[] = $body["name"];
    }

    if (isset($body["email"])) {
        $setClause[] = "email = ?";
        $params[] = $body["email"];
    }

    $params[] = $id;
    $result = $conn
        ->query(
            "UPDATE users SET " .
                implode(", ", $setClause) .
                " WHERE id = ? RETURNING *",
            $params
        )
        ->fetchArray();

    if (empty($result)) {
        return $context->json(["error" => "User not found"], 404);
    }

    return $context->json($result[0]);
});

$app->delete("/users/:id", function ($context) use ($conn) {
    $id = intval($context->req->param("id"));

    $changed = $conn->execute("DELETE FROM users WHERE id = ?", [$id]);

    if ($changed == 0) {
        return $context->json(["error" => "User not found"], 404);
    }

    return $context->json(["message" => "User deleted successfully"]);
});

$app->run();
