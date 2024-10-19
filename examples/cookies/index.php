<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\Cookie;

$app = new Dumbo();

$app->get("/cookie", function ($c) {
    $name = $c->req->query("name");

    if ($name) {
        $cookieValue = Cookie::get($c, $name);
        return $c->json(["cookie" => $cookieValue]);
    }

    $allCookies = Cookie::get($c);

    Cookie::set($c, "delicious_cookie", "matcha", [
        "path" => "/",
        "secure" => true,
        "httpOnly" => true,
        "maxAge" => 3600,
        "sameSite" => Cookie::SAME_SITE_LAX,
    ]);

    return $c->json([
        "allCookies" => $allCookies,
        "set" => "delicious_cookie=matcha",
    ]);
});

$app->get("/delete-cookie", function ($c) {
    $name = $c->req->query("name");
    if (!$name) {
        return $c->json(["error" => "Cookie name is required"], 400);
    }

    $deletedValue = Cookie::delete($c, $name);
    return $c->json(["deletedCookie" => $deletedValue]);
});

$app->get("/signed-cookie", function ($c) {
    $secret = "secret ingredient";
    $name = $c->req->query("name");

    if ($name) {
        $signedCookieValue = Cookie::getSigned($c, $secret, $name);
        return $c->json(["signedCookie" => $signedCookieValue]);
    }

    $allSignedCookies = Cookie::getSigned($c, $secret);

    Cookie::setSigned($c, "great_cookie", "blueberry", $secret, [
        "path" => "/",
        "secure" => true,
        "httpOnly" => true,
        "maxAge" => 3600,
    ]);

    return $c->json([
        "allSignedCookies" => $allSignedCookies,
        "setSigned" => "great_cookie=blueberry (signed)",
    ]);
});

$app->get("/delete-signed-cookie", function ($c) {
    $name = $c->req->query("name");
    if (!$name) {
        return $c->json(["error" => "Cookie name is required"], 400);
    }

    $deletedValue = Cookie::delete($c, $name);
    return $c->json(["deletedSignedCookie" => $deletedValue]);
});

$app->run();
