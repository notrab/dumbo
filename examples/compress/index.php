<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\Compress;

$app = new Dumbo();

$app->use(Compress::compress(['encoding' => 'gzip']));

$app->get("/", function ($c) {
    return $c->text("Hi");
});

$app->run();
