<?php

namespace Dumbo\Helpers;

use Dumbo\Context;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Compress
{
    private const STR_REGEX = '/^\s*(?:text\/[^;\s]+|application\/(?:javascript|json|xml|xml-dtd|ecmascript|dart|postscript|rtf|tar|toml|vnd\.dart|vnd\.ms-fontobject|vnd\.ms-opentype|wasm|x-httpd-php|x-javascript|x-ns-proxy-autoconfig|x-sh|x-tar|x-virtualbox-hdd|x-virtualbox-ova|x-virtualbox-ovf|x-virtualbox-vbox|x-virtualbox-vdi|x-virtualbox-vhd|x-virtualbox-vmdk|x-www-form-urlencoded)|font\/(?:otf|ttf)|image\/(?:bmp|vnd\.adobe\.photoshop|vnd\.microsoft\.icon|vnd\.ms-dds|x-icon|x-ms-bmp)|message\/rfc822|model\/gltf-binary|x-shader\/x-fragment|x-shader\/x-vertex|[^;\s]+?\+(?:json|text|xml|yaml))(?:[;\s]|$)/i';

    /**
     * Create a middleware that compresses the response body using the specified encoding
     * if the response size is greater than the given threshold.
     *
     * @param array{
     *     threshold?: int,
     *     allowedEncodings?: string[],
     *     encoding?: string
     * } $options Configuration options
     * @return callable The middleware
     */
    public static function compress(array $options = []): callable
    {
        $threshold = $options["threshold"] ?? 1024;
        $allowedEncodings = ["gzip", "deflate"];
        $encoding = $options["encoding"] ?? null;

        return self::compressor($threshold, $allowedEncodings, $encoding);
    }

    /**
     * Create a middleware that compresses the response body using the specified encoding
     * if the response size is greater than the given threshold.
     *
     * @param int $threshold The minimum response size in bytes to compress
     * @param array $allowedEncodings The list of allowed encodings
     * @param ?string $encoding The selected encoding, or null to auto-detect
     * @return callable The middleware
     */
    private static function compressor(
        int $threshold,
        array $allowedEncodings,
        ?string $encoding
    ): callable {
        return function (Context $ctx, callable $next) use (
            $threshold,
            $allowedEncodings,
            $encoding
        ) {
            $next($ctx);

            $response = $ctx->getResponse();
            $contentLength = $response->getHeaderLine("Content-Length");

            if (
                $response->hasHeader("Content-Encoding") ||
                $ctx->req->method() === "HEAD" ||
                ($contentLength && (int) $contentLength < $threshold) ||
                !self::shouldCompress($response) ||
                !self::shouldTransform($response)
            ) {
                return $response;
            }

            $acceptedEncodings = array_map(
                "trim",
                explode(",", $ctx->req->header("Accept-Encoding"))
            );
            if (!$encoding) {
                foreach ($allowedEncodings as $enc) {
                    if (in_array($enc, $acceptedEncodings)) {
                        $encoding = $enc;
                        break;
                    }
                }
            }

            if (!$encoding || !$response->getBody()) {
                return $response;
            }

            $compressedBody = self::performCompression(
                $response->getBody(),
                $encoding
            );
            $response = $response
                ->withBody($compressedBody)
                ->withoutHeader("Content-Length")
                ->withHeader("Content-Encoding", $encoding);

            return $response;
        };
    }

    /**
     * Check if the response should be compressed based on the Content-Type header
     *
     * @param ResponseInterface $response The response to check
     * @return bool Whether the response should be compressed
     */
    private static function shouldCompress(ResponseInterface $response): bool
    {
        $type = $response->getHeaderLine("Content-Type");
        return preg_match(self::STR_REGEX, $type);
    }

    /**
     * Check if the response should be transformed (e.g. compressed) based on the
     * Cache-Control header
     *
     * @param ResponseInterface $response The response to check
     * @return bool Whether the response should be transformed
     */
    private static function shouldTransform(ResponseInterface $response): bool
    {
        $cacheControl = $response->getHeaderLine("Cache-Control");
        return !preg_match(
            '/(?:^|,)\s*?no-transform\s*?(?:,|$)/i',
            $cacheControl
        );
    }

    /**
     * Perform the actual compression of the response body using the specified encoding
     *
     * @param string $body The response body to compress
     * @param string $encoding The encoding to use for compression
     *
     * @return string|StreamInterface The compressed response body
     */
    private static function performCompression(
        string $body,
        string $encoding
    ): StreamInterface {
        return match ($encoding) {
            "deflate" => Utils::streamFor(gzdeflate($body)),
            "gzip" => Utils::streamFor(gzencode($body)),
            default => $body,
        };
    }
}
