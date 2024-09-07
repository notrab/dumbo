<?php

use Dumbo\Dumbo;
use Dumbo\Middleware\CacheMiddleware;

require "vendor/autoload.php";


$app = new Dumbo();

/**
 * CacheMiddleware is a middleware that adds cache control headers to the HTTP response.
 * This middleware is applied only to routes that begin with "/cached",
 * meaning that only these routes will have cache control headers.
 */

$app->use('/cached', CacheMiddleware::withHeaders(
    type: "public",
    mustRevalidate: true,
    maxAge: 3600,
    strictEtag: true
));

$app->get("/cached/greet", function ($c) {
    sleep(5);

    return $c->json([
        "message" => "Welcome cached route!",
    ]);
});

$app->get('/uncached/greet', function ($c) {
    sleep(5);

    return $c->json([
        'message' => "Uncached route!",
    ]);
});

$app->run();
