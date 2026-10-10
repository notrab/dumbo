<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

$app = new Dumbo();

$nav = require __DIR__ . "/nav.php";

$environment = new Environment([
    "heading_permalink" => [
        "html_class" => "anchor",
        "id_prefix" => "",
        "fragment_prefix" => "",
        "apply_id_to_heading" => true,
        "insert" => "after",
        "symbol" => "#",
        "min_heading_level" => 2,
        "max_heading_level" => 3,
    ],
]);
$environment->addExtension(new CommonMarkCoreExtension());
$environment->addExtension(new GithubFlavoredMarkdownExtension());
$environment->addExtension(new HeadingPermalinkExtension());

$markdown = new MarkdownConverter($environment);

function view(string $name, array $data = []): string
{
    extract($data);
    ob_start();
    require __DIR__ . "/views/$name.php";

    return ob_get_clean();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

$app->use(function ($context, $next) use ($nav) {
    $context->render(fn(string $name, array $data = []) => view("layout", [
        ...$data,
        "nav" => $nav,
        "body" => view($name, [...$data, "nav" => $nav]),
    ]));

    return $next($context);
});

$app->get("/", fn($context) => $context->view("home", [
    "title" => "Dumbo - A lightweight, friendly PHP framework for HTTP",
]));

$app->get("/docs", fn($context) => $context->redirect("/docs/introduction"));

$app->get("/docs/:slug", function ($context) use ($nav, $markdown) {
    $slug = $context->req->param("slug");
    $pages = array_merge(...array_values($nav));
    $file = __DIR__ . "/content/$slug.md";

    if (!isset($pages[$slug]) || !is_file($file)) {
        $response = $context->view("not-found", ["title" => "Page not found"]);

        return $response->withStatus(404);
    }

    $html = (string) $markdown->convert(file_get_contents($file));

    // Build "On this page" from the h2 and h3 headings
    preg_match_all('/<h([23]) id="([^"]+)">(.*?)<\/h\1>/s', $html, $matches, PREG_SET_ORDER);
    $toc = array_map(fn($m) => [
        "level" => (int) $m[1],
        "id" => $m[2],
        "text" => trim(html_entity_decode(strip_tags(preg_replace('/<a[^>]*class="anchor".*?<\/a>/s', "", $m[3])))),
    ], $matches);

    $slugs = array_keys($pages);
    $index = array_search($slug, $slugs, true);
    $link = fn($i) => isset($slugs[$i]) ? ["slug" => $slugs[$i], "title" => $pages[$slugs[$i]]] : null;

    return $context->view("page", [
        "title" => $pages[$slug] . " - Dumbo",
        "slug" => $slug,
        "content" => $html,
        "toc" => $toc,
        "prev" => $link($index - 1),
        "next" => $link($index + 1),
    ]);
});

$app->run();
