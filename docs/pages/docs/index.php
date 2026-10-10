<?php

use Docs\Site;
use Dumbo\Context;
use Dumbo\Jumbo\Page;

// /docs goes to the first page in the sidebar
return new Page(
    render: function (Context $context, Site $site) {
        $first = array_key_first(array_merge(...array_values($site->sidebar())) ?: [null => null]);

        return $first ? $context->redirect("/docs/$first") : $context->notFound();
    },
);
