<?php

/**
 * Everything specific to these docs. Pages live in content/, and the home
 * page is content/index.md.
 */
return [
    "name" => "Dumbo",

    // An emoji, or a path to an image in public/
    "logo" => "🐘",

    // A little sticker next to the logo, or null
    "badge" => "never forgets a route",

    // Where the site is hosted, for canonical URLs, social cards and the
    // sitemap. Include any sub-path, e.g. https://notrab.github.io/dumbo
    "url" => getenv("DOCS_URL") ?: null,

    "locale" => "en",

    // Defaults for every page. Override them in a page's front matter with
    // title, seo_title, description, image, canonical or noindex.
    "seo" => [
        "title" => "Dumbo - A lightweight, friendly PHP framework for HTTP",
        "titleTemplate" => "%s - Dumbo",
        "description" => "A lightweight, friendly PHP framework for HTTP, inspired by Hono.",
        "image" => "/og.jpeg",
        "twitter" => null,
    ],

    // Any CSS colours. The accent should be light, as text on it is dark.
    "theme" => [
        "primary" => "#8a2be2",
        "accent" => "#fcd96a",
    ],

    "links" => [
        ["text" => "Docs", "href" => "/docs"],
        ["text" => "Examples", "href" => "/docs/examples"],
    ],

    // Icons in the header: github, discord, x or bluesky
    "social" => [
        "discord" => "https://discord.gg/nAq2h9BfsU",
        "github" => "https://github.com/notrab/dumbo",
    ],

    // Sidebar groups, in reading order. Each slug is a file in content/.
    "sidebar" => [
        "🚀 Getting started" => ["introduction", "quickstart"],
        "🧠 Concepts" => ["routing", "context", "middleware", "error-handling"],
        "🧩 Middleware" => ["built-in-middleware"],
        "🧰 Helpers" => ["cookies", "jwt", "static-files"],
        "🗺️ Guides" => ["templates", "environments", "deployment", "upgrading"],
        "🧪 Examples" => ["examples"],
    ],

    // {path} is the page's file in content/. Set to null to hide edit links.
    "editUrl" => "https://github.com/notrab/dumbo/edit/main/docs/content/{path}",

    "footer" => [
        "title" => "Made with 🥜 and PHP",
        "text" => "Released under the MIT License. Elephants never forget, but you might, so bookmark this.",
    ],

    // Sprinkled on copy buttons and thrown by the home page mascot
    "confetti" => "🥜",

    // "cdn" loads fonts and scripts from hop.js, "local" serves them from
    // node_modules. Defaults to the CDN in development and local in
    // production, and static builds are always local.
    "assets" => null,
];
