<?php

namespace Dumbo\Jumbo;

/**
 * RSS 2.0 feeds. Site::rss() fills in the channel from the site's config, so
 * a feed page can be:
 *
 *   return new Page(render: fn($context, Site $site) => $site->rss(
 *       array_map(fn($post) => [
 *           "title" => $post["title"],
 *           "link" => "/blog/{$post["slug"]}",
 *           "date" => $post["meta"]["date"],
 *       ], $posts),
 *   ));
 */
final class Feed
{
    /**
     * @param array{title: string, link: string, description?: string, language?: string} $channel
     * @param iterable<array{title: string, link: string, description?: string, content?: string, date?: mixed, guid?: string, author?: string, categories?: array<string>}> $items
     */
    public static function rss(array $channel, iterable $items): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<rss version=\"2.0\" xmlns:content=\"http://purl.org/rss/1.0/modules/content/\">\n<channel>\n"
            . self::element("title", $channel["title"])
            . self::element("link", $channel["link"])
            . self::element("description", $channel["description"] ?? "")
            . (isset($channel["language"]) ? self::element("language", $channel["language"]) : "");

        foreach ($items as $item) {
            $xml .= "<item>\n"
                . self::element("title", $item["title"])
                . self::element("link", $item["link"])
                . self::element("guid", $item["guid"] ?? $item["link"])
                . (isset($item["description"]) ? self::element("description", $item["description"]) : "")
                . (isset($item["content"]) ? "<content:encoded><![CDATA[" . str_replace("]]>", "]]]]><![CDATA[>", $item["content"]) . "]]></content:encoded>\n" : "")
                . (isset($item["date"]) ? self::element("pubDate", self::date($item["date"])) : "")
                . (isset($item["author"]) ? self::element("author", $item["author"]) : "")
                . implode("", array_map(fn($category) => self::element("category", $category), $item["categories"] ?? []))
                . "</item>\n";
        }

        return "$xml</channel>\n</rss>\n";
    }

    private static function element(string $name, string $value): string
    {
        return "<$name>" . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") . "</$name>\n";
    }

    /**
     * Front matter dates come from YAML as timestamps
     */
    private static function date(mixed $date): string
    {
        $date = match (true) {
            $date instanceof \DateTimeInterface => $date,
            is_int($date) => (new \DateTimeImmutable())->setTimestamp($date),
            default => new \DateTimeImmutable((string) $date),
        };

        return $date->format(DATE_RSS);
    }
}
