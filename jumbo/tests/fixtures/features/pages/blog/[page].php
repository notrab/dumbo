<?php

use Dumbo\Jumbo\Page;

return new Page(
    paths: fn($site) => $site->paginate(["a", "b", "c", "d", "e"], 2),
    render: fn($context, $site, $props) => $context->json([
        "items" => $props["page"]->items,
        "current" => $props["page"]->current,
        "last" => $props["page"]->last,
        "previous" => $props["page"]->previous(),
        "next" => $props["page"]->next(),
        "start" => $props["page"]->start(),
        "end" => $props["page"]->end(),
    ]),
);
