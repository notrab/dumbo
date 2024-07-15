# Dumbo

A lightweight, friendly PHP framework for HTTP &mdash; inspired by Hono.

## Install

```bash
composer require notrab/dumbo
```

## Quickstart

```php
<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Adapters\PhpDevelopmentServer;

$server = new PhpDevelopmentServer();

$app = new Dumbo($server);
$user = new Dumbo($server);

$userData = [
    [
        "id" => 1,
        "name" => "Jamie Barton",
        "email" => "jamie@notrab.dev",
    ],
];

$user->get("/", function ($c) use ($userData) {
    return $c->json($userData);
});

$user->get("/:id", function ($c) use ($userData) {
    $id = (int) $c->req->param("id");

    $user =
        array_values(array_filter($userData, fn($u) => $u["id"] === $id))[0] ??
        null;

    if (!$user) {
        return $c->json(["error" => "User not found"], 404);
    }

    return $c->json($user);
});

$user->post("/", function ($c) use ($userData) {
    $body = $c->req->body();

    if (!isset($body["name"]) || !isset($body["email"])) {
        return $c->json(["error" => "Name and email are required"], 400);
    }

    $newId = max(array_column($userData, "id")) + 1;

    $newUserData = array_merge(["id" => $newId], $body);

    return $c->json($newUserData, 201);
});

$user->delete("/:id", function ($c) use ($userData) {
    $id = (int) $c->req->param("id");

    $user =
        array_values(array_filter($userData, fn($u) => $u["id"] === $id))[0] ??
        null;

    if (!$user) {
        return $c->json(["error" => "User not found"], 404);
    }

    return $c->json(["message" => "User deleted successfully"]);
});

$app->get("/greet/:greeting", function ($c) {
    $greeting = $c->req->param("greeting");
    $name = $c->req->query("name");

    return $c->json([
        "message" => "$greeting, $name!",
    ]);
});

$app->route("/users", $user);

$app->use(function ($c, $next) {
    $c->header("X-Powered-By", "Dumbo");

    return $next($c);
});

$app->get("/", function ($c) {
    return $c->html("<h1>Hello from Dumbo!</h1>", 200, [
        "X-Hello" => "World",
    ]);
});

$app->run();
```
