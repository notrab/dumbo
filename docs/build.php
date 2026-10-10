<?php

/**
 * Render every page of the docs to static files in dist/, ready to upload to
 * any static host or CDN.
 *
 *   php build.php                Build for the root of a domain
 *   php build.php --base=/dumbo  Build for a sub-path, e.g. GitHub Pages
 */

use GuzzleHttp\Psr7\ServerRequest;

$app = require __DIR__ . "/app.php";

$out = __DIR__ . "/dist";
$base = rtrim(getopt("", ["base:"])["base"] ?? "", "/");

if (!is_file(__DIR__ . "/public/app.css")) {
    fwrite(STDERR, "public/app.css is missing. Run `npm run build` first.\n");
    exit(1);
}

$paths = [
    "/",
    "/docs",
    ...array_map(fn($slug) => "/docs/$slug", array_keys($pages)),
    ...array_map(fn($name) => "/docs/examples/$name", array_keys(examples())),
];

// Prefix root-relative links and assets with the base path
$rebase = fn(string $html) => $base === ""
    ? $html
    : preg_replace('#((?:href|src|action|url)=["\']?)/(?!/)#', "$1$base/", $html);

$write = function (string $file, string $contents) use ($out) {
    @mkdir(dirname("$out/$file"), 0777, true);
    file_put_contents("$out/$file", $contents);
};

exec("rm -rf " . escapeshellarg($out));

foreach ($paths as $path) {
    $response = $app->handle(new ServerRequest("GET", $path));
    $status = $response->getStatusCode();
    $file = ltrim("$path/index.html", "/");

    if ($status >= 300 && $status < 400) {
        // Static hosts can't redirect, so send the browser on with a meta refresh
        $location = e($base . $response->getHeaderLine("Location"));
        $write($file, "<!DOCTYPE html><meta charset=\"utf-8\"><meta http-equiv=\"refresh\" content=\"0; url=$location\"><link rel=\"canonical\" href=\"$location\"><a href=\"$location\">Redirecting...</a>\n");
    } elseif ($status === 200) {
        $write($file, $rebase((string) $response->getBody()));
    } else {
        fwrite(STDERR, "GET $path responded with $status\n");
        exit(1);
    }

    echo "  $path\n";
}

// Most static hosts serve 404.html for missing pages
$write("404.html", $rebase((string) $app->handle(new ServerRequest("GET", "/404"))->getBody()));

// Copy the public assets, such as the built CSS
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . "/public", FilesystemIterator::SKIP_DOTS));

foreach ($files as $file) {
    $write(substr($file->getPathname(), strlen(__DIR__ . "/public/")), file_get_contents($file->getPathname()));
}

echo "\nBuilt " . (count($paths) + 1) . " pages to dist/\n";
