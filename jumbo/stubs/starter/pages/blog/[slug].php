<?php

use Dumbo\Context;
use Dumbo\Jumbo\Page;
use Dumbo\Jumbo\Site;

// One page for each post in content/, built ahead of time
return new Page(
    paths: fn(Site $site) => array_map(
        fn(string $slug) => ["params" => ["slug" => $slug], "props" => ["post" => $site->find($slug)]],
        $site->slugs()
    ),
    render: fn(Context $context, Site $site, array $props) => isset($props["post"])
        ? $site->view("post", ["context" => $context, "post" => $props["post"]])
        : $context->notFound(),
);
