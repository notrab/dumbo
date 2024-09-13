<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->staticFiles("/", __DIR__ . "/assets");

$app->run();
