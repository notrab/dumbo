<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Middleware\BasicAuthMiddleware;

$app = new Dumbo();

$app->use(BasicAuthMiddleware::basicAuth("user:password"));

$app->get("/", function ($context) {
    return $context->html("<h1>Welcome to the protected area!</h1>");
});

$app->run();
