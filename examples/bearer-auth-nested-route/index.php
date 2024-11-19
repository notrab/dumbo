<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Middleware\BearerAuthMiddleware;

$app = new Dumbo();
$api = new Dumbo();

$token = "mysupersecret";

$api->use(BearerAuthMiddleware::bearerAuth($token));

$api->get("/", function ($context) {
    return $context->json([
        "message" => "Protected route accessed successfully",
    ]);
});

$app->route("/api", $api);

$app->run();
