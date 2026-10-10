<!-- Mobile: menu bar under the header -->
<div class="sticky top-16 z-30 flex items-center border-b border-zinc-200 bg-white/80 px-4 py-2 backdrop-blur sm:px-6 lg:hidden dark:border-zinc-800 dark:bg-zinc-950/80">
    <button type="button" data-menu-open class="flex items-center gap-2 text-sm font-medium">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        Menu
    </button>
</div>

<!-- Mobile: slide-out sidebar -->
<div data-menu class="fixed inset-0 z-50 hidden lg:hidden">
    <div data-menu-close class="absolute inset-0 bg-zinc-950/50 backdrop-blur-sm"></div>
    <div class="absolute inset-y-0 left-0 w-72 max-w-[85vw] overflow-y-auto bg-white p-4 shadow-xl dark:bg-zinc-950">
        <div class="mb-6 flex items-center justify-between px-3">
            <a href="/" class="flex items-center gap-2 font-bold text-zinc-900 dark:text-white"><span class="text-xl">🐘</span> Dumbo</a>
            <button type="button" data-menu-close class="rounded-md p-1" aria-label="Close menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <?= view("sidebar", ["nav" => $nav, "slug" => $slug]) ?>
    </div>
</div>

<div class="mx-auto flex max-w-[90rem]">
    <!-- Desktop sidebar -->
    <aside class="sticky top-16 hidden h-[calc(100vh-4rem)] w-64 shrink-0 overflow-y-auto border-r border-zinc-200 px-4 py-8 lg:block dark:border-zinc-800">
        <?= view("sidebar", ["nav" => $nav, "slug" => $slug]) ?>
    </aside>

    <main class="min-w-0 flex-1 px-4 py-10 sm:px-8 lg:px-12">
        <div class="mx-auto max-w-3xl">
            <?php if ($toc): ?>
                <!-- Mobile and tablet: collapsible table of contents -->
                <details class="mb-8 rounded-lg border border-zinc-200 text-sm xl:hidden dark:border-zinc-800">
                    <summary class="cursor-pointer px-4 py-3 font-medium text-zinc-900 dark:text-white">On this page</summary>
                    <ul class="space-y-1 border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                        <?php foreach ($toc as $item): ?>
                            <li class="<?= $item["level"] === 3 ? "pl-4" : "" ?>">
                                <a href="#<?= e($item["id"]) ?>" class="block py-1 hover:text-brand-500"><?= e($item["text"]) ?></a>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </details>
            <?php endif ?>

            <article class="prose prose-zinc max-w-none dark:prose-invert prose-headings:scroll-mt-24 prose-a:text-brand-600 prose-a:no-underline hover:prose-a:underline dark:prose-a:text-brand-300 prose-pre:p-4 prose-h1:text-3xl sm:prose-h1:text-4xl">
                <?= $content ?>
            </article>

            <div class="mt-12 border-t border-zinc-200 pt-6 text-sm dark:border-zinc-800">
                <a href="https://github.com/notrab/dumbo/edit/main/docs/content/<?= e($slug) ?>.md" class="inline-flex items-center gap-1.5 font-medium text-brand-600 hover:underline dark:text-brand-300">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16.9 3.5 3.6 3.6L8 19.6l-4.5 1 1-4.5L16.9 3.5Z"/></svg>
                    Edit this page on GitHub
                </a>
            </div>

            <nav class="mt-8 grid gap-4 sm:grid-cols-2">
                <?php if ($prev): ?>
                    <a href="/docs/<?= e($prev["slug"]) ?>" class="group rounded-lg border border-zinc-200 p-4 transition hover:border-brand-500 dark:border-zinc-800">
                        <span class="text-xs text-zinc-500">Previous page</span>
                        <span class="block font-medium text-zinc-900 group-hover:text-brand-600 dark:text-white dark:group-hover:text-brand-300">&larr; <?= e($prev["title"]) ?></span>
                    </a>
                <?php else: ?>
                    <span class="hidden sm:block"></span>
                <?php endif ?>
                <?php if ($next): ?>
                    <a href="/docs/<?= e($next["slug"]) ?>" class="group rounded-lg border border-zinc-200 p-4 text-right transition hover:border-brand-500 dark:border-zinc-800">
                        <span class="text-xs text-zinc-500">Next page</span>
                        <span class="block font-medium text-zinc-900 group-hover:text-brand-600 dark:text-white dark:group-hover:text-brand-300"><?= e($next["title"]) ?> &rarr;</span>
                    </a>
                <?php endif ?>
            </nav>
        </div>
    </main>

    <!-- Desktop: table of contents -->
    <aside class="sticky top-16 hidden h-[calc(100vh-4rem)] w-56 shrink-0 overflow-y-auto py-10 pr-4 xl:block">
        <?php if ($toc): ?>
            <h3 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-white">On this page</h3>
            <ul class="space-y-1 border-l border-zinc-200 text-sm dark:border-zinc-800">
                <?php foreach ($toc as $item): ?>
                    <li>
                        <a href="#<?= e($item["id"]) ?>" data-toc-link="<?= e($item["id"]) ?>"
                           class="-ml-px block border-l border-transparent py-1 text-zinc-500 transition hover:text-zinc-900 dark:hover:text-white <?= $item["level"] === 3 ? "pl-6" : "pl-3" ?>">
                            <?= e($item["text"]) ?>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </aside>
</div>

<script>
    const menu = document.querySelector("[data-menu]");
    const toggleMenu = (open) => {
        menu.classList.toggle("hidden", !open);
        document.body.classList.toggle("overflow-hidden", open);
    };
    document.querySelector("[data-menu-open]").addEventListener("click", () => toggleMenu(true));
    document.querySelectorAll("[data-menu-close]").forEach((el) => el.addEventListener("click", () => toggleMenu(false)));
    document.addEventListener("keydown", (event) => event.key === "Escape" && toggleMenu(false));

    // Highlight the heading currently in view
    const links = document.querySelectorAll("[data-toc-link]");
    const active = ["text-brand-600", "border-brand-500", "dark:text-brand-300"];
    const observer = new IntersectionObserver((entries) => {
        entries.filter((entry) => entry.isIntersecting).slice(0, 1).forEach((entry) => {
            links.forEach((link) => active.forEach((c) => link.classList.toggle(c, link.dataset.tocLink === entry.target.id)));
        });
    }, { rootMargin: "-80px 0px -70% 0px" });
    document.querySelectorAll(".prose h2[id], .prose h3[id]").forEach((heading) => observer.observe(heading));
</script>
