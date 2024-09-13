<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->staticFiles("/assets", __DIR__ . "/../assets");

$app->get("/", function ($context) {
    $content = file_get_contents(__DIR__ . "/views/home.php");
    return $context->html($content);
});

$app->run();
