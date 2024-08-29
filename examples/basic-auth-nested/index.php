<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\BasicAuth;

$app = new Dumbo();
$api = new Dumbo();

$api->use(BasicAuth::basicAuth("user:password"));

$api->get("/", function ($context) {
    return $context->html("<h1>Welcome to the protected area!</h1>");
});

$app->route("/api", $api);

$app->run();
