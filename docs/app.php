<?php

/**
 * The docs app. index.php runs it as a server, and build.php renders every
 * page of it to static files.
 */

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\StaticFiles;
use GuzzleHttp\Psr7\Response;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

const GITHUB_URL = "https://github.com/notrab/dumbo";
const EXAMPLES_DIR = __DIR__ . "/../examples";

$app = new Dumbo();

$nav = require __DIR__ . "/nav.php";
$pages = array_merge(...array_values($nav));

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

/**
 * Render a view inside the layout
 */
function page(string $name, array $data = []): string
{
    $nav = require __DIR__ . "/nav.php";

    return view("layout", [...$data, "body" => view($name, [...$data, "nav" => $nav])]);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

/**
 * Every folder in the repo's examples directory, keyed by name, with a title
 * made from the folder name and a description from the README
 */
function examples(): array
{
    $words = [
        "cors" => "CORS", "docker" => "Docker", "fpm" => "FPM", "frankenphp" => "FrankenPHP",
        "html" => "HTML", "htmx" => "HTMX", "http" => "HTTP", "id" => "ID", "json" => "JSON",
        "jwt" => "JWT", "nginx" => "Nginx", "php" => "PHP", "txt" => "TXT", "blade" => "Blade",
        "latte" => "Latte", "smarty" => "Smarty", "turso" => "Turso", "twig" => "Twig",
    ];
    $examples = [];

    foreach (glob(EXAMPLES_DIR . "/*", GLOB_ONLYDIR) as $dir) {
        $name = basename($dir);
        $readme = is_file("$dir/README.md") ? file_get_contents("$dir/README.md") : "";

        // The first block of text that isn't a heading, code or a list
        $blocks = preg_split('/\n\s*\n/', trim($readme));
        $description = current(array_filter($blocks, fn($b) => $b !== "" && !preg_match('/^\s*(#|```|[-*>]|\d+\.)/', $b)));
        $description = preg_replace('/\.\s+(?=[A-Z]).*$/s', ".", trim((string) $description));

        $examples[$name] = [
            "name" => $name,
            "dir" => $dir,
            "title" => ucfirst(implode(" ", array_map(fn($w) => $words[$w] ?? $w, explode("-", $name)))),
            // "This example demonstrates how to use X" reads better on a card as "Use X"
            "description" => ucfirst(preg_replace('/^This (?:example|repository) (?:demonstrates|showcases|shows|provides) how to /i', "", $description)) ?: "A Dumbo example.",
            "readme" => $readme,
        ];
    }

    return $examples;
}

/**
 * Inline markdown: escape the text and turn `backticks` into code
 */
function inline(string $text): string
{
    return preg_replace('/`([^`]+)`/', '<code>$1</code>', e(trim(preg_replace('/\s+/', " ", $text))));
}

/**
 * The previous and next items in a list of [key => title]
 */
function siblings(array $items, string $key, string $base): array
{
    $keys = array_keys($items);
    $index = array_search($key, $keys, true);
    $link = fn($i) => isset($keys[$i]) ? ["href" => "$base/$keys[$i]", "title" => $items[$keys[$i]]] : null;

    return ["prev" => $link($index - 1), "next" => $link($index + 1)];
}

$app->use(StaticFiles::serve(__DIR__ . "/public"));

$app->use(function ($context, $next) {
    $context->render(page(...));

    return $next($context);
});

// Swap any plain 404, including unknown routes, for the styled page
$app->use(function ($context, $next) {
    $response = $next($context);

    if ($response->getStatusCode() !== 404 || str_starts_with($response->getHeaderLine("Content-Type"), "text/html")) {
        return $response;
    }

    return new Response(404, ["Content-Type" => "text/html"], page("not-found", ["title" => "Page not found - Dumbo"]));
});

$app->get("/", fn($context) => $context->view("home", [
    "title" => "Dumbo - A lightweight, friendly PHP framework for HTTP",
]));

$app->get("/docs", fn($context) => $context->redirect("/docs/introduction"));

$app->get("/docs/examples", function ($context) use ($pages) {
    return $context->view("page", [
        "title" => "Examples - Dumbo",
        "slug" => "examples",
        "content" => view("examples", ["examples" => examples()]),
        "toc" => [],
        "editUrl" => GITHUB_URL . "/tree/main/examples",
        ...siblings($pages, "examples", "/docs"),
    ]);
});

$app->get("/docs/examples/:name", function ($context) use ($markdown) {
    $examples = examples();
    $example = $examples[$context->req->param("name")] ?? null;

    if (!$example) {
        return $context->text("Not found", 404);
    }

    // Show the README without its title, then the source files
    $readme = preg_replace('/^#\s.*\n/', "", ltrim($example["readme"]), 1);
    $files = array_filter(["index.php", "public/index.php", "Dockerfile"], fn($f) => is_file("{$example["dir"]}/$f"));

    return $context->view("page", [
        "title" => "{$example["title"]} example - Dumbo",
        "slug" => "examples",
        "content" => view("example", [
            "example" => $example,
            "readme" => (string) $markdown->convert($readme),
            "files" => array_map(fn($f) => ["name" => $f, "source" => file_get_contents("{$example["dir"]}/$f")], $files),
            "githubUrl" => GITHUB_URL . "/tree/main/examples/{$example["name"]}",
        ]),
        "toc" => [],
        "editUrl" => GITHUB_URL . "/edit/main/examples/{$example["name"]}/README.md",
        ...siblings(array_column($examples, "title", "name"), $example["name"], "/docs/examples"),
    ]);
});

$app->get("/docs/:slug", function ($context) use ($pages, $markdown) {
    $slug = $context->req->param("slug");
    $file = __DIR__ . "/content/$slug.md";

    if (!isset($pages[$slug]) || !is_file($file)) {
        return $context->text("Not found", 404);
    }

    $html = (string) $markdown->convert(file_get_contents($file));

    // Build "On this page" from the h2 and h3 headings
    preg_match_all('/<h([23]) id="([^"]+)">(.*?)<\/h\1>/s', $html, $matches, PREG_SET_ORDER);
    $toc = array_map(fn($m) => [
        "level" => (int) $m[1],
        "id" => $m[2],
        "text" => trim(html_entity_decode(strip_tags(preg_replace('/<a[^>]*class="anchor".*?<\/a>/s', "", $m[3])))),
    ], $matches);

    return $context->view("page", [
        "title" => "$pages[$slug] - Dumbo",
        "slug" => $slug,
        "content" => $html,
        "toc" => $toc,
        "editUrl" => GITHUB_URL . "/edit/main/docs/content/$slug.md",
        ...siblings($pages, $slug, "/docs"),
    ]);
});

return $app;
