<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->get("/", function ($context) {
    $data = [
        "message" => "Hello, Dumbo!",
        "timestamp" => time(),
        "items" => ["php", "dumbo"],
    ];
    return $context->json($data);
});

$app->run();
