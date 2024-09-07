<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\Compress;

$app = new Dumbo();

$app->use(
    Compress::compress([
        "threshold" => 1024, // Minimum size to compress (bytes)
        "encoding" => "gzip", // Preferred encoding (gzip or deflate)
    ])
);

$app->get("/", function ($c) {
    return $c->json(["message" => "Hello, Dumbo!"]);
});

$app->run();
