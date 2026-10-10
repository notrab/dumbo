<?php

namespace Dumbo\Jumbo\Markdown;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Starts a component on a line like `::: callout type="tip"`
 */
class ComponentStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        if (!preg_match('/^(:{3,})\s*([a-z][\w-]*)(.*)$/i', $cursor->getRemainder(), $match)) {
            return BlockStart::none();
        }

        $cursor->advanceToEnd();

        return BlockStart::of(new ComponentParser(strlen($match[1]), $match[2], self::attributes($match[3])))->at($cursor);
    }

    /**
     * Parse key="value", key='value', key=value and bare key (true) pairs
     */
    public static function attributes(string $input): array
    {
        preg_match_all('/([\w-]+)(?:=(?:"([^"]*)"|\'([^\']*)\'|(\S+)))?/', $input, $matches, PREG_SET_ORDER);

        $attributes = [];

        foreach ($matches as $match) {
            $attributes[$match[1]] = isset($match[2]) ? implode("", array_slice($match, 2)) : true;
        }

        return $attributes;
    }
}
