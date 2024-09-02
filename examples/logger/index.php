<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;

$app = new Dumbo();

$logger = new MonologLogger("example");
$handler = new StreamHandler("php://stdout");
$formatter = new LineFormatter(
    "[%datetime%] %channel%.%level_name%: %message%\n",
    "Y-m-d H:i:s.u"
);
$handler->setFormatter($formatter);
$logger->pushHandler($handler);

$app->use(Logger::logger($logger));

$app->get("/", function ($context) {
    return $context->html("<h1>We've just logged something on the console!</h1>");
});

$userData = [
    [
        "id" => 1,
        "name" => "Jamie Barton",
        "email" => "jamie@notrab.dev",
    ],
];

$user = new Dumbo();

$user->get("/", function ($c) use ($userData) {
    return $c->json($userData);
});

$user->get("/:id", function ($c) use ($userData) {
    $id = (int) $c->req->param("id");

    $user =
        array_values(array_filter($userData, fn($u) => $u["id"] === $id))[0] ??
        null;

    if (!$user) {
        return $c->json(["error" => "User not found"], 404);
    }

    return $c->json($user);
});

$app->route("/users", $user);

$app->run();
