<?php

namespace Dumbo\Jumbo\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Tempest\Highlight\Highlighter;
use Tempest\Highlight\Themes\InlineTheme;

/**
 * Highlights fenced code on the server, keeping CommonMark's own
 * <pre><code class="language-php"> markup. Languages the highlighter doesn't
 * know are escaped as plain text.
 */
class CodeRenderer implements NodeRendererInterface
{
    public function __construct(private Highlighter $highlighter)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        FencedCode::assertInstanceOf($node);

        $language = strtolower(preg_replace('/[^\w+-].*$/s', "", $node->getInfoWords()[0] ?? ""));
        $class = $language === "" ? "" : " class=\"language-$language\"";
        $code = $this->highlighter->parse($node->getLiteral(), $language);

        // Inline themes style the block itself, so it reads without any CSS
        $theme = $this->highlighter->getTheme();
        $pre = $theme instanceof InlineTheme ? $theme->preBefore($this->highlighter) : "<pre>";

        return "$pre<code$class>$code</code></pre>\n";
    }
}
