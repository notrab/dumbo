<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->get("/", function ($context) {
    return $context->json(["message" => "Hello, World!"]);
});

$app->post("/echo", function ($context) {
    $body = $context->req->body();

    return $context->json($body);
});

// Simulate CPU-intensive task
$app->get("/cpu", function ($context) {
    $result = 0;

    for ($i = 0; $i < 1000000; $i++) {
        $result += sqrt($i);
    }

    return $context->json(["result" => $result]);
});

$app->get("/db/:id", function ($context) {
    $id = $context->req->param("id");

    usleep(50000); // Simulate 50ms database query

    return $context->json(["id" => $id, "name" => "User " . $id]);
});

$app->get("/large", function ($context) {
    $data = [];

    for ($i = 0; $i < 1000; $i++) {
        $data[] = [
            "id" => $i,
            "name" => "Item " . $i,
            "description" =>
                "This is a long description for item " .
                $i .
                " to simulate a large response payload.",
        ];
    }

    return $context->json($data);
});

$app->run();
