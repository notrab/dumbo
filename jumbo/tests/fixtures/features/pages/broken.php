<?php

use Dumbo\Jumbo\Page;

return new Page(
    prerender: false,
    render: fn() => throw new RuntimeException("The page broke"),
);
