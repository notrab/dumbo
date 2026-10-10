<?php

require __DIR__ . "/vendor/autoload.php";

return new Dumbo\Jumbo\Site(__DIR__, [
    "name" => "{{name}}",
    // Where the site will live, for canonical URLs, the sitemap and the feed
    "url" => getenv("SITE_URL") ?: null,
    "seo" => [
        "titleTemplate" => "%s - {{name}}",
        "description" => "A site made with Jumbo.",
    ],
    "prefetch" => true,
    "viewTransitions" => true,
]);
