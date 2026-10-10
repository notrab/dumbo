<nav class="space-y-7 text-sm">
    <?php foreach ($site->sidebar() as $group => $pages): ?>
        <div>
            <h3 class="mb-2 px-3 font-display text-base font-bold text-ink"><?= e($group) ?></h3>
            <ul class="space-y-1">
                <?php foreach ($pages as $pageSlug => $pageTitle): ?>
                    <li>
                        <a href="/docs/<?= e($pageSlug) ?>"
                           class="block rounded-lg border-2 px-3 py-1.5 transition <?= $pageSlug === ($slug ?? null)
                               ? "-rotate-1 border-ink bg-accent-300 font-bold text-[#1c1917] shadow-[2px_2px_0_0_var(--color-ink)] dark:bg-primary-500 dark:text-white"
                               : "border-transparent text-stone-600 hover:translate-x-1 hover:text-ink dark:text-stone-400" ?>">
                            <?= e($pageTitle) ?>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
        </div>
    <?php endforeach ?>
</nav>
