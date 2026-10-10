<?php

use Dumbo\Context;
use Dumbo\Jumbo\Page;

return new Page(render: fn(Context $context, Blog $site) => $site->rss(array_map(fn(array $post) => [
    "title" => $post["title"],
    "link" => "/posts/{$post["slug"]}",
    "description" => $post["meta"]["description"] ?? "",
    "content" => $post["html"],
    "date" => $post["meta"]["date"],
    "categories" => $post["meta"]["tags"] ?? [],
], $site->posts())));
