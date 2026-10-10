<?php

namespace Docs;

/**
 * The docs' own config defaults and the helpers its templates use for the
 * sidebar and previous and next links
 */
class Site extends \Dumbo\Jumbo\Site
{
    public const DEFAULTS = [
        ...parent::DEFAULTS,
        "name" => "Docs",
        "logo" => "📘",
        "badge" => null,
        "theme" => [
            "primary" => "#8a2be2",
            "accent" => "#fcd96a",
        ],
        "links" => [],
        "social" => [],
        "sidebar" => [],
        "editUrl" => null,
        "footer" => [
            "title" => null,
            "text" => null,
        ],
        "confetti" => "✨",
        "dirs" => [
            ...parent::DEFAULTS["dirs"],
            "views" => "theme",
        ],
    ];

    /**
     * The sidebar groups from config, as [group => [slug => title]]
     */
    public function sidebar(): array
    {
        return array_map(
            fn($slugs) => array_combine($slugs, array_map(fn($slug) => $this->find($slug)["title"] ?? $slug, $slugs)),
            $this->config["sidebar"]
        );
    }

    /**
     * The pages before and after a page in the sidebar
     */
    public function siblings(string $slug): array
    {
        $pages = array_merge(...array_values($this->sidebar()));
        $slugs = array_keys($pages);
        $index = array_search($slug, $slugs, true);
        $link = fn($i) => $index !== false && isset($slugs[$i]) ? ["href" => "/docs/$slugs[$i]", "title" => $pages[$slugs[$i]]] : null;

        return ["prev" => $link($index - 1), "next" => $link($index + 1)];
    }
}
