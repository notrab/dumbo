<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->get("/", function ($context) {
    return $context->redirect("/destination", 302);
});

$app->get("/destination", function ($context) {
    return $context->text("You have been redirected here!");
});

$app->run();
