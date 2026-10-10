<?php

namespace Dumbo\Jumbo;

/**
 * One render of a PHP template. Inside it, $site is the site and $this is
 * the template, for picking a layout and filling its slots:
 *
 *   <?php $this->layout("base", ["title" => "About"]) ?>
 *
 *   <?php $this->start("head") ?>
 *       <link rel="stylesheet" href="/about.css">
 *   <?php $this->end() ?>
 *
 *   <h1>About</h1>
 *
 * The layout gets the template's output as $slot, named slots from
 * $this->slot("head"), and the template's variables. Layouts can have
 * layouts of their own.
 */
final class Template
{
    /** @var array{string, array}|null */
    private ?array $layout = null;

    /** @var array<string> */
    private array $open = [];

    /**
     * @param array<string, string> $slots Filled by the template this one wraps
     */
    public function __construct(private Site $site, private string $file, private array $slots = [])
    {
    }

    public function render(array $data = []): string
    {
        $html = $this->evaluate($data);

        if ($this->layout === null) {
            return $html;
        }

        [$name, $layoutData] = $this->layout;

        return (new self($this->site, $this->site->viewFile($name), $this->slots))
            ->render([...$data, ...$layoutData, "slot" => $html]);
    }

    /**
     * Render this template inside views/{name}.php
     */
    public function layout(string $name, array $data = []): void
    {
        $this->layout = [$name, $data];
    }

    /**
     * Capture output until end() as a named slot for the layout
     */
    public function start(string $name): void
    {
        $this->open[] = $name;
        ob_start();
    }

    public function end(): void
    {
        $name = array_pop($this->open) ?? throw new \LogicException("end() was called without start() in $this->file");
        $html = ob_get_clean();

        // A slot filled by an inner template wins over the layout's own
        $this->slots[$name] ??= $html;
    }

    /**
     * A named slot, or the default if no template filled it
     */
    public function slot(string $name, string $default = ""): string
    {
        return $this->slots[$name] ?? $default;
    }

    private function evaluate(array $__data): string
    {
        $level = ob_get_level();
        ob_start();

        try {
            (function () use ($__data) {
                extract($__data);
                $site = $this->site;

                require $this->file;
            })();
        } catch (\Throwable $error) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $error;
        }

        if ($this->open !== []) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw new \LogicException("start(\"" . end($this->open) . "\") has no end() in $this->file");
        }

        return ob_get_clean();
    }
}
