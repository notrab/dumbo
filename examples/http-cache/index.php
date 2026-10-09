<?php

use Dumbo\Dumbo;
use Dumbo\Middleware\Cache;

require __DIR__ . "/vendor/autoload.php";

$app = new Dumbo();

/**
 * The Cache middleware adds Cache-Control and ETag headers to the HTTP response,
 * and answers with 304 Not Modified when the client already has the latest version.
 * This middleware is applied only to routes that begin with "/cached",
 * meaning that only these routes will have cache control headers.
 */

$app->use(
    "/cached",
    Cache::cache([
        "type" => "public",
        "mustRevalidate" => true,
        "maxAge" => 3600,
    ])
);

$app->get("/cached/greet", function ($c) {
    sleep(5);

    return $c->json([
        "message" => "Welcome cached route!",
    ]);
});

$app->get("/uncached/greet", function ($c) {
    sleep(5);

    return $c->json([
        "message" => "Uncached route!",
    ]);
});

$app->run();
