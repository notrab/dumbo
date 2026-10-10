<?php
/**
 * One tab inside tabs. title: the tab's label
 */
$title = $attributes["title"] ?? "Tab";
?>
<div data-tab="<?= e($title) ?>" role="tabpanel">
    <p class="tab-title"><?= e($title) ?></p>
    <?= $slot ?>
</div>
