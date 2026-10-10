<?php
/**
 * ::: callout type="tip" title="Heads up"
 * Something worth knowing.
 * :::
 *
 * type: note (default), tip, warning or danger
 */
$type = in_array($attributes["type"] ?? null, ["note", "tip", "warning", "danger"], true) ? $attributes["type"] : "note";
$icon = ["note" => "💡", "tip" => "✅", "warning" => "⚠️", "danger" => "🚨"][$type];
$title = $attributes["title"] ?? ucfirst($type);
?>
<aside class="callout callout-<?= $type ?>">
    <p class="callout-title"><span aria-hidden="true"><?= $icon ?></span> <?= e($title) ?></p>
    <?= $slot ?>
</aside>
