<?php

namespace Dumbo\Helpers;

use Dumbo\Context;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;

class StaticFiles
{
    /** Text formats that mime_content_type() reports as text/plain */
    private const MIME_TYPES = [
        "css" => "text/css",
        "htm" => "text/html",
        "html" => "text/html",
        "js" => "text/javascript",
        "mjs" => "text/javascript",
        "json" => "application/json",
        "svg" => "image/svg+xml",
    ];

    /**
     * Serve files from a directory, as a route handler (using the "path"
     * route param) or as middleware (using the request path, and passing
     * through to the next handler when no file matches).
     *
     * As middleware it only answers GET and HEAD requests, and ignores route
     * params, which belong to the route the request matched: a page at
     * /docs/{path} is never answered with a file named after its param.
     */
    public static function serve(string $directory): callable
    {
        return function (Context $context, ?callable $next = null) use ($directory): ResponseInterface {
            if ($next && !in_array($context->req->method(), ["GET", "HEAD"], true)) {
                return $next($context);
            }

            $path = $next ? $context->req->path() : $context->req->param("path") ?? $context->req->path();
            $file = self::resolve($directory, rawurldecode($path));

            if ($file === null) {
                return $next ? $next($context) : $context->text("File not found", 404);
            }

            $etag = sprintf('"%s"', md5_file($file));

            $response = $context
                ->getResponse()
                ->withHeader("Content-Type", self::mimeType($file))
                ->withHeader("Cache-Control", "public, max-age=3600")
                ->withHeader("ETag", $etag);

            if (trim($context->req->header("If-None-Match") ?? "") === $etag) {
                return $response->withStatus(304);
            }

            return $response->withBody(Utils::streamFor(Utils::tryFopen($file, "r")));
        };
    }

    private static function mimeType(string $file): string
    {
        return self::MIME_TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))]
            ?? (mime_content_type($file) ?: "application/octet-stream");
    }

    /**
     * Find the requested file inside the directory, falling back to
     * index.html for directories, and refusing anything outside it.
     */
    private static function resolve(string $directory, string $path): ?string
    {
        // realpath() throws on null bytes, and no file has one
        if (str_contains($path, "\0")) {
            return null;
        }

        $root = realpath($directory);
        $file = $directory . "/" . ltrim($path, "/");

        if (is_dir($file)) {
            $file = rtrim($file, "/") . "/index.html";
        }

        $file = realpath($file);

        if (
            $root === false ||
            $file === false ||
            !is_file($file) ||
            !str_starts_with($file, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        ) {
            return null;
        }

        return $file;
    }
}
