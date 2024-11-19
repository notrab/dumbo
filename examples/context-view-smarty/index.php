<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->use(function ($context, $next) {
    $context->render(
        function (string $view, array $attributes) {
            // Define our cache and views directory
            $cacheDirectory = __DIR__ . '/cache/views';
            $templateDirectory = __DIR__ . '/views';
            $configDirectory = __DIR__ . '/config';
            $compileDirectory = __DIR__ . '/cache/compiles';


            // Instantiate our Template Engine
            $smarty = new Smarty();
            $smarty->setTemplateDir($templateDirectory);
            $smarty->setCompileDir($compileDirectory);
            $smarty->setCacheDir($cacheDirectory);
            $smarty->setConfigDir($configDirectory);

            // Render our HTML string from our view and attributes.
            $smarty->assign($attributes);
            $html = $smarty->fetch($view);

            return $html;
        }
    );

    return $next($context);
});

$app->get("/", function ($context) {
    $view = $context->view('home.tpl', [
        'message' => 'Hello Dumbo'
    ]);

    return $view;
});

$app->run();
