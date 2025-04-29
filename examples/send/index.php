<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$app->get("/", function ($c) {
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>File Type Examples</title>
</head>
<body>
    <h1>File Type Examples</h1>
    <ul>
        <li><a href="/logo">SVG</a> (image/svg+xml)</li>
        <li><a href="/image">Image</a> (image/jpeg)</li>
        <li><a href="/readme">README</a> (text/plain)</li>
        <li><a href="/export">CSV Export</a> (text/csv)</li>
        <li><a href="/feed">XML Feed</a> (application/xml)</li>
    </ul>
</body>
</html>
HTML;

    return $c->html($html);
});

$app->get("/logo", function ($c) {
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">
    <circle cx="50" cy="50" r="40" stroke="black" stroke-width="3" fill="purple" />
</svg>
SVG;

    return $c->send($svg, "image/svg+xml");
});

$app->get("/image", function ($c) {
    $png = file_get_contents(__DIR__ . "/dumbo.jpeg");
    return $c->send($png, "image/jpeg");
});

$app->get("/readme", function ($c) {
    $text = file_get_contents(__DIR__ . "/README.md");
    return $c->send($text, "text/plain");
});

$app->get("/export", function ($c) {
    $csv = "Name,Email,Role\n";
    $csv .= "John Doe,john@example.com,Admin\n";
    $csv .= "Jane Smith,jane@example.com,User\n";

    return $c->send($csv, "text/csv", 200, [
        "Content-Disposition" => 'attachment; filename="users.csv"',
    ]);
});

$app->get("/feed", function ($c) {
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>My Feed</title>
        <description>Latest updates</description>
        <item>
            <title>New Feature</title>
            <description>Check out our latest feature!</description>
        </item>
    </channel>
</rss>
XML;

    return $c->send($xml, "application/xml");
});

$app->run();
