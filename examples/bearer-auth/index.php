<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Middleware\BearerAuth;

$app = new Dumbo();
$token = "mysupersecret";

$app->use(BearerAuth::bearerAuth($token));

$app->get("/", function ($context) {
    return $context->json([
        "message" => "Protected route accessed successfully",
    ]);
});

$app->run();
