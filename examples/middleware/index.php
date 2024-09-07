<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->use(function ($context, $next) {
    echo "middleware 1 start\n";
    $response = $next($context);
    echo "middleware 1 end\n";
    return $response;
});

$app->use(function ($context, $next) {
    echo "middleware 2 start\n";
    $response = $next($context);
    echo "middleware 2 end\n";
    return $response;
});

$app->use(function ($context, $next) {
    echo "middleware 3 start\n";
    $response = $next($context);
    echo "middleware 3 end\n";
    return $response;
});

$app->get("/", function ($context) {
    echo "handler\n";
    return $context->text("Hello!");
});

$app->run();
