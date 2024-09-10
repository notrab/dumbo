<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Middleware\CsrfMiddleware;

$app = new Dumbo();

$app->use(CsrfMiddleware::csrf([
    'origin' => 'http://localhost:8000'
]));

$app->post('/api/greet', function ($c) {
    $body = $c->req->body();

    if (!isset($body["name"])) {
        return $c->json(["error" => "Name is required"], 400);
    }

    return $c->json([
        'message' => 'Hello ' . $body["name"],
    ]);
});

$app->run();
