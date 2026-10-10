<?php

use Dumbo\Jumbo\Page;

return new Page(
    paths: fn() => [
        ["params" => ["tag" => "php"], "props" => ["count" => 3]],
        ["tag" => "plain"],
    ],
    render: fn($context, $site, $props) => "<p>" . e($context->req->param("tag")) . ": " . ($props["count"] ?? "none") . "</p>",
);
