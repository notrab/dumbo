<?php
/**
 * One card inside cards. title, plus optional href and icon
 */
$tag = isset($attributes["href"]) ? "a" : "div";
?>
<<?= $tag ?> <?= $tag === "a" ? 'href="' . e($attributes["href"]) . '"' : "" ?> class="pop <?= $tag === "a" ? "pop-hover hover:bg-accent-100 dark:hover:bg-primary-950" : "" ?> block rounded-xl bg-paper p-5">
    <?php if (isset($attributes["icon"])): ?>
        <span class="flex h-10 w-10 items-center justify-center rounded-lg border-2 border-ink bg-white text-xl dark:bg-stone-800"><?= e($attributes["icon"]) ?></span>
    <?php endif ?>
    <span class="mt-3 block font-display text-lg font-bold text-ink"><?= e($attributes["title"] ?? "") ?></span>
    <span class="mt-1 block text-sm text-stone-600 dark:text-stone-400 [&_p]:m-0"><?= $slot ?></span>
</<?= $tag ?>>
