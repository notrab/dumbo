<?php

namespace Dumbo;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * The request from PHP's globals, built as a full PSR-7 request only when
 * something needs one. The method, path, headers, query, cookies, form fields
 * and server params are read from the globals as they were when it was made.
 * Everything else, including every with*() method, builds the full request
 * with Globals::create() and uses that.
 *
 * @internal
 */
final class LazyRequest implements ServerRequestInterface
{
    /** Path characters a PSR-7 URI keeps as they are, see Nyholm\Psr7\Uri::filterPath() */
    private const PATH_CHARS = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-.~!$&'()*+,;=%:@/";

    private ?ServerRequestInterface $request = null;

    /** @var array<string, string>|null The headers as sent, from Globals::headers() */
    private ?array $headers = null;

    /** @var array<string, array<string>>|null Header values by lowercase name */
    private ?array $headerLines = null;

    private ?string $path = null;

    public function __construct(
        private array $server,
        private array $query,
        private array $parsedBody,
        private array $cookies,
        private array $files
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
    }

    /**
     * The request's path, without building its URI when it has nothing a
     * PSR-7 URI would encode
     */
    public static function pathOf(ServerRequestInterface $request): string
    {
        return $request instanceof self ? $request->path() : $request->getUri()->getPath();
    }

    /**
     * The full PSR-7 request, built the first time it's needed
     */
    public function toPsr7(): ServerRequestInterface
    {
        return $this->request ??= Globals::create(
            $this->server,
            $this->headers(),
            $this->query,
            $this->parsedBody,
            $this->cookies,
            $this->files
        );
    }

    private function path(): string
    {
        if ($this->path !== null) {
            return $this->path;
        }

        $path = Globals::path($this->server);

        // A % must start an escape like %20, or the URI encodes it
        $asIs = strspn($path, self::PATH_CHARS) === strlen($path)
            && (!str_contains($path, "%") || !preg_match('/%(?![A-Fa-f0-9]{2})/', $path));

        return $this->path = $asIs ? $path : $this->toPsr7()->getUri()->getPath();
    }

    private function headers(): array
    {
        return $this->headers ??= Globals::headers($this->server);
    }

    /**
     * @return array<string, array<string>>
     */
    private function headerLines(): array
    {
        if ($this->headerLines === null) {
            $this->headerLines = [];

            foreach ($this->headers() as $name => $value) {
                $this->headerLines[strtolower($name)][] = trim($value, " \t");
            }
        }

        return $this->headerLines;
    }

    public function getMethod(): string
    {
        return Globals::method($this->server);
    }

    public function getProtocolVersion(): string
    {
        return Globals::protocolVersion($this->server);
    }

    public function getHeader(string $name): array
    {
        $lines = $this->headerLines();
        $name = strtolower($name);

        // Without a Host header, the full request adds one from the URI
        if ($name === "host" && !isset($lines["host"])) {
            return $this->toPsr7()->getHeader($name);
        }

        return $lines[$name] ?? [];
    }

    public function hasHeader(string $name): bool
    {
        return $this->getHeader($name) !== [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(", ", $this->getHeader($name));
    }

    public function getServerParams(): array
    {
        return $this->server;
    }

    public function getCookieParams(): array
    {
        return $this->cookies;
    }

    public function getQueryParams(): array
    {
        return $this->query;
    }

    public function getParsedBody(): array
    {
        return $this->parsedBody;
    }

    public function getAttributes(): array
    {
        return [];
    }

    public function getAttribute(string $name, $default = null): mixed
    {
        return $default;
    }

    public function getHeaders(): array
    {
        return $this->toPsr7()->getHeaders();
    }

    public function getBody(): StreamInterface
    {
        return $this->toPsr7()->getBody();
    }

    public function getRequestTarget(): string
    {
        return $this->toPsr7()->getRequestTarget();
    }

    public function getUri(): UriInterface
    {
        return $this->toPsr7()->getUri();
    }

    public function getUploadedFiles(): array
    {
        return $this->toPsr7()->getUploadedFiles();
    }

    public function withProtocolVersion(string $version): ServerRequestInterface
    {
        return $this->toPsr7()->withProtocolVersion($version);
    }

    public function withHeader(string $name, $value): ServerRequestInterface
    {
        return $this->toPsr7()->withHeader($name, $value);
    }

    public function withAddedHeader(string $name, $value): ServerRequestInterface
    {
        return $this->toPsr7()->withAddedHeader($name, $value);
    }

    public function withoutHeader(string $name): ServerRequestInterface
    {
        return $this->toPsr7()->withoutHeader($name);
    }

    public function withBody(StreamInterface $body): ServerRequestInterface
    {
        return $this->toPsr7()->withBody($body);
    }

    public function withRequestTarget(string $requestTarget): ServerRequestInterface
    {
        return $this->toPsr7()->withRequestTarget($requestTarget);
    }

    public function withMethod(string $method): ServerRequestInterface
    {
        return $this->toPsr7()->withMethod($method);
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): ServerRequestInterface
    {
        return $this->toPsr7()->withUri($uri, $preserveHost);
    }

    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        return $this->toPsr7()->withCookieParams($cookies);
    }

    public function withQueryParams(array $query): ServerRequestInterface
    {
        return $this->toPsr7()->withQueryParams($query);
    }

    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        return $this->toPsr7()->withUploadedFiles($uploadedFiles);
    }

    public function withParsedBody($data): ServerRequestInterface
    {
        return $this->toPsr7()->withParsedBody($data);
    }

    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        return $this->toPsr7()->withAttribute($name, $value);
    }

    public function withoutAttribute(string $name): ServerRequestInterface
    {
        return $this->toPsr7()->withoutAttribute($name);
    }
}
