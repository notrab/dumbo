<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\Cookie;
use Latte\Engine as LatteEngine;

$app = new Dumbo();
$latte = new LatteEngine();

$latte->setAutoRefresh(true);
$latte->setTempDirectory(null);

const COOKIE_SECRET = "somesecretkey";

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
    $username = Cookie::getSignedCookie($c, COOKIE_SECRET, "username");
    $html = render($latte, "home", ["username" => $username]);

    return $c->html($html);
});

$app->get("/login", function ($c) {
    Cookie::setSignedCookie($c, "username", "Dumbo", COOKIE_SECRET, [
        "httpOnly" => true,
        "secure" => true,
        "path" => "/",
        "maxAge" => 3600,
        "sameSite" => Cookie::SAME_SITE_LAX,
    ]);

    return $c->redirect("/");
});

$app->get("/logout", function ($c) {
    Cookie::deleteCookie($c, "username", [
        "httpOnly" => true,
        "secure" => true,
        "path" => "/",
    ]);

    return $c->redirect("/");
});

$app->run();
