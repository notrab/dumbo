<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\CORS;

$app = new Dumbo();

$app->use(CORS::cors());

$app->get("/", function ($context) {
    return $context->json(["hello" => "Dumbo!"]);
});

// Or for a specific route with custom options
// $app->use(
//     CORS::cors([
//         "origin" => fn($origin, $c) => $origin,
//         "allow_headers" => ["X-Custom-Header", "Upgrade-Insecure-Requests"],
//         "allow_methods" => ["POST", "GET", "OPTIONS"],
//         "expose_headers" => ["Content-Length", "X-Kuma-Revision"],
//         "max_age" => 600,
//         "credentials" => true,
//     ])
// );

// Multiple origins
// $app->use(
//     CORS::cors([
//         "origin" => ["https://example.com", "https://example.org"],
//     ])
// );

// Dynamic origin based on a function
// $app->use(
//     CORS::cors([
//         "origin" => function ($origin, $c) {
//             return str_ends_with($origin, ".example.com")
//                 ? $origin
//                 : "http://example.com";
//         },
//     ])
// );

$app->run();
