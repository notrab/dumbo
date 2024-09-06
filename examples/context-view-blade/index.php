<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->use(function ($context, $next) {
    $context->render(
        function (string $view, array $attributes) {
            // Define our cache and views directory
            $cacheDirectory = __DIR__ . '/cache/views';
            $viewsDirectory = __DIR__ . '/views';

            // Instantiate our Template Engine
            $blade = new Jenssegers\Blade\Blade($viewsDirectory, $cacheDirectory);

            // Render our HTML string from our view and attributes.
            $html = $blade
                ->make($view, $attributes)
                ->render();

            return $html;
        }
    );

    return $next($context);
});

$app->get("/", function ($context) {
    $view = $context->view('home', [
        'message' => 'Hello Dumbo'
    ]);

    return $view;
});

$app->run();
