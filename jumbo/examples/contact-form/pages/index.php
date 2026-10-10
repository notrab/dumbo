<?php

use Dumbo\Context;
use Dumbo\Jumbo\Action;
use Dumbo\Jumbo\Page;
use Dumbo\Jumbo\Site;

/**
 * Two forms on one page. Each posts back here, its action runs, and the page
 * renders again with the result, or the action redirects.
 */
return new Page(
    render: fn(Context $context, Site $site) => $site->view("contact", [
        "context" => $context,
        "contact" => $site->action($context, "default"),
        "subscribe" => $site->action($context, "subscribe"),
    ]),
    actions: [
        // <form method="post"> with no _action field runs this one
        "default" => function (array $input, Context $context) {
            $name = trim((string) ($input["name"] ?? ""));
            $email = trim((string) ($input["email"] ?? ""));
            $message = trim((string) ($input["message"] ?? ""));
            $errors = [];

            if ($name === "") {
                $errors["name"] = "Tell us your name";
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors["email"] = "That doesn't look like an email address";
            }

            if (mb_strlen($message) < 10) {
                $errors["message"] = "Say a little more, at least 10 characters";
            }

            if ($errors) {
                return Action::fail($errors);
            }

            // Somewhere to keep messages for the example. Send an email for real.
            if (!is_dir(__DIR__ . "/../storage")) {
                mkdir(__DIR__ . "/../storage", 0700, true);
            }

            file_put_contents(__DIR__ . "/../storage/messages.jsonl", json_encode(compact("name", "email", "message")) . "\n", FILE_APPEND);

            // Redirect after a post, so refreshing doesn't send it twice
            return $context->redirect("/thanks", 303);
        },

        // Forms with <input type="hidden" name="_action" value="subscribe">
        "subscribe" => function (array $input) {
            $email = trim((string) ($input["email"] ?? ""));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return Action::fail(["email" => "Enter an email address to subscribe"]);
            }

            return ["message" => "Subscribed $email. Expect peanuts."];
        },
    ],
);
