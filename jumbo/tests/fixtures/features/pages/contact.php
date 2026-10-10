<?php

use Dumbo\Context;
use Dumbo\Jumbo\Action;
use Dumbo\Jumbo\Page;
use Dumbo\Jumbo\Site;

return new Page(
    render: function (Context $context, Site $site) {
        $action = $site->action($context);
        $subscribed = $site->action($context, "subscribe");

        return $site->view("html", ["context" => $context, "title" => "Contact", "slot" => ""])
            . "<p>" . ($action ? ($action->ok() ? "sent: " . e(json_encode($action->data)) : "error: " . e($action->error("email"))) : "form") . "</p>"
            . "<input name=\"email\" value=\"" . e($action?->old("email")) . "\">"
            . ($subscribed ? "<p>subscribed</p>" : "");
    },
    actions: [
        "default" => function (array $input) {
            if (!filter_var($input["email"] ?? "", FILTER_VALIDATE_EMAIL)) {
                return Action::fail(["email" => "Enter an email address"]);
            }

            return ["to" => $input["email"]];
        },
        "subscribe" => fn(array $input) => ["list" => "news"],
        "redirect" => fn(array $input, Context $context) => $context->redirect("/contact?sent=1", 303),
    ],
);
