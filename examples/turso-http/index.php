<?php

require __DIR__ . "/vendor/autoload.php";

use Darkterminal\TursoHttp\LibSQL;
use Dumbo\Dumbo;

// Run: turso dev -p 8001
$dsn = "http://127.0.0.1:8001";

$client = new LibSQL($dsn);

$client->execute("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE
    )
");

$app = new Dumbo();

$app->get("/users", function ($context) use ($client) {
    $result = $client->query("SELECT * FROM users")->fetchArray(LibSQL::LIBSQL_ASSOC);

    return $context->json($result);
});

$app->get("/users/:id", function ($context) use ($client) {
    $id = $context->req->param("id");

    $result = $client->query("SELECT * FROM users WHERE id = ?", [$id])->fetchArray(LibSQL::LIBSQL_ASSOC);

    if (empty($result)) {
        return $context->json(["error" => "User not found"], 404);
    }

    return $context->json($result[0]);
});

$app->post("/users", function ($context) use ($client) {
    $body = $context->req->body();

    if (!isset($body["name"]) || !isset($body["email"])) {
        return $context->json(["error" => "Name and email are required"], 400);
    }

    $result = $client->prepare("INSERT INTO users (name, email) VALUES (?, ?) RETURNING id")
        ->query([$body["name"], $body["email"]])
        ->fetchArray(LibSQL::LIBSQL_ASSOC);

    return $context->json(["id" => $result[0]["id"]], 201);
});

$app->put("/users/:id", function ($context) use ($client) {
    $id = $context->req->param("id");
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
    $result = $client->prepare(
        "UPDATE users SET " .
        implode(", ", $setClause) .
        " WHERE id = ? RETURNING *"
    )->query($params)->fetchArray(LibSQL::LIBSQL_ASSOC);

    if (empty($result)) {
        return $context->json(["error" => "User not found"], 404);
    }

    return $context->json($result[0]);
});

$app->delete("/users/:id", function ($context) use ($client) {
    $id = $context->req->param("id");

    $result = $client->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

    if (empty($result)) {
        return $context->json(["error" => "User not found"], 404);
    }

    return $context->json(["message" => "User deleted successfully"]);
});

$app->run();
