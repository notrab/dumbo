<?php

namespace Dumbo\Middleware;

use Dumbo\Context;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;

class Compress
{
    private const ENCODINGS = ["gzip", "deflate"];

    private const STR_REGEX = '/^\s*(?:text\/[^;\s]+|application\/(?:javascript|json|xml|xml-dtd|ecmascript|dart|postscript|rtf|tar|toml|vnd\.dart|vnd\.ms-fontobject|vnd\.ms-opentype|wasm|x-httpd-php|x-javascript|x-ns-proxy-autoconfig|x-sh|x-tar|x-virtualbox-hdd|x-virtualbox-ova|x-virtualbox-ovf|x-virtualbox-vbox|x-virtualbox-vdi|x-virtualbox-vhd|x-virtualbox-vmdk|x-www-form-urlencoded)|font\/(?:otf|ttf)|image\/(?:bmp|vnd\.adobe\.photoshop|vnd\.microsoft\.icon|vnd\.ms-dds|x-icon|x-ms-bmp)|message\/rfc822|model\/gltf-binary|x-shader\/x-fragment|x-shader\/x-vertex|[^;\s]+?\+(?:json|text|xml|yaml))(?:[;\s]|$)/i';

    /**
     * Create a middleware that compresses response bodies of at least
     * `threshold` bytes, using `encoding` or the first of gzip/deflate the
     * client accepts.
     *
     * @param array{threshold?: int, encoding?: string} $options
     */
    public static function compress(array $options = []): callable
    {
        $threshold = $options["threshold"] ?? 1024;
        $forcedEncoding = $options["encoding"] ?? null;

        return function (Context $context, callable $next) use (
            $threshold,
            $forcedEncoding
        ): ResponseInterface {
            $response = $next($context);
            $size = $response->getBody()->getSize();

            if (
                $context->req->method() === "HEAD" ||
                !$response->getBody()->isSeekable() ||
                $response->hasHeader("Content-Encoding") ||
                ($size !== null && $size < $threshold) ||
                !preg_match(self::STR_REGEX, $response->getHeaderLine("Content-Type")) ||
                preg_match('/(?:^|,)\s*no-transform\s*(?:,|$)/i', $response->getHeaderLine("Cache-Control"))
            ) {
                return $response;
            }

            $encoding =
                $forcedEncoding ??
                self::negotiate($context->req->header("Accept-Encoding") ?? "");

            if ($encoding === null) {
                return $response;
            }

            $contents = (string) $response->getBody();
            $compressed = $encoding === "deflate"
                ? gzdeflate($contents)
                : gzencode($contents);

            return $response
                ->withBody(Utils::streamFor($compressed))
                ->withoutHeader("Content-Length")
                ->withHeader("Content-Encoding", $encoding)
                ->withAddedHeader("Vary", "Accept-Encoding");
        };
    }

    /**
     * Pick the first supported encoding the client accepts, ignoring any it
     * has refused with q=0.
     */
    private static function negotiate(string $acceptEncoding): ?string
    {
        $accepted = [];

        foreach (explode(",", $acceptEncoding) as $part) {
            if (preg_match('/;\s*q=0(?:\.0*)?\s*$/i', $part)) {
                continue;
            }

            $accepted[] = strtolower(trim(explode(";", $part)[0]));
        }

        foreach (self::ENCODINGS as $encoding) {
            if (in_array($encoding, $accepted, true)) {
                return $encoding;
            }
        }

        return null;
    }
}
