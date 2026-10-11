<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\StaticFiles;

/**
 * The URL of an image in public/, with Bunny Optimizer's query parameters,
 * like ["width" => 640]. A version from the file's contents changes the URL
 * when the image changes, so the CDN and browsers can keep each one forever.
 *
 * Set CDN_URL to a pull zone's address, like https://acme.b-cdn.net, when
 * the pages are served from somewhere else.
 */
function image(string $path, array $options = []): string
{
    $file = __DIR__ . "/public" . $path;
    $options["v"] = is_file($file) ? substr(md5_file($file), 0, 8) : null;

    // Keep values like aspect_ratio=16:9 readable
    $query = str_replace("%3A", ":", http_build_query($options));

    return rtrim(getenv("CDN_URL") ?: "", "/") . $path . "?" . $query;
}

/**
 * A srcset of an image at each width, so browsers download the smallest
 * one that fills the space
 */
function srcset(string $path, array $widths, array $options = []): string
{
    return implode(", ", array_map(fn(int $width) => image($path, ["width" => $width] + $options) . " {$width}w", $widths));
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES);
}

$app = new Dumbo();

// Images get versioned URLs, so the CDN and browsers can cache them for a year
$app->use(function ($c, $next) {
    $response = $next($c);

    return str_starts_with($c->req->path(), "/images/") && $response->getStatusCode() === 200
        ? $response->withHeader("Cache-Control", "public, max-age=31536000, immutable")
        : $response;
});

$app->use(StaticFiles::serve(__DIR__ . "/public"));

$app->get("/", function ($c) {
    $photos = ["sunrise" => "Sunrise", "meadow" => "Meadow", "dusk" => "Dusk"];
    $hero = "/images/sunrise.jpg";
    $heroSrc = e(image($hero, ["width" => 960, "quality" => 80]));
    $heroSrcset = e(srcset($hero, [480, 960, 1440, 1920], ["quality" => 80]));

    $thumbnails = "";

    foreach ($photos as $name => $title) {
        $path = "/images/$name.jpg";
        // Square crops at 1x and 2x, made by the Optimizer from the original
        $thumbnails .= sprintf(
            '<figure><img src="%s" srcset="%s" width="300" height="300" alt="%s" loading="lazy"><figcaption>%s</figcaption></figure>',
            e(image($path, ["width" => 300, "aspect_ratio" => "1:1"])),
            e(srcset($path, [300, 600], ["aspect_ratio" => "1:1"])),
            e($title),
            e($title)
        );
    }

    return $c->html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dumbo + Bunny Optimizer</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0 auto; max-width: 960px; padding: 2rem 1rem; color: #222; }
        img { display: block; max-width: 100%; height: auto; border-radius: 8px; }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        figure { margin: 0; }
        code { background: #f3f3f3; padding: 0.1em 0.3em; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Dumbo + Bunny Optimizer</h1>
    <p>Every image here is one original in <code>public/images</code>. Bunny Optimizer resizes, crops and compresses it at the edge from the query string.</p>

    <img src="$heroSrc"
         srcset="$heroSrcset"
         sizes="(min-width: 960px) 960px, 100vw"
         width="960" height="640" alt="Sunrise over the hills" fetchpriority="high">

    <h2>Square thumbnails</h2>
    <div class="grid">$thumbnails</div>
</body>
</html>
HTML);
});

$app->run();
