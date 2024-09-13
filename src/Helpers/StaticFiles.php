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
                strpos($realFilePath, $realDirectory) !== 0
            ) {
                return $context->text("File not found", 404);
            }

            if (file_exists($realFilePath) && is_file($realFilePath)) {
                $mimeType = mime_content_type($realFilePath);
                $fileContent = file_get_contents($realFilePath);

                $response = $context
                    ->getResponse()
                    ->withHeader("Content-Type", $mimeType)
                    ->withHeader("Cache-Control", "public, max-age=3600")
                    ->withBody(\GuzzleHttp\Psr7\Utils::streamFor($fileContent));

                $etag = md5($fileContent);
                $response = $response->withHeader("ETag", $etag);

                $ifNoneMatch = $context->req->header("If-None-Match");
                if ($ifNoneMatch === $etag) {
                    return $response->withStatus(304);
                }

                return $response;
            }

            return $context->text("File not found", 404);
        };
    }
}
