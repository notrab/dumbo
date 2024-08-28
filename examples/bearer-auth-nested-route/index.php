<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\BearerAuth;

$app = new Dumbo();
$api = new Dumbo();

$token = "mysupersecret";

$api->use(BearerAuth::bearerAuth($token));

$api->get("/", function ($c) {
    return $c->json(["message" => "Protected route accessed successfully"]);
});

$app->route("/api", $api);

$app->run();
