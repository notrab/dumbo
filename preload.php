<?php

/**
 * Preload Dumbo and the PSR-7 classes every request uses into OPcache, so
 * requests skip loading them. Point opcache.preload at this file:
 *
 *     opcache.preload=/var/www/vendor/notrab/dumbo/preload.php
 *     opcache.preload_user=www-data
 *
 * Preloaded code only changes when PHP restarts, so restart PHP-FPM after
 * each deploy.
 */

require file_exists(__DIR__ . "/vendor/autoload.php")
    ? __DIR__ . "/vendor/autoload.php"
    : dirname(__DIR__, 2) . "/autoload.php";

$classes = [
    \Nyholm\Psr7\Response::class,
    \Nyholm\Psr7\ServerRequest::class,
    \Nyholm\Psr7\Stream::class,
    \Nyholm\Psr7\UploadedFile::class,
    \Nyholm\Psr7\Uri::class,
];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . "/src", FilesystemIterator::SKIP_DOTS));

foreach ($files as $file) {
    $classes[] = "Dumbo\\" . str_replace("/", "\\", substr($file->getPathname(), strlen(__DIR__ . "/src/"), -4));
}

foreach ($classes as $class) {
    try {
        class_exists($class);
    } catch (\Throwable) {
        // Skip classes whose optional dependencies aren't installed
    }
}
