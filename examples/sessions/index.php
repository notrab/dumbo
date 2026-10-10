<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Context;
use Dumbo\Dumbo;
use Dumbo\Middleware\Csrf;
use Dumbo\Middleware\Session;
use Dumbo\Session\CookieStore;
use Dumbo\Session\FileStore;

$app = new Dumbo();

// Files in storage/ by default. Set SESSION_SECRET (32+ characters) to keep
// the whole session in an encrypted cookie instead.
$store = getenv("SESSION_SECRET") ? new CookieStore(getenv("SESSION_SECRET")) : new FileStore(__DIR__ . "/storage");

$app->use(Session::session(["store" => $store, "lifetime" => 60 * 60]));

// The CSRF token lives in the session, so forms need no cookie of their own
$app->use(Csrf::csrf([
    "getToken" => fn(Context $context) => $context->session()->get("csrf_token"),
    "setToken" => fn(Context $context, string $token) => $context->session()->set("csrf_token", $token),
]));

// A pretend user list. Use password_hash() and a database for real.
const USERS = ["dumbo" => "peanuts"];

$app->get("/", function (Context $context) {
    $session = $context->session();
    $session->set("visits", $session->get("visits", 0) + 1);

    $user = $session->get("user");
    $notice = $session->get("notice");
    $token = $context->get("csrf_token");
    $escape = fn(?string $value) => htmlspecialchars($value ?? "", ENT_QUOTES);

    $form = $user
        ? "<p>Signed in as <strong>{$escape($user)}</strong>.</p>
           <form method='post' action='/logout'><input type='hidden' name='csrf_token' value='{$escape($token)}'><button>Sign out</button></form>"
        : "<form method='post' action='/login'>
             <input type='hidden' name='csrf_token' value='{$escape($token)}'>
             <label>Name <input name='name' value='dumbo'></label>
             <label>Password <input name='password' type='password' value='peanuts'></label>
             <button>Sign in</button>
           </form>";

    return $context->html("<!DOCTYPE html>
        <title>Sessions</title>
        <style>body { font: 16px/1.6 system-ui; max-width: 32rem; margin: 3rem auto; } .notice { padding: .5rem 1rem; background: #f5f0ff; border-left: 4px solid #8a2be2; } label { display: block; margin: .5rem 0; }</style>
        <h1>🐘 Sessions</h1>
        " . ($notice ? "<p class='notice'>{$escape($notice)}</p>" : "") . "
        <p>You've loaded this page {$session->get('visits')} " . ($session->get("visits") === 1 ? "time" : "times") . ".</p>
        $form");
});

$app->post("/login", function (Context $context) {
    $session = $context->session();
    $name = (string) ($context->req->body()["name"] ?? "");
    $password = (string) ($context->req->body()["password"] ?? "");

    if (!isset(USERS[$name]) || !hash_equals(USERS[$name], $password)) {
        $session->flash("notice", "That name and password don't match.");

        return $context->redirect("/", 303);
    }

    // A new ID on sign in, so one planted before now is useless
    $session->regenerate();
    $session->set("user", $name);
    $session->flash("notice", "Welcome back, $name.");

    return $context->redirect("/", 303);
});

$app->post("/logout", function (Context $context) {
    $context->session()->destroy();

    return $context->redirect("/", 303);
});

$app->run();
