<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

// Route with a single parameter
$app->get("/hello/:name", function ($context) {
    $name = $context->req->param("name");
    return $context->text("Hello, $name!");
});

// Route with multiple parameters
$app->get("/users/:id/posts/:postId", function ($context) {
    $userId = $context->req->param("id");
    $postId = $context->req->param("postId");
    return $context->json([
        "userId" => $userId,
        "postId" => $postId,
        "message" => "Fetching post $postId for user $userId",
    ]);
});

// Optional parameter (using query string)
$app->get("/", function ($context) {
    $name = $context->req->query("name") ?? "Guest";
    return $context->text("Greetings, $name!");
});

$app->run();
