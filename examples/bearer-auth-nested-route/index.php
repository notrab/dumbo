<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\BearerAuth;

$app = new Dumbo();
$api = new Dumbo();

$token = "mysupersecret";

$api->use(BearerAuth::bearerAuth($token));

$api->get("/", function ($context) {
    return $context->json([
        "message" => "Protected route accessed successfully",
    ]);
});

$app->route("/api", $api);

$app->run();
