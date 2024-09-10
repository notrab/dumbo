<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\BasicAuth;

$app = new Dumbo();

$app->use(
    BasicAuth::basicAuth([
        "users" => [
            ["username" => "user1", "password" => "pass1"],
            ["username" => "user2", "password" => "pass2"],
        ],
        "realm" => "Admin Area",
    ])
);

$app->get("/", function ($context) {
    return $context->html("<h1>Welcome to the protected area!</h1>");
});

$app->run();
