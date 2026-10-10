<?php

namespace Dumbo\Jumbo;

use Dumbo\Context;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;

/**
 * Resized and converted copies of the images in public/, made with GD:
 *
 *   /_image/640/photos/beach.jpg.webp    beach.jpg, 640px wide, as WebP
 *   /_image/1280/photos/beach.jpg        beach.jpg, 1280px wide, as JPEG
 *
 * Only the configured widths and each image's own width are made, so a
 * client can't ask the server for an endless number of sizes. Copies are
 * cached on disk, and static builds write each one the pages use.
 */
final class Images
{
    private const MIME_TYPES = [
        "jpg" => "image/jpeg",
        "jpeg" => "image/jpeg",
        "png" => "image/png",
        "webp" => "image/webp",
        "avif" => "image/avif",
    ];

    /** @var array<string, array{int, int}|false> */
    private array $sizes = [];

    public function __construct(private Site $site)
    {
    }

    /**
     * The URL of an image in public/ at a width and format. The width is
     * rounded up to the next configured width, and images that can't be
     * resized, like SVGs, keep their own URL.
     */
    public function url(string $src, ?int $width = null, ?string $format = null): string
    {
        $file = $this->source($src);
        $size = $file ? $this->size($file) : null;

        if ($size === null) {
            return $src;
        }

        $extension = self::extension($file);
        $format = $this->format($format, $extension);
        $path = implode("/", array_map(rawurlencode(...), explode("/", ltrim(rawurldecode(self::path($src)), "/"))));

        return "/_image/" . $this->snap($width ?? $size[0], $size[0]) . "/$path" . ($format === $extension ? "" : ".$format")
            . "?v=" . substr(md5(filemtime($file) . ":" . filesize($file)), 0, 8);
    }

    /**
     * An <img> tag with a srcset of widths up to twice the given width, plus
     * its width and height so the page doesn't shift as it loads
     *
     * @param array<string, string|bool|null> $attributes Extra attributes, or overrides like "loading" => "eager"
     */
    public function tag(string $src, string $alt = "", ?int $width = null, ?string $sizes = null, ?string $format = null, array $attributes = []): string
    {
        $file = $this->source($src);
        $size = $file ? $this->size($file) : null;

        if ($size === null) {
            return self::element(["src" => $src, "alt" => $alt, ...$attributes]);
        }

        [$naturalWidth, $naturalHeight] = $size;
        $display = min($width ?? $naturalWidth, $naturalWidth);
        $widths = array_filter($this->widths($naturalWidth), fn(int $w) => $w <= $display * 2);
        $widths = array_unique([...$widths, $this->snap($display, $naturalWidth)]);
        sort($widths);

        return self::element([
            "src" => $this->url($src, $display, $format),
            "srcset" => implode(", ", array_map(fn(int $w) => $this->url($src, $w, $format) . " {$w}w", $widths)),
            "sizes" => $sizes ?? "(min-width: {$display}px) {$display}px, 100vw",
            "width" => (string) $display,
            "height" => (string) max(1, (int) round($naturalHeight * $display / $naturalWidth)),
            "alt" => $alt,
            "loading" => "lazy",
            "decoding" => "async",
            ...$attributes,
        ]);
    }

    /**
     * Whether an image in public/ can be resized
     */
    public function resizable(string $src): bool
    {
        $file = $this->source($src);

        return $file !== null && $this->size($file) !== null;
    }

    /**
     * The route handler for /_image/{width}/{file}
     */
    public function serve(Context $context): ResponseInterface
    {
        $width = (int) $context->req->param("width");
        $path = "/" . rawurldecode($context->req->param("file"));
        $file = $this->source($path);
        $format = null;

        // A second extension is the format to convert to
        if ($file === null || $this->size($file) === null) {
            $format = self::extension($path);
            $file = $this->source(substr($path, 0, -strlen($format) - 1));
        }

        $size = $file ? $this->size($file) : null;
        $format ??= $file ? self::extension($file) : null;

        if ($size === null || !in_array($width, $this->widths($size[0]), true) || !in_array($format, $this->formats(), true)) {
            return $context->notFound();
        }

        $cache = $this->cache() . "/" . sha1("$file:" . filemtime($file) . ":$width") . ".$format";

        if (!is_file($cache)) {
            $this->resize($file, $cache, $width, $format);
        }

        return $context->send(Utils::streamFor(Utils::tryFopen($cache, "r")), self::MIME_TYPES[$format], 200, [
            // Versioned URLs change when the image does
            "Cache-Control" => $context->req->query("v") !== null ? "public, max-age=31536000, immutable" : "public, max-age=3600",
        ]);
    }

    /**
     * The widths an image can be made at: the configured ones smaller than
     * it, and its own
     *
     * @return array<int>
     */
    private function widths(int $natural): array
    {
        $widths = array_filter(array_map(intval(...), $this->site->config["images"]["widths"]), fn(int $w) => $w > 0 && $w < $natural);
        $widths[] = $natural;
        sort($widths);

        return array_values(array_unique($widths));
    }

    private function snap(int $width, int $natural): int
    {
        foreach ($this->widths($natural) as $allowed) {
            if ($allowed >= $width) {
                return $allowed;
            }
        }

        return $natural;
    }

    private function format(?string $format, string $extension): string
    {
        $format = strtolower($format ?? $this->site->config["images"]["format"] ?? $extension);

        return in_array($format, $this->formats(), true) ? $format : $extension;
    }

    /**
     * @return array<string> The formats GD can write here
     */
    private function formats(): array
    {
        return array_keys(array_filter([
            "jpg" => true,
            "jpeg" => true,
            "png" => true,
            "webp" => function_exists("imagewebp"),
            "avif" => function_exists("imageavif") && !empty(gd_info()["AVIF Support"]),
        ]));
    }

    /**
     * The file in public/ for a URL path, or null if there's none
     */
    private function source(string $src): ?string
    {
        if (!str_starts_with($src, "/") || str_starts_with($src, "//")) {
            return null;
        }

        $root = realpath($this->site->path("public"));
        $file = $root ? realpath($root . "/" . ltrim(rawurldecode(self::path($src)), "/")) : false;

        return $file && is_file($file) && str_starts_with($file, $root . DIRECTORY_SEPARATOR) ? $file : null;
    }

    /**
     * The width and height of a JPEG, PNG or WebP as it's shown, or null for
     * anything GD can't resize here. GIFs are left alone so they keep moving.
     *
     * @return array{int, int}|null
     */
    private function size(string $file): ?array
    {
        if (!array_key_exists($file, $this->sizes)) {
            $readable = match (self::extension($file)) {
                "jpg", "jpeg", "png" => function_exists("imagecreatefromstring"),
                "webp" => function_exists("imagecreatefromwebp"),
                "avif" => function_exists("imagecreatefromavif"),
                default => false,
            };
            $size = $readable ? @getimagesize($file) : false;

            // Photos turned on their side are shown upright
            if ($size && in_array(self::orientation($file), [5, 6, 7, 8], true)) {
                [$size[0], $size[1]] = [$size[1], $size[0]];
            }

            $this->sizes[$file] = $size && $size[0] > 0 ? [$size[0], $size[1]] : false;
        }

        return $this->sizes[$file] ?: null;
    }

    private function resize(string $file, string $cache, int $width, string $format): void
    {
        $image = @imagecreatefromstring(file_get_contents($file)) ?: throw new \RuntimeException("GD couldn't read $file");
        $image = self::orient($image, self::orientation($file));

        $naturalWidth = imagesx($image);
        $naturalHeight = imagesy($image);
        $height = max(1, (int) round($naturalHeight * $width / $naturalWidth));
        $resized = imagecreatetruecolor($width, $height);

        if (in_array($format, ["jpg", "jpeg"], true)) {
            // JPEGs have no transparency, so it becomes white
            imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
        } else {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, $naturalWidth, $naturalHeight);

        if (!is_dir(dirname($cache))) {
            mkdir(dirname($cache), 0777, true);
        }

        // Written then moved, so a request never reads a half-written file
        $temporary = "$cache." . getmypid() . ".tmp";
        $quality = (int) $this->site->config["images"]["quality"];

        match ($format) {
            "jpg", "jpeg" => imagejpeg($resized, $temporary, $quality),
            "png" => imagepng($resized, $temporary),
            "webp" => imagewebp($resized, $temporary, $quality),
            "avif" => imageavif($resized, $temporary, $quality),
        };

        rename($temporary, $cache);
    }

    private function cache(): string
    {
        return $this->site->config["images"]["cache"] ?? sys_get_temp_dir() . "/jumbo-images-" . substr(md5($this->site->root), 0, 8);
    }

    private static function orientation(string $file): int
    {
        if (!in_array(self::extension($file), ["jpg", "jpeg"], true) || !function_exists("exif_read_data")) {
            return 1;
        }

        return (int) (@exif_read_data($file)["Orientation"] ?? 1);
    }

    private static function orient(\GdImage $image, int $orientation): \GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }

        // GD turns images anticlockwise
        $angle = match ($orientation) {
            3 => 180,
            5, 8 => 90,
            6, 7 => -90,
            default => 0,
        };

        return $angle === 0 ? $image : imagerotate($image, $angle, 0);
    }

    private static function element(array $attributes): string
    {
        $html = "<img";

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            $html .= $value === true ? " $name" : " $name=\"" . e((string) $value) . "\"";
        }

        return "$html>";
    }

    private static function path(string $src): string
    {
        return strtok($src, "?#") ?: "";
    }

    private static function extension(string $path): string
    {
        return strtolower(pathinfo(self::path($path), PATHINFO_EXTENSION));
    }
}
