<?php

namespace Dumbo;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Builds the request from PHP's globals, the way Guzzle's
 * ServerRequest::fromGlobals() does: an invalid Host header is dropped and
 * the body can be read more than once. Requests without a body don't open
 * php://input at all.
 *
 * @internal
 */
final class Globals
{
    public static function request(): ServerRequestInterface
    {
        return self::create($_SERVER, self::headers($_SERVER), $_GET, $_POST, $_COOKIE, $_FILES);
    }

    /**
     * Build the request from snapshots of the globals and the headers from
     * headers()
     */
    public static function create(
        array $server,
        array $headers,
        array $query,
        array $parsedBody,
        array $cookies,
        array $files
    ): ServerRequestInterface {
        $hasBody = ($server["CONTENT_LENGTH"] ?? "") !== "" || isset($server["HTTP_TRANSFER_ENCODING"]);

        $request = new ServerRequest(
            self::method($server),
            self::uri($server),
            $headers,
            $hasBody ? Stream::create(fopen("php://input", "r")) : null,
            self::protocolVersion($server),
            $server
        );

        return $request
            ->withCookieParams($cookies)
            ->withQueryParams($query)
            ->withParsedBody($parsedBody)
            ->withUploadedFiles(self::normalizeFiles($files));
    }

    /**
     * The request headers, without a Host header that isn't a valid host
     *
     * @return array<string, string>
     */
    public static function headers(array $server): array
    {
        $headers = [];

        foreach (function_exists("getallheaders") ? getallheaders() : self::headersFromServer($server) as $name => $value) {
            if (is_scalar($value) && (strcasecmp($name, "Host") !== 0 || self::parseHost((string) $value) !== null)) {
                $headers[(string) $name] = (string) $value;
            }
        }

        return $headers;
    }

    public static function method(array $server): string
    {
        return strtoupper(self::server($server, "REQUEST_METHOD") ?? "GET");
    }

    public static function protocolVersion(array $server): string
    {
        return str_replace("HTTP/", "", self::server($server, "SERVER_PROTOCOL") ?? "1.1");
    }

    /**
     * The path of the request URI, as it arrived
     */
    public static function path(array $server): string
    {
        $requestUri = self::server($server, "REQUEST_URI");

        return $requestUri === null ? "" : explode("?", $requestUri, 2)[0];
    }

    private static function uri(array $server): UriInterface
    {
        $https = self::server($server, "HTTPS");
        $uri = (new Uri())->withScheme($https !== null && $https !== "" && $https !== "off" ? "https" : "http");
        $port = null;

        if (($authority = self::server($server, "HTTP_HOST")) !== null) {
            [$host, $port] = self::parseHost($authority) ?? [null, null];
        } else {
            $host = self::server($server, "SERVER_NAME") ?? self::server($server, "SERVER_ADDR");
        }

        // As Guzzle does, http and https URIs without a host get localhost
        $uri = $uri->withHost($host ?? "localhost");

        $serverPort = self::server($server, "SERVER_PORT");
        $port ??= $serverPort !== null && preg_match('/^[+-]?\d+$/D', $serverPort) ? (int) $serverPort : null;

        if ($port !== null) {
            $uri = $uri->withPort($port);
        }

        $requestUri = self::server($server, "REQUEST_URI");

        if ($requestUri === null) {
            return $uri->withQuery(self::server($server, "QUERY_STRING") ?? "");
        }

        [$path, $query] = explode("?", $requestUri, 2) + [1 => self::server($server, "QUERY_STRING") ?? ""];

        return $uri->withPath($path)->withQuery($query);
    }

    /**
     * Split a Host header into its host and port, or null when it isn't a
     * valid host, e.g. one with a path or a userinfo part
     *
     * @return array{string, int|null}|null
     */
    private static function parseHost(string $authority): ?array
    {
        if (!preg_match('/^(\[[^\]]*\]|[^:\[\]]*)(?::(\d+))?$/D', $authority, $m) || $m[1] === "") {
            return null;
        }

        [, $host] = $m;
        $port = isset($m[2]) ? (int) ltrim($m[2], "0") : null;

        if ($port > 0xFFFF || strlen(ltrim($m[2] ?? "", "0")) > 5 || preg_match('/[\x00-\x20\x7F\/?#@\\\\]/', $host)) {
            return null;
        }

        if ($host[0] === "[") {
            $address = substr($host, 1, -1);
            $valid = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
                || preg_match('/^v[0-9a-f]+\.[a-z0-9\-._~!$&\'()*+,;=:]+$/iD', $address) === 1;

            if (!$valid) {
                return null;
            }
        }

        return [$host, $port];
    }

    /**
     * Headers from $_SERVER, for SAPIs without getallheaders()
     */
    private static function headersFromServer(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, "HTTP_")) {
                $headers[str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($key, 5)))))] = $value;
            } elseif (in_array($key, ["CONTENT_TYPE", "CONTENT_LENGTH", "CONTENT_MD5"], true)) {
                $headers[str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", $key))))] = $value;
            }
        }

        if (!isset($headers["Authorization"])) {
            if (isset($server["REDIRECT_HTTP_AUTHORIZATION"])) {
                $headers["Authorization"] = $server["REDIRECT_HTTP_AUTHORIZATION"];
            } elseif (isset($server["PHP_AUTH_USER"])) {
                $headers["Authorization"] = "Basic " . base64_encode($server["PHP_AUTH_USER"] . ":" . ($server["PHP_AUTH_PW"] ?? ""));
            } elseif (isset($server["PHP_AUTH_DIGEST"])) {
                $headers["Authorization"] = $server["PHP_AUTH_DIGEST"];
            }
        }

        return $headers;
    }

    /**
     * Turn $_FILES, including nested and multiple uploads, into UploadedFiles
     */
    private static function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            $normalized[$key] = match (true) {
                !is_array($value) => throw new \InvalidArgumentException("Invalid value in files specification"),
                !isset($value["tmp_name"]) => self::normalizeFiles($value),
                is_array($value["tmp_name"]) => self::normalizeFiles(array_map(
                    fn($key) => [
                        "tmp_name" => $value["tmp_name"][$key],
                        "size" => $value["size"][$key] ?? null,
                        "error" => $value["error"][$key] ?? null,
                        "name" => $value["name"][$key] ?? null,
                        "type" => $value["type"][$key] ?? null,
                    ],
                    array_combine(array_keys($value["tmp_name"]), array_keys($value["tmp_name"]))
                )),
                default => new UploadedFile(
                    $value["tmp_name"],
                    (int) $value["size"],
                    (int) $value["error"],
                    $value["name"],
                    $value["type"]
                ),
            };
        }

        return $normalized;
    }

    private static function server(array $server, string $key): ?string
    {
        return isset($server[$key]) && is_string($server[$key]) ? $server[$key] : null;
    }
}
