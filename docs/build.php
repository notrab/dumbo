<?php

/**
 * Render every page of the docs to static files in dist/, ready to upload to
 * any static host or CDN. Fonts and scripts are bundled from node_modules,
 * so the built site makes no requests to other hosts.
 *
 *   php build.php                              Build for the root of a domain
 *   php build.php --url=https://example.com    Set the site URL for SEO
 *   php build.php --base=/dumbo                Build for a sub-path
 */

use GuzzleHttp\Psr7\ServerRequest;

$options = getopt("", ["base:", "url:"]);

if (isset($options["url"])) {
    putenv("DOCS_URL=" . $options["url"]);
}

$production = true;
$app = require __DIR__ . "/app.php";

$out = __DIR__ . "/dist";
$base = rtrim($options["base"] ?? parse_url($site->config["url"] ?? "", PHP_URL_PATH) ?? "", "/");

if (!is_file(__DIR__ . "/public/app.css")) {
    fwrite(STDERR, "public/app.css is missing. Run `npm run build` first.\n");
    exit(1);
}

$paths = [
    "/",
    "/docs",
    ...array_map(fn($slug) => "/docs/$slug", array_diff($site->slugs(), ["index"])),
    "/robots.txt",
    ...($site->config["url"] ? ["/sitemap.xml"] : []),
];

// Prefix root-relative links and assets with the base path
$rebase = fn(string $html) => $base === ""
    ? $html
    : preg_replace('#((?:href|src|action)=["\']?)/(?!/)#', "$1$base/", $html);

$write = function (string $file, string $contents) use ($out) {
    @mkdir(dirname("$out/$file"), 0777, true);
    file_put_contents("$out/$file", $contents);
};

$vendor = [];

exec("rm -rf " . escapeshellarg($out));

foreach ($paths as $path) {
    $response = $app->handle(new ServerRequest("GET", $path));
    $status = $response->getStatusCode();
    $body = (string) $response->getBody();

    // Pages become path/index.html, so /docs/routing works on any static host
    $file = pathinfo($path, PATHINFO_EXTENSION) ? ltrim($path, "/") : ltrim("$path/index.html", "/");

    if ($status >= 300 && $status < 400) {
        // Static hosts can't redirect, so send the browser on with a meta refresh
        $location = e($base . $response->getHeaderLine("Location"));
        $write($file, "<!DOCTYPE html><meta charset=\"utf-8\"><meta http-equiv=\"refresh\" content=\"0; url=$location\"><link rel=\"canonical\" href=\"$location\"><a href=\"$location\">Redirecting...</a>\n");
    } elseif ($status === 200) {
        preg_match_all('#"/vendor/([^"]+)"#', $body, $matches);
        $vendor = [...$vendor, ...$matches[1]];
        $write($file, str_ends_with($file, ".html") ? $rebase($body) : $body);
    } else {
        fwrite(STDERR, "GET $path responded with $status\n");
        exit(1);
    }

    echo "  $path\n";
}

// Most static hosts serve 404.html for missing pages
$write("404.html", $rebase((string) $app->handle(new ServerRequest("GET", "/404"))->getBody()));

// Bundle the npm files the pages use, and the fonts and images their CSS uses
$queue = array_unique($vendor);

while ($asset = array_shift($queue)) {
    $source = __DIR__ . "/node_modules/$asset";

    if (!is_file($source) || is_file("$out/vendor/$asset")) {
        continue;
    }

    $write("vendor/$asset", file_get_contents($source));

    if (str_ends_with($asset, ".css")) {
        preg_match_all('#url\(\s*[\'"]?(?!data:|https?:|/)([^\'")?\#]+)#', file_get_contents($source), $matches);

        foreach ($matches[1] as $relative) {
            $queue[] = preg_replace('#/(\./)+#', "/", dirname($asset) . "/$relative");
        }
    }
}

// Copy the public assets, such as the built CSS
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . "/public", FilesystemIterator::SKIP_DOTS));

foreach ($files as $file) {
    $write(substr($file->getPathname(), strlen(__DIR__ . "/public/")), file_get_contents($file->getPathname()));
}

echo "\nBuilt " . (count($paths) + 1) . " files and " . count(glob("$out/vendor/*/*", GLOB_ONLYDIR) ?: []) . " vendor packages to dist/\n";
