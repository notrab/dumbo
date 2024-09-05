<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Latte\Engine;

$app = new Dumbo();

$app->get("/", function ($c) {
    // Instantiate our Template Engine
    $latte = new Engine();

    // Define our view and attributes.
    $message = $c->get("message") ?? 'Dumbo';
    $view = __DIR__ . "/view.latte";
    $attributes = [
        'message' => "Hello from $message!"
    ];

    // Define our cache directory
    $cacheDirectory = __DIR__ . '/cache/views';

    // Create our cache directory, if it doesn't exist
    if (!is_dir($cacheDirectory)) {
        mkdir($cacheDirectory, 0755, true);
    }

    // Set Cache directry
    $latte->setTempDirectory($cacheDirectory);

    // Render our HTML string from our view and attributes.
    $html = $latte->renderToString($view, $attributes);

    // Return as HTML
    return $c->html($html);
});

$app->run();
