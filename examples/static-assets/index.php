<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\StaticFiles;

$app = new Dumbo();

$app->use("/", StaticFiles::serve(__DIR__ . "/assets"));

// $app->get("/", StaticFiles::serve(__DIR__ . "/assets"));

$app->run();
