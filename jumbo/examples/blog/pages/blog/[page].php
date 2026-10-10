<?php

use Dumbo\Context;
use Dumbo\Jumbo\Page;

// /blog/1, /blog/2 and so on, two posts to a page
return new Page(
    paths: fn(Blog $site) => $site->paginate($site->posts(), 2),
    render: function (Context $context, Blog $site, array $props) {
        $page = $props["page"] ?? null;

        if ($page === null) {
            return $context->notFound();
        }

        return $site->view("archive", ["context" => $context, "page" => $page]);
    },
);
