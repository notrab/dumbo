<?php

use Docs\Site;
use Dumbo\Context;
use Dumbo\Jumbo\Page;

// Every Markdown file in content/ is a page at /docs/{file name}
return new Page(
    paths: fn(Site $site) => array_map(fn($slug) => ["slug" => $slug], $site->slugs()),
    render: function (Context $context, Site $site) {
        $slug = $context->req->param("slug");
        $page = $site->find($slug);

        if (!$page) {
            return $context->notFound();
        }

        $editUrl = $site->config["editUrl"] ? str_replace("{path}", "$slug.md", $site->config["editUrl"]) : null;

        return $site->view($page["meta"]["layout"] ?? "page", [
            "page" => $page,
            "seo" => $site->seo($page["meta"], "/docs/$slug", $page["title"]),
            "editUrl" => $editUrl,
            ...$site->siblings($slug),
        ]);
    },
);
