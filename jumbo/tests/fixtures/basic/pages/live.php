<?php

return new Dumbo\Jumbo\Page(
    prerender: false,
    render: fn($context) => $context->text("live"),
);
