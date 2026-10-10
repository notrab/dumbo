<?php

use Docs\Site;
use Dumbo\Context;
use Dumbo\Jumbo\Page;

// Sitemaps need absolute URLs, so this is only built with a url in config.php
return new Page(
    prerender: fn(Site $site) => (bool) $site->config["url"],
    render: function (Context $context, Site $site) {
        $url = rtrim($site->config["url"] ?? "", "/");

        if (!$url) {
            return $context->notFound();
        }

        // The home page first, then the docs
        $pages = array_filter(array_map($site->find(...), $site->slugs()), fn($page) => empty($page["meta"]["noindex"]));
        $locations = ["$url/", ...array_map(fn($page) => "$url/docs/{$page["slug"]}", $pages)];
        $entries = implode("", array_map(fn($loc) => "  <url><loc>" . e($loc) . "</loc></url>\n", $locations));

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n$entries</urlset>\n";
    },
);
