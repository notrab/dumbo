<?php

use Dumbo\Context;
use Dumbo\Jumbo\Page;
use Dumbo\Jumbo\Site;

return new Page(render: fn(Context $context, Site $site) => $site->rss(array_map(fn(string $slug) => [
    "title" => $site->find($slug)["title"],
    "link" => "/blog/$slug",
    "description" => $site->find($slug)["meta"]["description"] ?? "",
    "date" => $site->find($slug)["meta"]["date"] ?? null,
], $site->slugs())));
