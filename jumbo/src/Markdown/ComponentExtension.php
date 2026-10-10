<?php

namespace Dumbo\Jumbo\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * Adds ::: components to Markdown:
 *
 *   ::: callout type="tip" title="Heads up"
 *   Any **Markdown**, including other components.
 *   :::
 */
class ComponentExtension implements ExtensionInterface
{
    /**
     * @param callable(string $name, array $attributes, string $slot): string $render
     */
    public function __construct(private $render)
    {
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addBlockStartParser(new ComponentStartParser(), 80)
            ->addRenderer(Component::class, new ComponentRenderer($this->render));
    }
}
