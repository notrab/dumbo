<?php

require __DIR__ . "/../vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->get("/", fn($c) => $c->json([
    "message" => "Hello from Dumbo on bunny.net Magic Containers!",
    "worker" => getenv("FRANKENPHP_WORKER") === "1",
]));

$app->get("/users/:id", fn($c) => $c->json([
    "id" => $c->req->param("id"),
]));

// A cheap route for health checks and uptime monitors
$app->get("/health", fn($c) => $c->text("ok"));

// In FrankenPHP worker mode, run() keeps the app in memory and handles
// every request; otherwise it handles this one
$app->run();
