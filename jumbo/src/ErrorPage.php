<?php

namespace Dumbo\Jumbo;

/**
 * The page shown for an uncaught error while developing: the message, the
 * lines around where it was thrown, and the trace
 */
final class ErrorPage
{
    public static function render(\Throwable $error, string $root = ""): string
    {
        $relative = fn(string $file) => $root !== "" && str_starts_with($file, "$root/") ? substr($file, strlen($root) + 1) : $file;
        $frames = "";

        foreach ($error->getTrace() as $i => $frame) {
            $call = ($frame["class"] ?? "") . ($frame["type"] ?? "") . ($frame["function"] ?? "");
            $where = isset($frame["file"]) ? $relative($frame["file"]) . ":" . ($frame["line"] ?? "?") : "internal";
            $frames .= "<li><code>" . e($call) . "()</code> <span>" . e($where) . "</span></li>";

            if ($i >= 30) {
                break;
            }
        }

        $previous = $error->getPrevious()
            ? "<p class=\"previous\">Caused by " . e(get_class($error->getPrevious())) . ": " . e($error->getPrevious()->getMessage()) . "</p>"
            : "";

        $class = e(get_class($error));
        $message = nl2br(e($error->getMessage()));
        $location = e($relative($error->getFile()) . ":" . $error->getLine());
        $excerpt = self::excerpt($error->getFile(), $error->getLine());

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$class}</title>
<style>
body { margin: 0; padding: 2rem; font: 15px/1.5 system-ui, sans-serif; background: #1c1917; color: #e7e5e4; }
main { max-width: 64rem; margin: 0 auto; }
.class { color: #c4b5fd; font: 600 13px ui-monospace, monospace; letter-spacing: .04em; }
h1 { margin: .25rem 0 1rem; font-size: 1.5rem; line-height: 1.3; color: #fafaf9; }
.location, .previous { color: #a8a29e; font-family: ui-monospace, monospace; font-size: 13px; }
pre { overflow-x: auto; margin: 1rem 0 2rem; padding: 1rem 0; border-radius: .75rem; background: #0c0a09; font: 13px/1.6 ui-monospace, monospace; }
pre span { display: block; padding: 0 1rem; white-space: pre; }
pre span b { display: inline-block; width: 3rem; color: #57534e; font-weight: normal; user-select: none; }
pre span.line { background: #7f1d1d66; }
h2 { font-size: .8rem; text-transform: uppercase; letter-spacing: .08em; color: #a8a29e; }
ol { padding: 0; list-style: none; font-size: 13px; }
li { padding: .4rem 0; border-top: 1px solid #292524; }
li span { color: #78716c; font-family: ui-monospace, monospace; }
code { color: #e7e5e4; }
</style>
</head>
<body>
<main>
<div class="class">{$class}</div>
<h1>{$message}</h1>
<div class="location">{$location}</div>
{$previous}
{$excerpt}
<h2>Trace</h2>
<ol>{$frames}</ol>
</main>
</body>
</html>
HTML;
    }

    private static function excerpt(string $file, int $line): string
    {
        if (!is_file($file) || !is_readable($file)) {
            return "";
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $start = max(1, $line - 5);
        $end = min(count($lines), $line + 5);
        $html = "";

        for ($n = $start; $n <= $end; $n++) {
            $html .= "<span" . ($n === $line ? " class=\"line\"" : "") . "><b>$n</b>" . e($lines[$n - 1]) . "</span>";
        }

        return "<pre>$html</pre>";
    }
}
