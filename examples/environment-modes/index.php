<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->get("/", function ($c) use ($app) {
    return $c->json([
        "message" => "Hello from Dumbo!",
        "environment" => $c->get("environment"),
        "is_development" => $app->isDevelopment(),
    ]);
});

$app->get("/error", function ($c) {
    throw new Exception("This is a test error");
});

$app->run();
