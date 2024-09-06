<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\Cookie;

$app = new Dumbo();

$app->get("/cookie", function ($c) {
    $name = $c->req->query("name");

    if ($name) {
        $cookieValue = Cookie::getCookie($c, $name);
        return $c->json(["cookie" => $cookieValue]);
    }

    $allCookies = Cookie::getCookie($c);

    Cookie::setCookie($c, "delicious_cookie", "matcha", [
        "path" => "/",
        "secure" => true,
        "httpOnly" => true,
        "maxAge" => 3600,
        "sameSite" => Cookie::SAME_SITE_LAX,
    ]);

    return $c->json([
        "allCookies" => $allCookies,
        "setCookie" => "delicious_cookie=matcha",
    ]);
});

$app->get("/delete-cookie", function ($c) {
    $name = $c->req->query("name");
    if (!$name) {
        return $c->json(["error" => "Cookie name is required"], 400);
    }

    $deletedValue = Cookie::deleteCookie($c, $name);
    return $c->json(["deletedCookie" => $deletedValue]);
});

$app->get("/signed-cookie", function ($c) {
    $secret = "secret ingredient";
    $name = $c->req->query("name");

    if ($name) {
        $signedCookieValue = Cookie::getSignedCookie($c, $secret, $name);
        return $c->json(["signedCookie" => $signedCookieValue]);
    }

    $allSignedCookies = Cookie::getSignedCookie($c, $secret);

    Cookie::setSignedCookie($c, "great_cookie", "blueberry", $secret, [
        "path" => "/",
        "secure" => true,
        "httpOnly" => true,
        "maxAge" => 3600,
    ]);

    return $c->json([
        "allSignedCookies" => $allSignedCookies,
        "setSignedCookie" => "great_cookie=blueberry (signed)",
    ]);
});

$app->get("/delete-signed-cookie", function ($c) {
    $name = $c->req->query("name");
    if (!$name) {
        return $c->json(["error" => "Cookie name is required"], 400);
    }

    $deletedValue = Cookie::deleteCookie($c, $name);
    return $c->json(["deletedSignedCookie" => $deletedValue]);
});

$app->run();
