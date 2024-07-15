<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->get("/", function ($c) {
    return $c->text("Welcome to Dumbo!");
});

$app->get("/json", function ($c) {
    return $c->json(["message" => "Hello from Dumbo!"]);
});

$app->get("/user/:name", function ($c) {
    $name = $c->param("name");
    return $c->text("Hello, $name!");
});

$app->get("/greet/:greeting/:name", function ($c) {
    $greeting = $c->param("greeting");
    $name = $c->param("name");
    return $c->json([
        "message" => "$greeting, $name!",
    ]);
});

$app->run();
