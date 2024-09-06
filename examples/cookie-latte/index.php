<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\Cookie;
use Latte\Engine as LatteEngine;

$app = new Dumbo();
$latte = new LatteEngine();

$latte->setAutoRefresh(true);
$latte->setTempDirectory(null);

function render($latte, $view, $params = [])
{
    return $latte->renderToString(__DIR__ . "/views/$view.latte", $params);
}

$app->onError(function ($error, $c) {
    error_log($error->getMessage());

    return $c->json(
        [
            "error" => "Internal Server Error",
            "message" => $error->getMessage(),
            "trace" => $error->getTraceAsString(),
        ],
        500
    );
});

$app->get("/", function ($c) use ($latte) {
    $username = Cookie::getCookie($c, "username");
    $html = render($latte, "home", ["username" => $username]);

    return $c->html($html);
});

$app->get("/login", function ($c) {
    Cookie::setCookie($c, "username", "Dumbo", [
        "httpOnly" => true,
        "path" => "/",
        "maxAge" => 3600,
    ]);

    return $c->redirect("/");
});

$app->get("/logout", function ($c) {
    Cookie::deleteCookie($c, "username");

    return $c->redirect("/");
});

$app->run();
