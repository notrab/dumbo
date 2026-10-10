<?php $this->layout("layout") ?>
<!-- Mobile: menu bar under the header -->
<div class="sticky top-16 z-30 flex items-center border-b-2 border-ink bg-paper/90 px-4 py-2 backdrop-blur sm:px-6 lg:hidden">
    <button type="button" data-menu-open class="flex items-center gap-2 text-sm font-bold text-ink">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        Menu
    </button>
</div>

<!-- Mobile: slide-out sidebar -->
<div data-menu class="fixed inset-0 z-50 hidden lg:hidden">
    <div data-menu-close class="absolute inset-0 bg-stone-950/50 backdrop-blur-sm"></div>
    <div class="absolute inset-y-0 left-0 w-72 max-w-[85vw] overflow-y-auto border-r-2 border-ink bg-paper p-4">
        <div class="mb-6 flex items-center justify-between px-3">
            <a href="/" class="flex items-center gap-2 font-display text-xl font-extrabold text-ink"><?= $site->view("logo", ["class" => "text-2xl"]) ?> <?= e($site->config["name"]) ?></a>
            <button type="button" data-menu-close class="rounded-md p-1" aria-label="Close menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <?= $site->view("sidebar", ["slug" => $page["slug"]]) ?>
    </div>
</div>

<div class="mx-auto flex max-w-[90rem]">
    <!-- Desktop sidebar -->
    <aside class="sticky top-16 hidden h-[calc(100vh-4rem)] w-64 shrink-0 overflow-y-auto border-r-2 border-ink px-4 py-8 lg:block">
        <?= $site->view("sidebar", ["slug" => $page["slug"]]) ?>
    </aside>

    <main class="min-w-0 flex-1 px-4 py-10 sm:px-8 lg:px-12">
        <div class="mx-auto max-w-3xl">
            <?php if ($page["toc"]): ?>
                <!-- Mobile and tablet: collapsible table of contents -->
                <details class="pop mb-8 rounded-xl bg-paper text-sm xl:hidden">
                    <summary class="cursor-pointer px-4 py-3 font-display font-bold text-ink">📍 On this page</summary>
                    <ul class="space-y-1 border-t-2 border-ink px-4 py-3">
                        <?php foreach ($page["toc"] as $item): ?>
                            <li class="<?= $item["level"] === 3 ? "pl-4" : "" ?>">
                                <a href="#<?= e($item["id"]) ?>" class="block py-1 hover:text-primary-500"><?= e($item["text"]) ?></a>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </details>
            <?php endif ?>

            <article class="prose prose-stone max-w-none dark:prose-invert prose-headings:scroll-mt-24 prose-headings:text-ink prose-a:font-semibold prose-a:text-primary-600 prose-a:decoration-accent-400 prose-a:decoration-2 prose-a:underline-offset-4 hover:prose-a:decoration-primary-500 dark:prose-a:text-primary-300 prose-pre:p-4 prose-pre:pt-5 prose-h1:text-4xl prose-h1:font-extrabold sm:prose-h1:text-5xl prose-th:text-ink">
                <?= $page["html"] ?>
            </article>

            <?php if ($editUrl): ?>
                <div class="mt-14 border-t-2 border-dashed border-ink/30 pt-6 text-sm">
                    <a href="<?= e($editUrl) ?>" class="inline-flex items-center gap-1.5 font-semibold text-primary-600 hover:underline dark:text-primary-300">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16.9 3.5 3.6 3.6L8 19.6l-4.5 1 1-4.5L16.9 3.5Z"/></svg>
                        Spotted a typo? Edit this page on GitHub
                    </a>
                </div>
            <?php endif ?>

            <nav class="mt-8 grid gap-5 sm:grid-cols-2">
                <?php if ($prev): ?>
                    <a href="<?= e($prev["href"]) ?>" class="pop pop-hover group rounded-xl bg-paper p-4">
                        <span class="text-xs font-semibold tracking-wide text-stone-500 uppercase dark:text-stone-400">Previous</span>
                        <span class="block font-display text-lg font-bold text-ink">&larr; <?= e($prev["title"]) ?></span>
                    </a>
                <?php else: ?>
                    <span class="hidden sm:block"></span>
                <?php endif ?>
                <?php if ($next): ?>
                    <a href="<?= e($next["href"]) ?>" class="pop pop-hover group rounded-xl bg-accent-200 p-4 text-right dark:bg-primary-900">
                        <span class="text-xs font-semibold tracking-wide text-stone-500 uppercase dark:text-stone-300">Up next</span>
                        <span class="block font-display text-lg font-bold text-ink"><?= e($next["title"]) ?> &rarr;</span>
                    </a>
                <?php endif ?>
            </nav>
        </div>
    </main>

    <!-- Desktop: table of contents -->
    <aside class="sticky top-16 hidden h-[calc(100vh-4rem)] w-56 shrink-0 overflow-y-auto py-10 pr-4 xl:block">
        <?php if ($page["toc"]): ?>
            <h3 class="mb-3 font-display text-sm font-bold text-ink">📍 On this page</h3>
            <ul class="space-y-1 border-l-2 border-ink/20 text-sm">
                <?php foreach ($page["toc"] as $item): ?>
                    <li>
                        <a href="#<?= e($item["id"]) ?>" data-toc-link="<?= e($item["id"]) ?>"
                           class="-ml-0.5 block border-l-2 border-transparent py-1 text-stone-500 transition hover:text-ink dark:text-stone-400 <?= $item["level"] === 3 ? "pl-6" : "pl-3" ?>">
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
    const active = ["font-semibold", "text-primary-600", "border-primary-500", "dark:text-primary-300"];
    const observer = new IntersectionObserver((entries) => {
        entries.filter((entry) => entry.isIntersecting).slice(0, 1).forEach((entry) => {
            links.forEach((link) => active.forEach((c) => link.classList.toggle(c, link.dataset.tocLink === entry.target.id)));
        });
    }, { rootMargin: "-80px 0px -70% 0px" });
    document.querySelectorAll(".prose h2[id], .prose h3[id]").forEach((heading) => observer.observe(heading));
</script>
