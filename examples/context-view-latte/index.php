<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->use(function ($context, $next) {
    $context->render(
        function (string $view, array $attributes) {
            // Instantiate our Template Engine
            $latte = new Latte\Engine();

            // Define our cache and views directory
            $cacheDirectory = __DIR__ . '/cache/views';
            $viewsDirectory = __DIR__ . '/views';

            // Create our cache directory, if it doesn't exist
            if (!is_dir($cacheDirectory)) {
                mkdir($cacheDirectory, 0755, true);
            }

            // Set Cache directry
            $latte->setTempDirectory($cacheDirectory);

            // Render our HTML string from our view and attributes.
            $html = $latte->renderToString("{$viewsDirectory}/{$view}", $attributes);

            return $html;
        }
    );

    return $next($context);
});

$app->get("/", function ($context) {
    $view = $context->view('home.latte', [
        'message' => 'Hello Dumbo'
    ]);

    return $view;
});

$app->run();
