<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\BearerAuth;

$app = new Dumbo();
$token = "mysupersecret";

$app->use(BearerAuth::bearerAuth($token));

$app->get("/", function ($c) {
    return $c->json(["message" => "Protected route accessed successfully"]);
});

$app->run();
