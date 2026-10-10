<?php

return new Dumbo\Site\Page(
    prerender: false,
    render: fn($context) => $context->text("live"),
);
