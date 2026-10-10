<?php
/**
 * ::: aside title="Psst"
 * Anything in Markdown
 * :::
 *
 * Its CSS is hoisted into the <head> once, however many asides a page has.
 */
?>
<aside class="aside">
    <?php if (!empty($attributes["title"])): ?><strong><?= e($attributes["title"]) ?></strong><?php endif ?>
    <?= $slot ?>
</aside>

<style hoist>
    .aside { margin: 2rem 0; padding: 1rem 1.25rem; border-radius: 0.75rem; background: #fff7e6; border: 2px solid #f0c36d; }
    .aside > strong { display: block; margin-bottom: 0.25rem; }
    .aside > p:last-child { margin-bottom: 0; }
</style>
