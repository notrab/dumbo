<?php

namespace Dumbo\Jumbo\Markdown;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A ::: name key="value" block, rendered by components/{name}.php
 */
class Component extends AbstractBlock
{
    public function __construct(public string $name, public array $attributes = [])
    {
        parent::__construct();
    }
}
