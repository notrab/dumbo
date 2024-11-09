<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Middleware\StaticFilesMiddleware;

$app = new Dumbo();

$app->use("/", StaticFilesMiddleware::serve(__DIR__ . "/assets"));

// $app->get("/", StaticFiles::serve(__DIR__ . "/assets"));

$app->run();
