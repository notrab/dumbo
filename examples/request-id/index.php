<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Middleware\RequestIdMiddleware;

$app = new Dumbo();

$app->use(RequestIdMiddleware::requestId());

// Or apply it with custom options
// $app->use(
//     RequestIdMiddleware::requestId([
//         "headerName" => "X-Custom-Request-Id",
//         "limitLength" => 128,
//         "generator" => function ($context) {
//             return uniqid("custom-", true);
//         },
//     ])
// );

$app->get("/", function ($context) {
    $requestId = $context->get("requestId");

    return $context->text("Your request ID is: " . $requestId);
});

$app->run();
