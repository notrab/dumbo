<nav class="space-y-8 text-sm">
    <?php foreach ($nav as $group => $pages): ?>
        <div>
            <h3 class="mb-2 px-3 font-semibold text-zinc-900 dark:text-white"><?= e($group) ?></h3>
            <ul class="space-y-0.5">
                <?php foreach ($pages as $pageSlug => $pageTitle): ?>
                    <li>
                        <a href="/docs/<?= e($pageSlug) ?>"
                           class="block rounded-md px-3 py-1.5 transition <?= $pageSlug === ($slug ?? null)
                               ? "bg-brand-50 font-medium text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"
                               : "text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-900 dark:hover:text-white" ?>">
                            <?= e($pageTitle) ?>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
        </div>
    <?php endforeach ?>
</nav>
