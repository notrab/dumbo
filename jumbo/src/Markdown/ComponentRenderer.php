<?php

namespace Dumbo\Jumbo\Markdown;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

class ComponentRenderer implements NodeRendererInterface
{
    /**
     * @param callable(string $name, array $attributes, string $slot): string $render
     */
    public function __construct(private $render)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        assert($node instanceof Component);

        return ($this->render)($node->name, $node->attributes, $childRenderer->renderNodes($node->children()));
    }
}
