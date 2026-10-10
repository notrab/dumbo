<?php

use Dumbo\Context;
use Dumbo\Jumbo\Page;

return new Page(
    paths: fn(Blog $site) => array_map(
        fn(string $tag) => ["params" => ["tag" => $tag], "props" => ["posts" => $site->posts($tag)]],
        $site->tags()
    ),
    render: function (Context $context, Blog $site, array $props) {
        $tag = $context->req->param("tag");

        return $site->view("layout", [
            "context" => $context,
            "title" => "Posts tagged $tag",
            "slot" => "<h1>Tagged <span class=\"tag\">" . e($tag) . "</span></h1>" . $site->view("list", ["posts" => $props["posts"] ?? []]),
        ]);
    },
);
