<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

// Context middleware for all routes
$app->use(function ($context, $next) {
    $context->set("message", "Hello Dumbo!");

    return $next($context);
});

$app->get("/", function ($context) {
    $message = $context->get("message");

    return $context->json([
        "message" => $message,
    ]);
});

// Route specific context via middleware
$app->use("/api", function ($context, $next) {
    $context->set("databaseUrl", "something-connection-uri");
    $context->set("authToken", "my-secret-auth-token");

    return $next($context);
});

$app->get("/api", function ($context) {
    $databaseUrl = $context->get("databaseUrl");
    $authToken = $context->get("authToken");
    $message = $context->get("message");

    return $context->json([
        "message" => $message,
        "databaseUrl" => $databaseUrl,
        "authToken" => $authToken,
    ]);
});

$app->run();
