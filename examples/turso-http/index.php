<?php

require "vendor/autoload.php";

use Darkterminal\TursoHttp\LibSQL;
use Dumbo\Dumbo;

$dsn = "...";

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
    $result = $client->execute("SELECT * FROM users");

    return $context->json($result);
});

$app->get("/users/:id", function ($context) use ($client) {
    $id = $context->req->param("id");

    $result = $client->execute("SELECT * FROM users WHERE id = ?", [$id]);

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

    $result = $client->execute(
        "INSERT INTO users (name, email) VALUES (?, ?) RETURNING id",
        [$body["name"], $body["email"]]
    );

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
    $result = $client->execute(
        "UPDATE users SET " .
            implode(", ", $setClause) .
            " WHERE id = ? RETURNING *",
        $params
    );

    if (empty($result)) {
        return $context->json(["error" => "User not found"], 404);
    }

    return $context->json($result[0]);
});

$app->delete("/users/:id", function ($context) use ($client) {
    $id = $context->req->param("id");

    $result = $client->execute("DELETE FROM users WHERE id = ? RETURNING id", [
        $id,
    ]);

    if (empty($result)) {
        return $context->json(["error" => "User not found"], 404);
    }

    return $context->json(["message" => "User deleted successfully"]);
});

$app->run();
