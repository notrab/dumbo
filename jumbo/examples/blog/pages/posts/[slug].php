<?php

use Dumbo\Context;
use Dumbo\Jumbo\Page;

// One page per post. Each path brings its post along as a prop, so render
// doesn't have to look it up again.
return new Page(
    paths: fn(Blog $site) => array_map(
        fn(array $post) => ["params" => ["slug" => $post["slug"]], "props" => ["post" => $post]],
        $site->posts()
    ),
    render: fn(Context $context, Blog $site, array $props) => isset($props["post"])
        ? $site->view("post", ["context" => $context, "post" => $props["post"]])
        : $context->notFound(),
);
