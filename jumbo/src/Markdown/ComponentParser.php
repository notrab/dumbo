<?php

namespace Dumbo\Jumbo\Markdown;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

/**
 * Collects Markdown until a closing fence with at least as many colons as the
 * opening one, so components nest by adding colons to the outer fence
 */
class ComponentParser extends AbstractBlockContinueParser
{
    private Component $block;

    public function __construct(private int $fenceLength, string $name, array $attributes)
    {
        $this->block = new Component($name, $attributes);
    }

    public function getBlock(): Component
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if (!$cursor->isIndented() && preg_match('/^(:{3,})\s*$/', $cursor->getRemainder(), $match) && strlen($match[1]) >= $this->fenceLength) {
            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }
}
