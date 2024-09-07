<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\RequestId;

$app = new Dumbo();

$app->use(RequestId::requestId());

// Or apply it with custom options
// $app->use(
//     RequestId::requestId([
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
