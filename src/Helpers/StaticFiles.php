<?php

namespace Dumbo\Helpers;

use Dumbo\Context;
use Psr\Http\Message\ResponseInterface;

class StaticFiles
{
    /**
     * Create a handler for serving static files
     *
     * @param string $directory The directory containing the static files
     * @param array $options Additional options for serving static files
     * @return callable The handler function
     */
    public static function serve(
        string $directory,
        array $options = []
    ): callable {
        return function (Context $context) use ($directory, $options) {
            $requestedPath = $context->req->param("path") ?? "";
            $filePath = $directory . "/" . $requestedPath;

            if (empty($requestedPath) || is_dir($filePath)) {
                $filePath = rtrim($filePath, "/") . "/index.html";
            }

            $realFilePath = realpath($filePath);
            $realDirectory = realpath($directory);

            if (
                $realFilePath === false ||
                $realDirectory === false ||
                !str_starts_with(
                    $realFilePath,
                    rtrim($realDirectory, DIRECTORY_SEPARATOR) .
                        DIRECTORY_SEPARATOR
                )
            ) {
                return $context->text("File not found", 404);
            }

            if (is_file($realFilePath)) {
                $fileContent = file_get_contents($realFilePath);

                if ($fileContent === false) {
                    return $context->text("File not found", 404);
                }

                $mimeType =
                    mime_content_type($realFilePath) ?:
                    "application/octet-stream";

                $etag = sprintf('"%s"', md5($fileContent));

                $response = $context
                    ->getResponse()
                    ->withHeader("Content-Type", $mimeType)
                    ->withHeader("Cache-Control", "public, max-age=3600")
                    ->withHeader("ETag", $etag);

                $ifNoneMatch = $context->req->header("If-None-Match");
                if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
                    return $response->withStatus(304);
                }

                return $response->withBody(
                    \GuzzleHttp\Psr7\Utils::streamFor($fileContent)
                );
            }

            return $context->text("File not found", 404);
        };
    }
}
