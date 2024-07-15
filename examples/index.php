<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();
$user = new Dumbo();

$userData = [
    "id" => 1,
    "name" => "Jamie Barton",
    "email" => "jamie@notrab.dev",
];

$user->on("GET", "/", function ($c) {
    return $c->json([
        "id" => 1,
        "name" => "Jamie Barton",
        "email" => "jamie@notrab.dev",
    ]);
});

$user->get("/:id", function ($c) use ($userData) {
    $id = (int) $c->param("id");

    if ($id !== $userData["id"]) {
        return $c->json(["error" => "User not found"], 404);
    }

    return $c->json($userData);
});

$app->get("/", function ($c) {
    return $c->html("<h1>Hello from Dumbo!</h1>", 200, [
        "X-Hello" => "World",
    ]);
});

$app->get("/json", function ($c) {
    return $c->json(["message" => "Hello from Dumbo!"]);
});

$app->get("/greet/:greeting/:name", function ($c) {
    $greeting = $c->param("greeting");
    $name = $c->param("name");

    return $c->json([
        "message" => "$greeting, $name!",
    ]);
});

$app->route("/users", $user);

$app->run();
