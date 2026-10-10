<?php

declare(strict_types=1);

use Dumbo\Context;
use Dumbo\Jumbo\Page;
use Dumbo\Jumbo\Site;

// A post from content/
return new Page(
    paths: fn(Site $site) => array_map(fn($slug) => ["slug" => $slug], $site->slugs()),
    render: function (Context $context, Site $site) {
        $post = $site->find($context->req->param("slug")) ?? null;

        return $post ? $site->view("article", ["page" => $post, "seo" => ["title" => $post["title"]]]) : $context->notFound();
    },
);
