<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Middleware\Compress;

$app = new Dumbo();

$app->use(
    Compress::compress([
        "threshold" => 1024, // Minimum size to compress (bytes)
        "encoding" => "gzip", // Preferred encoding (gzip or deflate)
    ])
);

$app->get("/", function ($c) {
    // Large enough to pass the threshold above
    return $c->json([
        "message" => "Hello, Dumbo!",
        "items" => range(1, 500),
    ]);
});

$app->run();
