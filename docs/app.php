<?php

/**
 * The docs app. index.php runs it as a server, and build.php renders every
 * page of it to static files. Configure it in config.php.
 */

require __DIR__ . "/vendor/autoload.php";

use Docs\Site;
use Dumbo\Dumbo;
use Dumbo\Helpers\StaticFiles;
use GuzzleHttp\Psr7\Response;

$app = new Dumbo(isset($production) ? Dumbo::ENV_PRODUCTION : null);
$site = new Site(__DIR__, require __DIR__ . "/config.php", $app->getEnvironment() === Dumbo::ENV_PRODUCTION);

$app->use(StaticFiles::serve(__DIR__ . "/public"));

// Packages loaded with $site->asset() are served from node_modules when not using the CDN
if ($site->assetMode() === "local") {
    $app->get("/vendor/{path:.+}", StaticFiles::serve(__DIR__ . "/node_modules"));
}

$app->use(function ($context, $next) use ($site) {
    $context->render($site->page(...));

    return $next($context);
});

// Swap any plain 404, including unknown routes, for the styled page
$app->use(function ($context, $next) use ($site) {
    $response = $next($context);

    if ($response->getStatusCode() !== 404 || str_starts_with($response->getHeaderLine("Content-Type"), "text/html")) {
        return $response;
    }

    $html = $site->page("not-found", ["seo" => $site->seo(["noindex" => true], "/404", "Page not found")]);

    return new Response(404, ["Content-Type" => "text/html"], $html);
});

$app->get("/", function ($context) use ($site) {
    $page = $site->find("index") ?? ["meta" => [], "html" => "", "toc" => []];

    return $context->view($page["meta"]["layout"] ?? "home", [
        "page" => $page,
        "seo" => $site->seo($page["meta"], "/"),
    ]);
});

$app->get("/docs", function ($context) use ($site) {
    $first = array_key_first(array_merge(...array_values($site->sidebar())) ?: [null => null]);

    return $first ? $context->redirect("/docs/$first") : $context->text("Not found", 404);
});

$app->get("/docs/:slug", function ($context) use ($site) {
    $slug = $context->req->param("slug");
    $page = $slug === "index" ? null : $site->find($slug);

    if (!$page) {
        return $context->text("Not found", 404);
    }

    $editUrl = $site->config["editUrl"] ? str_replace("{path}", "$slug.md", $site->config["editUrl"]) : null;

    return $context->view($page["meta"]["layout"] ?? "page", [
        "page" => $page,
        "seo" => $site->seo($page["meta"], "/docs/$slug", $page["title"]),
        "editUrl" => $editUrl,
        ...$site->siblings($slug),
    ]);
});

$app->get("/sitemap.xml", function ($context) use ($site) {
    $url = rtrim($site->config["url"] ?? "", "/");

    if (!$url) {
        return $context->text("Set url in config.php to generate a sitemap", 404);
    }

    // The home page first, then the rest
    $slugs = array_unique(["index", ...$site->slugs()]);
    $pages = array_filter(
        array_map(fn($slug) => $site->find($slug), $slugs),
        fn($page) => empty($page["meta"]["noindex"])
    );
    $locations = array_map(fn($page) => $page["slug"] === "index" ? "$url/" : "$url/docs/{$page["slug"]}", $pages);
    $entries = implode("", array_map(fn($loc) => "  <url><loc>" . e($loc) . "</loc></url>\n", $locations));

    return $context->send(
        "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n$entries</urlset>\n",
        "application/xml"
    );
});

$app->get("/robots.txt", function ($context) use ($site) {
    $url = rtrim($site->config["url"] ?? "", "/");

    return $context->text("User-agent: *\nAllow: /\n" . ($url ? "\nSitemap: $url/sitemap.xml\n" : ""));
});

return $app;
