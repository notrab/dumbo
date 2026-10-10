<?php

require __DIR__ . "/vendor/autoload.php";

$site = new Dumbo\Jumbo\Site(__DIR__, [
    "name" => "Herd Handbook",
    "url" => getenv("SITE_URL") ?: "https://handbook.example",
    // Old URLs that moved. Served as real redirects by the PHP server, and
    // as meta refreshes in builds, unless the host reads _redirects below.
    "redirects" => [
        "/getting-started" => "/guides/first-steps",
        "/guides/feeding" => ["to" => "/guides/peanuts", "status" => 302],
    ],
    // Builds /guides/peanuts as guides/peanuts.html, for hosts that like files
    "build" => ["format" => "file"],
]);

// Run the CSS build first, if there was one
$site->on("build:start", function (string $out) {
    echo "Building into " . basename($out) . "/\n";
});

// Once it's built, write the files Netlify and Cloudflare Pages read for
// redirects and headers, so they send real redirects and a few safe headers
$site->on("build:done", function (string $out, array $files) use ($site) {
    $redirects = "";

    foreach ($site->redirects() as $from => $redirect) {
        $redirects .= "$from {$redirect["to"]} {$redirect["status"]}\n";
    }

    file_put_contents("$out/_redirects", $redirects);
    file_put_contents("$out/_headers", "/*\n  X-Content-Type-Options: nosniff\n  Referrer-Policy: strict-origin-when-cross-origin\n");

    echo "Wrote _redirects and _headers for " . count($files) . " files\n";
});

return $site;
