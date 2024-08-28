<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\BasicAuth;

$app = new Dumbo();

$app->use(
    BasicAuth::basicAuth([
        "verifyUser" => function ($username, $password, $ctx) {
            // You could call a database here...
            $validUsers = [
                "admin" => "strongpassword",
                "user" => "password",
            ];
            return isset($validUsers[$username]) &&
                $validUsers[$username] === $password;
        },
        "realm" => "Admin Area",
    ])
);

$app->get("/", function ($c) {
    return $c->html("<h1>Welcome to the admin area!</h1>");
});

$app->run();
