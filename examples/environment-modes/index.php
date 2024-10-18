<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->get("/", function ($c) {
    $env = $c->get("environment");

    return $c->json([
        "message" => "Hello from Dumbo!",
        "environment" => $env["current"],
        "is_development" => $env["isDevelopment"],
        "is_production" => $env["isProduction"],
        "is_testing" => $env["isTesting"],
    ]);
});

$app->get("/error", function ($c) {
    throw new Exception("This is a test error");
});

$app->run();
