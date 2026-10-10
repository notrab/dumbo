<?php

return new Dumbo\Jumbo\Page(
    paths: fn() => [["path" => "a/b"], ["path" => "c d"]],
    render: fn($context) => "file " . $context->req->param("path"),
);
