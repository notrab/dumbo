<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->get("/", function ($context) {
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dumbo</title>
</head>
<body>
    <h1>Welcome to Dumbo!</h1>
    <p>This is an HTML response.</p>
</body>
</html>
HTML;

    return $context->html($html);
});

$app->run();
