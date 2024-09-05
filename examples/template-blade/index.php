<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Jenssegers\Blade\Blade;

$app = new Dumbo();

$app->get("/", function ($c) {
    // Define our cache and views directory
    $cacheDirectory = __DIR__ . '/cache/views';
    $viewsDirectory = __DIR__ . '/views';

    // Define our view and attributes.
    $message = $c->get("message") ?? 'Dumbo';
    $view = 'home';
    $attributes = [
        'message' => "Hello from $message!"
    ];

    // Instantiate our Template Engine
    $blade = new Blade($viewsDirectory, $cacheDirectory);

    // Render our HTML string from our view and attributes.
    $html = $blade
    ->make($view, $attributes)
    ->render();

    // Return as HTML
    return $c->html($html);
});

$app->run();
