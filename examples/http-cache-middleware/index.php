<?php

use Dumbo\Dumbo;
use Dumbo\Middleware\CacheMiddleware;

require "vendor/autoload.php";


$app = new Dumbo();

$app->use(CacheMiddleware::withHeaders(
    type: "public",
    mustRevalidate: true,
    maxAge: 3600,
    strictEtag: true
));

$app->get("/greet/:greeting", function ($c) {
    sleep(5);

    $greeting = $c->req->param("greeting");

    return $c->json([
        "message" => "$greeting!",
    ]);
});

$app->run();
