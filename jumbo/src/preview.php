<?php

/**
 * The router script for `jumbo preview`, which serves a build the way most
 * static hosts do: files as they are, /about from about/index.html or
 * about.html, and 404.html for anything else
 */

$out = getenv("JUMBO_OUT");
$base = rtrim(getenv("JUMBO_BASE") ?: "", "/");
$path = rawurldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) ?: "/");

if ($base !== "") {
    if ($path !== $base && !str_starts_with($path, "$base/")) {
        header("Location: $base/", true, 302);

        return;
    }

    $path = substr($path, strlen($base)) ?: "/";
}

$types = [
    "html" => "text/html; charset=utf-8",
    "css" => "text/css",
    "js" => "text/javascript",
    "mjs" => "text/javascript",
    "json" => "application/json",
    "xml" => "application/xml",
    "txt" => "text/plain; charset=utf-8",
    "svg" => "image/svg+xml",
    "webp" => "image/webp",
    "avif" => "image/avif",
    "woff2" => "font/woff2",
];

$root = realpath($out);
$trimmed = rtrim($path, "/");

foreach (["$out$path", "$out$trimmed/index.html", "$out$trimmed.html"] as $candidate) {
    $file = realpath($candidate);

    if ($file !== false && is_file($file) && str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
        break;
    }

    $file = null;
}

if ($file === null) {
    http_response_code(404);
    $file = is_file("$out/404.html") ? "$out/404.html" : null;

    if ($file === null) {
        header("Content-Type: text/plain");
        echo "Not found";

        return;
    }
}

header("Content-Type: " . ($types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? (mime_content_type($file) ?: "application/octet-stream")));
readfile($file);
