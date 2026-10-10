<?php

namespace Dumbo\Jumbo;

/**
 * Small changes to rendered pages, made after every template has run
 */
final class Html
{
    /**
     * Move <style hoist> and <script hoist> tags into the <head>, once each.
     * Components can ship their own CSS and JS this way, and a page that
     * uses a component ten times still loads it once.
     */
    public static function hoist(string $html): string
    {
        $tags = [];

        $html = preg_replace_callback(
            '#<(style|script)(\s[^>]*)?>(.*?)</\1>#is',
            function (array $m) use (&$tags): string {
                $attributes = $m[2] ?? "";

                if (!preg_match('/\shoist(?:=(?:""|\'\'|hoist))?(?=[\s>]|$)/i', $attributes, $flag)) {
                    return $m[0];
                }

                $attributes = str_replace($flag[0], "", $attributes);
                $tags["<$m[1]$attributes>$m[3]</$m[1]>"] = true;

                return "";
            },
            $html
        );

        return $tags === [] ? $html : self::inject($html, implode("\n", array_keys($tags)));
    }

    /**
     * Add HTML at the end of the <head>, or at the start of a fragment
     */
    public static function inject(string $html, string $tags, string $before = "</head>"): string
    {
        $position = stripos($html, $before);

        if ($position === false) {
            return $before === "</body>" ? $html . $tags : $tags . $html;
        }

        return substr_replace($html, $tags . "\n", $position, 0);
    }
}
