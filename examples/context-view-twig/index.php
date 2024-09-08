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

            $loader = new Twig\Loader\FilesystemLoader($viewsDirectory);
            $twig = new Twig\Environment($loader, [
                'cache' => $cacheDirectory,
            ]);

            // Render our HTML string from our view and attributes.
            $html = $twig->render($view, $attributes);

            return $html;
        }
    );

    return $next($context);
});

$app->get("/", function ($context) {
    $view = $context->view('home.html', [
        'message' => 'Hello Dumbo'
    ]);

    return $view;
});

$app->run();
