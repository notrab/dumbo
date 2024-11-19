<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Middleware\CompressMiddleware;

$app = new Dumbo();

$app->use(
    CompressMiddleware::compress([
        "threshold" => 1024, // Minimum size to compress (bytes)
        "encoding" => "gzip", // Preferred encoding (gzip or deflate)
    ])
);

$app->get("/", function ($c) {
    return $c->json(["message" => "Hello, Dumbo!"]);
});

$app->run();
