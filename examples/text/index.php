<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->get("/", function ($context) {
    return $context->text("This is a plain text response from Dumbo.");
});

$app->run();
